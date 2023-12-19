FROM uselagoon/lagoon-cli:latest as LAGOONCLI
FROM amazeeio/advanced-task-toolbox:latest

#######################################################
# Install Laoon Tools Globally
#######################################################
COPY --from=LAGOONCLI /lagoon /usr/bin/lagoon
RUN DOWNLOAD_PATH=$(curl -sL "https://api.github.com/repos/uselagoon/lagoon-sync/releases/latest" | grep "browser_download_url" | cut -d \" -f 4 | grep linux_386) && wget -O /usr/bin/lagoon-sync $DOWNLOAD_PATH && chmod +x /usr/bin/lagoon-sync

#######################################################
# Copy files, and run installs for composer and yarn
#######################################################
COPY scripts /app
COPY src /app

#######################################################
# Setup environment 
#######################################################
# ENV PHP_MEMORY_LIMIT=8192M
