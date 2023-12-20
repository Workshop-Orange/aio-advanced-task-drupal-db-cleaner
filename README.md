# Advanced Task Drupal DB Cleaner

The advanced task drupal DB cleaner is designed to achieve two things
1. Return a report of matching tables in a Drupal which the cleaner considers removeable (nukeable)
2. DROP TABLES that match the pattern

The approach taken leverages https://github.com/uselagoon/advanced-task-toolbox, and follows the guidance of that project.

## Docker files
The repo contains two dockerfiles which usa a `FROM amazeeio/advanced-task-toolbox:latest`
- `drupclean_report.dockerfile` : Builds and image for the reporting task
- `drupclean_nuke.dockerfile` : Builds and image for the removing (nuking) task

There is a `docker-compose.yml` for building the containers locally under development.

## Advanced Task Tookbox 
There are two scripts in the `scripts/` directory, one for reporting and one for nuking. The docker build copies the relevant script to `default.yml` for the advanced task execution.

## Drupclean Code
In the `drupclean` directory you will find the code that is called by the Advanced Task Toolbox scripts. The code is designed to be run as a `drush scr` call.

For development, there is also a `drupclean_dev.php` which will create 1000 "bad" tables that can be used to test the script.

## Image building
When the code is pushed to Github, and action runs which will
1. Build the two images
2. Log into Dockerhub
3. Push the tagged images to Dockerhub as 
- aio-advanced-task-drupal-db-cleaner:report-latest
- aio-advanced-task-drupal-db-cleaner:nuker-latest

## Creating the Advanced Task in Lagoon
The relevant GraphQL template for the two Advanced Tasks are available in `create_task_*.gql`. Please note that these queries need at least the `project: ` id to be set correctly. This could be changed to `environment: ` if needed, or could be added to a whole group. Please see the Lagoon docs (http://docs.lagoon.sh) for more details on Advanced Tasks.

