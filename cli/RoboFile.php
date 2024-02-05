<?php

class RoboFile extends \Robo\Tasks
{
    private $graphqlClient;
    private $lagoonApiEndpoint = "https://api.lagoon.amazeeio.cloud/graphql";
    private $lagoonSshPort = "32222";
    private $lagoonSshHost = "ssh.lagoon.amazeeio.cloud";
    private $lagoonSshUser = "lagoon";
    private $lagoonToken;

    public function lagoonTaskBulkExecReport($projectListFile = "project-list.json") 
    {
        try {
            $this->validateProjectList($projectListFile);
            $this->initGraphqlClient();
            $projectList = $this->getProjectList($projectListFile);
            
            foreach($projectList as $projectEnvironment) {
                $taskInstanceResult = $this->kickoffTaskAndWaitReport($projectEnvironment["project"], $projectEnvironment["environment"]);
                $this->say("Task run complete for Project=".$projectEnvironment["project"]." Environment=".$projectEnvironment["environment"]);
                $this->io()->newLine();
            }

        } catch(Exception $ex) {
            $this->io()->error($ex->getMessage());
            return 255;
        }
    }

    private function kickoffTaskAndWaitReport($project, $environment)
    {
        $this->io()->title('Project: ' . $project . " | Environment: " . $environment);
        $this->say("Looking up Environment and Task IDs for Project=" . $project . " Environment=" . $environment);
        
        $envId = $this->getEnvironmentIdForProjectEnvironment($project, $environment);
        $this->say("Environment ID: " . $envId);
        
        $taskId = $this->findAdvancedTaskIdForEnvironment($envId, "Drupclean: Report");
        $this->say("Task ID: " . $taskId);

        if(empty($envId) || empty($taskId)) {
            throw new Exception("Error triggering report task (".$taskId.") on Project=" . $project . " Environment=" . $environment . " (".$envId.")");
        }

        $this->say("Triggering report task (".$taskId.") on Project=" . $project . " Environment=" . $environment . " (".$envId.")");
        
        $taskInstanceId = $this->kickoffTaskReport($envId, $taskId);
        $this->say("Task triggered: " . $taskInstanceId);

        sleep(5);

        $taskInstanceStatus = $this->getTaskInstanceStatus($taskInstanceId);
        while(! in_array($taskInstanceStatus, ["complete", "cancelled","failed"])) {
            $this->say("Task status: " . $taskInstanceStatus);
            sleep(5);
            $taskInstanceStatus = $this->getTaskInstanceStatus($taskInstanceId);
        }

        sleep(5);
        $logs = $this->getTaskInstanceLogs($taskInstanceId);
        
        $logUrl = $this->getReportUrlFromLogs($logs);
        $nukeables = $this->getNukeablesFromLogs($logs);
        $noteables = $this->getNostablesFromLogs($logs);

        $ret = [
            "taskInstanceId" => $taskInstanceId,
            "taskInstanceStatus" => $taskInstanceStatus,
            "nukeables" => $nukeables,
            "noteables" => $noteables,
            "logUrl" => $logUrl
        ];

        $this->io()->definitionList(
            "Task Result",
            ["Task Instance ID" => $ret["taskInstanceId"]],
            ["Task Instance Status" => $ret["taskInstanceStatus"]],
            ["Nukeables" => $ret["nukeables"]],
            ["Noteables" => $ret["noteables"]],
            ["Logs URL" => $ret["logUrl"]],
        );
        
        return $ret;
    }

    /**
     * Nukeables found: 1021
     */
    private function getNukeablesFromLogs($logs) {
        $ret = "";
        if(preg_match("/Nukeables found: (.*)/", $logs, $logMatch)) {
            $ret = isset($logMatch[1]) ? $logMatch[1] : "";
        }

        return $ret;
    }

    /**
     * Noteables found: 13
     */
    private function getNostablesFromLogs($logs) {
        $ret = "";
        if(preg_match("/Noteables found: (.*)/", $logs, $logMatch)) {
            $ret = isset($logMatch[1]) ? $logMatch[1] : "";
        }

        return $ret;
    }

    private function getReportUrlFromLogs($logs) {
        $ret = "";
        if(preg_match("/Report URL: (https.*.json)/", $logs, $logMatch)) {
            $ret = isset($logMatch[1]) ? $logMatch[1] : "";
        }

        return $ret;
    }

    private function getTaskInstanceLogs($taskInstanceId)
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        $query = "
            query q {
                taskById(id: ".$taskInstanceId.") {
                    id
                    logs
                }
            }
        ";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            throw new Exception("Lagoon API communication error looking up task isntance logs for TaskInstanceID=".$taskInstanceId);
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            if(!isset($data["taskById"]["logs"])) 
            {
                throw new Exception("Lagoon API communication error looking up task isntance logs for TaskInstanceID=".$taskInstanceId);
            }
            
            return $data["taskById"]["logs"];
        }

        throw new Exception("Lagoon API communication error looking up task isntance logs for TaskInstanceID=".$taskInstanceId);
    }

    private function getTaskInstanceStatus($taskInstanceId)
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        $query = "
            query q {
                taskById(id: ".$taskInstanceId.") {
                    id
                    status
                }
            }
        ";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            throw new Exception("Lagoon API communication error looking up task isntance status for TaskInstanceID=".$taskInstanceId);
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            if(!isset($data["taskById"]["status"])) 
            {
                throw new Exception("Lagoon API communication error looking up task isntance status for TaskInstanceID=".$taskInstanceId);
            }
            
            return $data["taskById"]["status"];
        }

        throw new Exception("Lagoon API communication error looking up task isntance status for TaskInstanceID=".$taskInstanceId);
    }

    private function findAdvancedTaskIdForEnvironment($envId, $taskName)
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        $query = "
            query q {
                environmentById(id: ".$envId.") {
                    advancedTasks {
                        ... on AdvancedTaskDefinitionImage {
                            name
                            id
                        }
                    }
                }
            }
        ";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            throw new Exception("Lagoon API communication error looking up task id for EnvironmentId=".$envId. " Task=".$taskName);
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            if(!isset($data["environmentById"]["advancedTasks"]) || ! is_array($data["environmentById"]["advancedTasks"])) 
            {
                throw new Exception("Lagoon API communication error looking up task id for EnvironmentId=".$envId. " Task=".$taskName);
            }
            
            foreach($data["environmentById"]["advancedTasks"] as $env) {
                if(isset($env["name"]) && $env["name"] == $taskName) {
                    return $env["id"];
                }
            }
        }

        throw new Exception("Lagoon API communication error looking up task id for EnvironmentId=".$envId. " Task=".$taskName);
    }

    private function kickoffTaskReport($envId, $taskId) 
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        $query = "
            mutation m {
                invokeRegisteredTask(advancedTaskDefinition: ".$taskId.", environment: ".$envId.")
                {
                id
                }
            }
        ";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            throw new Exception("Lagoon API communication error looking up environment id for Environment=".$envId." Task=".$taskId);
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            if(!isset($data["invokeRegisteredTask"]["id"])) 
            {
                throw new Exception("Could not invoke Task=".$taskId." on Environment=".$envId);
            }
            
            return $data["invokeRegisteredTask"]["id"];
        }

        throw new Exception("Could not invoke Task=".$taskId." on Environment=".$envId);
    }

    private function getEnvironmentIdForProjectEnvironment($project, $environment) {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        $query = "
        query q {
          projectByName(name: \"".$project."\"){
            id
            name
            environments {
              name
              id
            }
          }
        }
        ";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            throw new Exception("Lagoon API communication error looking up environment id for Project=" . $project . " Environment=".$environment);
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            if(!isset($data["projectByName"]["environments"]) || ! is_array($data["projectByName"]["environments"])) 
            {
                throw new Exception("Project=".$project . " Environment=". $environment . " not found");
            }
            
            foreach($data["projectByName"]["environments"] as $env) {
                if(isset($env["name"]) && $env["name"] == $environment) {
                    return $env["id"];
                }
            }
        }

        throw new Exception("Project=".$project . " Environment=". $environment . " not found");
    }

    public function pingLagoonAPI() : bool
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        /**
         * Query Example
         */
        $query = "
          query q {
            lagoonVersion
            me {
              id
            }
          }";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            return false;
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();

            return isset($data['lagoonVersion']) && isset($data['me']['id']);
        }

        return true;
    }

    private function initGraphqlClient()
    {
        $this->getLagoonToken();

        if(empty($this->lagoonToken)) {
            $this->io()->error("Could not get a Lagoon token");
        }

        $this->graphqlClient = \Softonic\GraphQL\ClientBuilder::build($this->lagoonApiEndpoint, [
            'headers' => [
                'Authorization'     => 'Bearer ' . $this->lagoonToken
            ]
        ]);

        if($this->pingLagoonAPI()) {
            $this->say("Lagoon API communication initialized");
        } else {
            throw new Exception("Lagoon API communication initialization error");
        }
    }

    private function getLagoonToken() {
        $result = $this->taskSshExec($this->lagoonSshHost, $this->lagoonSshUser)
        ->port($this->lagoonSshPort)
        ->printOutput(FALSE)
        ->exec('token')
        ->run();

        $this->lagoonToken = $result->getMessage();
        return $this->lagoonToken;
    }

    private function validateProjectList($projectListFile) {
        if(!file_exists($projectListFile)) {
            throw new Exception("File-no-found: " . $projectListFile);
        }

        $arr = explode("\n", trim(file_get_contents($projectListFile)));

        foreach($arr as $line) {
            $parts = explode(",", $line);
            $project = isset($parts[0]) ? trim($parts[0]) : "";
            $environment = isset($parts[1]) ? trim($parts[1]) : "";

            if(empty($project) || empty($environment)) {
                throw new Exception("File-format-invalid: Project=" . $project . " Environment=" . $environment);
            }
        }

        return TRUE;
    }

    private function getProjectList($projectListFile) {
        $this->validateProjectList($projectListFile);

        $returnData = [];

        $arr = explode("\n", trim(file_get_contents($projectListFile)));

        foreach($arr as $line) {
            $parts = explode(",", $line);
            $project = isset($parts[0]) ? trim($parts[0]) : "";
            $environment = isset($parts[1]) ? trim($parts[1]) : "";

            $returnData[] = [
                "project" => $project,
                "environment" => $environment
            ];
        }

        return $returnData;
    }
}   