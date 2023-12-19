FROM amazeeio/advanced-task-toolbox:latest

#######################################################
# Copy and set Lagoon Advanced Task script file
#######################################################
COPY scripts/nuke.yaml /app/scripts/default.yaml

#######################################################
# Copy drush src files for use with drush scr
#######################################################
COPY drupclean /app/drupclean

#######################################################
# Setup environment 
#######################################################
# ENV PHP_MEMORY_LIMIT=8192M
