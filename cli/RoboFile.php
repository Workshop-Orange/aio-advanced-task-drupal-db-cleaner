<?php

class RoboFile extends \Robo\Tasks
{
    private $graphqlClient;
    private $lagoonApiEndpoint = "https://api.lagoon.amazeeio.cloud/graphql";
    private $lagoonSshPort = "32222";
    private $lagoonSshHost = "ssh.lagoon.amazeeio.cloud";
    private $lagoonSshUser = "lagoon";
    private $lagoonToken;
    private $reportId;
    private $reportDirBase = "reports/";
    private $reportDir;

    public function lagoonGetAllProjectsAndEnvironmentsInGroupsToCsv($groups)
    {
        try {
            $this->initGraphqlClient();
            $this->initReportResult();
            $this->say("Report directory results initialized: " . $this->reportDir);

        } catch(Exception $ex) {
            $this->io()->error($ex->getMessage());
            return 255;
        }

        $grpArr = [];
        if(preg_match("/,/", $groups)) {
            $grpArr = explode(",", $groups);
        } else {
            $grpArr[] = $groups;
        }

        foreach($grpArr as $group) {
            $projects = $this->getLagoonProjectsAndEnvironmentsForGroup(trim($group));
            foreach($projects as $project) {
              file_put_contents($this->reportDir . "/project-list.csv", implode(",", $project) . PHP_EOL, FILE_APPEND);
            }
        }

        $this->say("Your project list is available at: " . $this->reportDir. "/project-list.csv");
    }

    public function lagoonTaskBulkExecReport($projectListFile = "project-list.csv", $opts = ['generate-nuke-project-list' => false]) 
    {
        try {
            $this->validateProjectList($projectListFile);
            $this->initGraphqlClient();
            $this->initReportResult();
            $this->say("Report directory results initialized: " . $this->reportDir);

        } catch(Exception $ex) {
            $this->io()->error($ex->getMessage());
            return 255;
        }

        $projectList = $this->getProjectList($projectListFile);

        $nukeList = [];
        
        $starting = time();
        $this->say("Starting a report on " . count($projectList) . " projects");
        $tot = count($projectList);
        $cnt = 0;
        foreach($projectList as $projectEnvironment) {
            $age = time() - $starting;
            $cnt++;
            try {
                $this->say("Project ". $cnt . " of " . $tot . " (total runtime " . $age . " seconds)"); 
                $taskInstanceResult = $this->kickoffTaskAndWaitReport($projectEnvironment["project"], $projectEnvironment["environment"]);
                $this->logTaskResultReport(
                    $projectEnvironment["project"] ?? "", 
                    $projectEnvironment["environment"] ?? "",
                    $taskInstanceResult["taskInstanceId"] ?? "", 
                    $taskInstanceResult["taskInstanceStatus"] ?? "", 
                    $taskInstanceResult["nukeables"] ?? "", 
                    $taskInstanceResult["noteables"] ?? "", 
                    $taskInstanceResult["noteables_entity_mapped"] ?? "", 
                    $taskInstanceResult["noteables_not_entity_mapped"] ?? "", 
                    $taskInstanceResult["logUrl"] ?? "");
                    
                $this->say("Task nuke run complete for Project=".$projectEnvironment["project"]." Environment=".$projectEnvironment["environment"]);
                $this->io()->newLine();

                if ($opts['generate-nuke-project-list']) {
                    if($taskInstanceResult['nukeables'] ?? 0 > 0) {
                        $nukeList[$projectEnvironment["project"]][$projectEnvironment["environment"]] = TRUE;
                    }
                }

            } catch (Exception $ex) {
                $this->logTaskResultReport(
                    $projectEnvironment["project"] ?? "", 
                    $projectEnvironment["environment"] ?? "",
                    0, 
                    $ex->getMessage(), 
                    "", 
                    "", 
                    "", 
                    "", 
                    "");
            }
        }

        if($opts['generate-nuke-project-list'] && count($nukeList)) {
            $outfile = $this->reportDir . "/nuke-project-list.csv";
            foreach($nukeList as $project => $environments) {
                foreach($environments as $environment => $addIt) {
                    file_put_contents($outfile, $project . "," . $environment . PHP_EOL, FILE_APPEND);
                }
            }

            $this->say("The projects that can be nuked are listed in: " . $outfile);
        }
    }

    public function lagoonTaskBulkExecNuke($projectListFile = "project-list.csv") 
    {
        try {
            $this->validateProjectList($projectListFile);
            $this->initGraphqlClient();
            $this->initNukeResult();
            $this->say("Nuke directory results initialized: " . $this->reportDir);
        } catch(Exception $ex) {
            $this->io()->error($ex->getMessage());
            return 255;
        }

        $projectList = $this->getProjectList($projectListFile);
        $starting = time();
        $this->say("Starting a report on " . count($projectList) . " projects");
        $tot = count($projectList);
        $cnt = 0;
        foreach($projectList as $projectEnvironment) {
            $age = time() - $starting;
            $cnt++;
            try {
                $this->say("Project ". $cnt . " of " . $tot . " (total runtime " . $age . " seconds)"); 
                $taskInstanceResult = $this->kickoffTaskAndWaitNuke($projectEnvironment["project"], $projectEnvironment["environment"]);
                $this->logTaskResultNuke(
                    $projectEnvironment["project"] ?? "", 
                    $projectEnvironment["environment"] ?? "",
                    $taskInstanceResult["taskInstanceId"] ?? "", 
                    $taskInstanceResult["taskInstanceStatus"] ?? "", 
                    $taskInstanceResult["nukeables"] ?? "", 
                    $taskInstanceResult["logUrl"] ?? "");
                    
                $this->say("Task nuke run complete for Project=".$projectEnvironment["project"]." Environment=".$projectEnvironment["environment"]);
                $this->io()->newLine();

            } catch (Exception $ex) {
                $this->logTaskResultNuke(
                    $projectEnvironment["project"] ?? "", 
                    $projectEnvironment["environment"] ?? "",
                    0, 
                    $ex->getMessage(), 
                    "", 
                    "");
            }
        }

    }

    private function getLagoonProjectsAndEnvironmentsForGroup($groupName)
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        $query = "
            query q {
                allProjectsInGroup(input: {name: \"".$groupName."\"}){
                    name
                    environments {
                      name
                    }
                } 
            }
        ";

        $returnData = [];
        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            throw new Exception("Lagoon API communication error looking up projects for group=".$group);
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            if(!isset($data["allProjectsInGroup"]) || !is_array($data["allProjectsInGroup"])) 
            {
                throw new Exception("Lagoon API communication error looking up projects for group=".$group);
            }
            
            foreach($data["allProjectsInGroup"] as $project) {
                foreach($project["environments"] as $environment) {
                    $returnData[]  = [
                        $project["name"],
                        $environment["name"]
                    ];
                }
            }

            return $returnData;
        }

        throw new Exception("Lagoon API communication error looking up projects for group=".$group);
    }

    private function initReportResult()
    {
        $this->reportId = date('Y-m-d_H-i-s') . "-report-" . uniqid();
        $this->reportDir = $this->reportDirBase . $this->reportId;
        if(! is_dir($this->reportDir)) {
            mkdir($this->reportDir);
        }

        file_put_contents($this->reportDir . "/results.csv", implode(",", [
            "project",
            "environment",
            "task_instance_id",
            "task_status_id",
            "nukeables",
            "noteables",
            "noteables_entity_mapped",
            "noteables_not_entity_mapped",
            "log_url"      
        ]). PHP_EOL);
    }
    
    private function initNukeResult()
    {
        $this->reportId = date('Y-m-d_H-i-s') . "-nuke-" . uniqid();
        $this->reportDir = $this->reportDirBase . $this->reportId;
        if(! is_dir($this->reportDir)) {
            mkdir($this->reportDir);
        }

        file_put_contents($this->reportDir . "/results.csv", implode(",", [
            "project",
            "environment",
            "task_instance_id",
            "task_status_id",
            "nukeables",
            "log_url"      
        ]). PHP_EOL);
    }

    private function logTaskResultReport($project, $environment, $taskInstanceId, $taskInstanceStatus, $nukeables, $noteables, $noteables_mapped, $noteables_not_mapped, $logUrl) 
    {
        file_put_contents($this->reportDir . "/results.csv", implode(",", [
            $project,
            $environment,
            $taskInstanceId,
            $taskInstanceStatus,
            $nukeables,
            $noteables,
            $noteables_mapped,
            $noteables_not_mapped,
            $logUrl      
        ]) . PHP_EOL, FILE_APPEND);
    }

    private function logTaskResultNuke($project, $environment, $taskInstanceId, $taskInstanceStatus, $nukeables, $logUrl) 
    {
        file_put_contents($this->reportDir . "/results.csv", implode(",", [
            $project,
            $environment,
            $taskInstanceId,
            $taskInstanceStatus,
            $nukeables,
            $logUrl      
        ]) . PHP_EOL, FILE_APPEND);
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
        $start = time();
        while(! in_array($taskInstanceStatus, ["complete", "cancelled","failed"])) {
            $age = time() - $start;
            if($age > (60*15)) {
                throw new Exception("Timeout reached after " . $age . " seconds.");
            }

            $this->say("Task status: " . $taskInstanceStatus);
            sleep(3);
            $taskInstanceStatus = $this->getTaskInstanceStatus($taskInstanceId);
        }

        sleep(5);
        $logs = $this->getTaskInstanceLogs($taskInstanceId);
        
        $logUrl = $this->getReportUrlFromLogs($logs);
        $nukeables = $this->getNukeablesFromLogs($logs);
        $noteables = $this->getNoteablesFromLogs($logs);
        $noteablesEntityMapped = $this->getNoteablesEntityMappedFromLogs($logs);
        $noteablesNotEntityMapped = $this->getNoteablesNotEntityMappedFromLogs($logs);
        

        $ret = [
            "taskInstanceId" => $taskInstanceId,
            "taskInstanceStatus" => $taskInstanceStatus,
            "nukeables" => $nukeables,
            "noteables" => $noteables,
            "noteables_entity_mapped" => $noteablesEntityMapped,
            "noteables_not_entity_mapped" => $noteablesNotEntityMapped,
            "logUrl" => $logUrl
        ];

        $this->io()->definitionList(
            "Task Result",
            ["Task Instance ID" => $ret["taskInstanceId"]],
            ["Task Instance Status" => $ret["taskInstanceStatus"]],
            ["Nukeables" => $ret["nukeables"]],
            ["Noteables" => $ret["noteables"]],
            ["Noteables Entity Mapped" => $ret["noteables_entity_mapped"]],
            ["Noteables Not Entity Mapped" => $ret["noteables_not_entity_mapped"]],
            ["Logs URL" => $ret["logUrl"]]
        );
        
        return $ret;
    }

    private function kickoffTaskAndWaitNuke($project, $environment)
    {
        $this->io()->title('Project: ' . $project . " | Environment: " . $environment);
        $this->say("Looking up Environment and Task IDs for Project=" . $project . " Environment=" . $environment);
        
        $envId = $this->getEnvironmentIdForProjectEnvironment($project, $environment);
        $this->say("Environment ID: " . $envId);
        
        $taskId = $this->findAdvancedTaskIdForEnvironment($envId, "Drupclean: Nuke - BEWARE");
        $this->say("Task ID: " . $taskId);

        if(empty($envId) || empty($taskId)) {
            throw new Exception("Error triggering nuke task (".$taskId.") on Project=" . $project . " Environment=" . $environment . " (".$envId.")");
        }

        $this->say("Triggering nuke task (".$taskId.") on Project=" . $project . " Environment=" . $environment . " (".$envId.")");
        
        $taskInstanceId = $this->kickoffTaskNuke($envId, $taskId);
        $this->say("Task triggered: " . $taskInstanceId);

        sleep(5);

        $taskInstanceStatus = $this->getTaskInstanceStatus($taskInstanceId);
        $start = time();
        while(! in_array($taskInstanceStatus, ["complete", "cancelled","failed"])) {
            $age = time() - $start;
            if($age > (60*15)) {
                throw new Exception("Timeout reached after " . $age . " seconds.");
            }

            $this->say("Task status: " . $taskInstanceStatus);
            sleep(5);
            $taskInstanceStatus = $this->getTaskInstanceStatus($taskInstanceId);
        }

        sleep(5);
        $logs = $this->getTaskInstanceLogs($taskInstanceId);
        
        $logUrl = $this->getReportUrlFromLogs($logs);
        $nukeablesNuked = $this->getNukeablesNukedFromLogs($logs);

        $ret = [
            "taskInstanceId" => $taskInstanceId,
            "taskInstanceStatus" => $taskInstanceStatus,
            "nukeables_nuked" => $nukeablesNuked,
            "logUrl" => $logUrl
        ];

        $this->io()->definitionList(
            "Task Result",
            ["Task Instance ID" => $ret["taskInstanceId"]],
            ["Task Instance Status" => $ret["taskInstanceStatus"]],
            ["Nukeables Nuked" => $ret["nukeables_nuked"]],
            ["Logs URL" => $ret["logUrl"]],
        );
        
        return $ret;
    }

    /**
     * Nukeables nuked: 1021
     */
    private function getNukeablesNukedFromLogs($logs) {
        $ret = "";
        if(preg_match("/Nukeables nuked: (.*)/", $logs, $logMatch)) {
            $ret = isset($logMatch[1]) ? $logMatch[1] : "";
        }

        return $ret;
    }

    private function getNoteablesEntityMappedFromLogs($logs) {
        $ret = "";
        if(preg_match("/is_entity_mapped.*(\d+)/", $logs, $logMatch)) {
            $ret = isset($logMatch[1]) ? $logMatch[1] : "";
        }

        return $ret;
    }

    private function getNoteablesNotEntityMappedFromLogs($logs) {
        $ret = "";
        if(preg_match("/is_not_entity_mapped.*(\d+)/", $logs, $logMatch)) {
            $ret = isset($logMatch[1]) ? $logMatch[1] : "";
        }

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
    private function getNoteablesFromLogs($logs) {
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

    private function kickoffTaskNuke($envId, $taskId) 
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new Exception("Lagoon api communication error");
        }

        $query = "
            mutation m {
                invokeRegisteredTask(advancedTaskDefinition: ".$taskId.", 
                    environment: ".$envId.",
                    argumentValues: {
                        advancedTaskDefinitionArgumentName: \"DRUPCLEAN_NUKE_CONFIRM\", 
                        value: \"I UNDERSTAND\"
                    }
                )
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