#!/bin/bash
set -o pipefail
IFS=$'\n\t'

# Function to safely call tput
safe_tput() {
  tput "$@" 2>/dev/null
}

# Defines some global variables for colors with fallback values.
normal=$(safe_tput sgr0)
bold=$(safe_tput bold)
red=$(safe_tput setaf 1)
green=$(safe_tput setaf 2)
yellow=$(safe_tput setaf 3)
# Additional styles we aren't currently using.
#italic=$(safe_tput sitm)
#blue=$(safe_tput setaf 4)
#magenta=$(safe_tput setaf 5)
#cyan=$(safe_tput setaf 6)

# Use environment variables if set, otherwise prompt for input
sitename="${SITENAME:-}"
sftpuser="${SFTPUSER:-}"
sftphost="${SFTPHOST:-}"
sagename="${SAGENAME:-}"
phpversion="${PHPVERSION:-8.1}"
is_ci="${CI:-0}"
siteenv="${SITEENV:-dev}"
if [ "$siteenv" == "dev" ]; then
  branch="master"
else
  branch="$siteenv"
fi

# Main function that runs the script.
function main() {
  help_msg="Usage: bash ./private/scripts/helpers.sh <command>
  Available commands:
    install_sage: Install Sage.
    maybe_create_symlinks: Create a symlinks to WordPress files, if they don't already exist.
    update_php: Updates PHP version to 8.1 if it's not already set at 8.1 or higher."

  if [ -z "$1" ]; then
    echo "${red}No command specified.${normal}"
    echo "${help_msg}"
    exit 1;
  fi

  if [ "$1" == "help" ]; then
    echo "${help_msg}"
    exit 0;
  fi

  # Check for a valid command.
  if [ "$1" != "install_sage" ] && [ "$1" != "maybe_create_symlinks" ] && [ "$1" != "update_php" ]; then
    echo "${red}Invalid command specified.${normal}"
    echo "${help_msg}"
    exit 1;
  fi

  # Execute the command passed.
  "$1"
}

# Get the site name, theme name, and SFTP credentials from the user.
function get_info() {
  # If is_restarted is unset, set it to 0.
  if [ -z "$is_restarted" ]; then
    is_restarted=0
  fi

  dashboard_link="https://dashboard.pantheon.io/sites/${id}#dev/code"

  # Unset the variables if we're doing this a second time.
  if [ "$is_restarted" -eq 1 ]; then
    if [ "$is_ci" -eq 1 ]; then
      echo "${yellow}CI detected. We're not going to restart. Bailing here.${normal}"
      exit 1;
    fi
    unset sitename
    unset sagename
    unset sftpuser
    unset sftphost
  fi

  if [ "$is_restarted" -eq 0 ]; then
    echo "${yellow}Finding site information...${normal}"
  fi

  # Set up some defaults. These should evaluate to false if you go through
  # the prompts once but say no to the confirmation because at that point we
  # set them to empty strings.
  # There's some discussion about the brackets distinction in this
  # StackOverflow: https://stackoverflow.com/a/13864829/1351526
  if [ "${is_restarted}" -eq 0 ] && [ -z "${sitename}" ]; then
    echo "Found site name! Using ${name}."
    sitename=$name
  fi

  if [ "${is_restarted}" -eq 0 ] && [ -z "${sftpuser}" ]; then
    echo "Found SFTP username! Using dev.${id}."
    sftpuser=dev.$id
  fi

  if [ "${is_restarted}" -eq 0 ] && [ -z "${sftphost}" ]; then
    echo "Found SFTP host name! Using appserver.dev.${id}.drush.in."
    sftphost=appserver.dev.$id.drush.in
  fi

  if [ -z "$sitename" ] && [ -z "$sftpuser" ] && [ -z "$sftphost" ]; then
    echo "No site information found. You can enter it manually."
  fi

  if [ "$is_restarted" -eq 0 ]; then
    echo "--------------------------------------------------------------------------"
  fi
  # We want these to evaluate to false if they're empty strings so they can be
  # set manually.
  if [ -z "$sitename" ]; then
    echo "${yellow}Enter the site name.${normal}"
    echo "This will be used to interact with your site. The default is ${green}${name}${normal}."
    read -p "Site name: " -r sitename
  else
    echo "${green}Site name: ${normal}${sitename}"
  fi

  if [ -z "$sftpuser" ]; then
    echo "${yellow}Enter your SFTP username.${normal}"
    echo "This will only be stored in this terminal session. This can be found in your site dashboard. The default is ${green}dev.${id}${normal}."
    echo "Dashboard link: ${dashboard_link}"
    read -p "SFTP username: " -r sftpuser
  else
    echo "${green}SFTP username: ${normal}${sftpuser}"
  fi

  if [ -z "$sftphost" ]; then
    echo "${yellow}Enter your SFTP hostname.${normal}"
    echo "This will only be stored in this terminal session. This can be found in your site dashboard. The default is ${green}appserver.dev.${id}.drush.in${normal}."
    echo "Dashboard link: ${dashboard_link}"
    read -p "SFTP hostname: " -r sftphost
  else
    echo "${green}SFTP hostname: ${normal}${sftphost}"
  fi

  if [ "$is_restarted" -eq 0 ]; then
    echo "--------------------------------------------------------------------------"
  fi

  # This is the first input that doesn't have a default. We'll do the line break above this.
  if [ -z "$sagename" ]; then
    echo "${yellow}Enter your theme name.${normal}"
    echo "This is used to create the theme directory. As such, it should ideally be all lowercase with no spaces (hyphens or underscores recommended)"
    read -p "Theme name: " -r sagename
    confirmThemeName "$sagename"
  else
    echo "${green}Theme name: ${sagename}${normal}"
  fi

  echo "You've entered:
  Site name: ${sitename}
  Theme name: ${sagename}
  SFTP username: ${sftpuser}
  SFTP hostname: ${sftphost}"
  if [ "$is_ci" != 1 ]; then
    read -p "Is this correct? (y/n) " -n 1 -r
    # If the user enters n, redo the prompts.
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
      echo "Restarting..."

      # Toggle the restarted state.
      is_restarted=1
      get_info
    fi
  fi

  if [ -z "$sitename" ] || [ -z "$sagename" ] || [ -z "$sftpuser" ] || [ -z "$sftphost" ]; then
    echo "${red}Missing information!${normal} Make sure you input everything for all the prompts."
    get_info
  fi

  # Set the theme directory. Do this at the end, after we know what everything should be.
  sagedir=$themedir/$sagename
}

# Create the web/index.php if it does not exist.
function check_index_php() {
  # Check if we are in the web directory
  if [ "${PWD##*/}" != "web" ]; then
    echo "${red}Error: You must be in the 'web' directory to run this script.${normal}"
    exit 1
  fi
  # Check if index.php exists, create if it doesn't
  if [ ! -f "index.php" ]; then
    echo "${yellow}Creating index.php...${normal}"
    cat > index.php << 'EOF'
<?php
/**
 * WordPress View Bootstrapper
 */
define('WP_USE_THEMES', true);
require __DIR__ . '/wp/wp-blog-header.php';
EOF
    echo "${green}Created index.php${normal}"
  fi
}

# Confirm user-submitted theme-name and ensure letters are lower-space
function confirmThemeName() {
  # Replace spaces with dashes and convert to lowercase
  echo "Validating theme name..."
  sagename=$(echo "$sagename" | tr '[:space:]' '-' | tr '[:upper:]' '[:lower:]')

  # Replace underscores with dashes
  sagename=${sagename//_/\-}

  # Remove double dashes
  while [[ $sagename == *--* ]]; do
    sagename=${sagename/--/-}
  done

  # Remove trailing dash (if present)
  sagename=${sagename%-}

  echo "Theme name is: $sagename"
}

# Use terminus whoami to check if the user is logged in and exit the script if they are not.
function check_login() {
  echo "${yellow}Checking if you are logged in to Terminus...${normal}"
  # Read the response from terminus whoami into the REPLY variable.
  REPLY=$(terminus whoami)

  # If the response does not include a @, you're not logged in.
  # Ask the user to log in and exit.
  if [[ $REPLY != *"@"* ]]; then
    echo "${red}You are not logged in to Terminus.${normal}"
    echo "Please authenticate with terminus first using ${bold}terminus auth:login${normal}"
    exit 1;
  fi
}

# Get a field from the output of terminus site:info.
get_field() {
  local input="$1"
  # Remove leading and trailing whitespace from each line
  input="${input#"${input%%[![:space:]]*}"}"

  if [[ -n "$input" ]]; then
    # Remove the first and last lines that are entirely dashes
    input="$(echo "$input" | sed '1d; $d')"
  fi

  # $1: field name
  # $2: input string
  echo "$2" | awk -v field="$1" '$1 == field { print $2 }'
}

# Update to PHP 8.1 or higher
function update_php() {
  # If an old PHP version was passed into the script from the outside, fall back to 8.1.
  if [ "$phpversion" == "8" ] || [ "$phpversion" == "8.0" ]; then
    phpversion="8.1"
  fi

  # Check if $phpversion is < 8.1. We shouldn't get here because we just updated $phpversion (which is passed from the environment), so if we are here, it's a problem.
  if [ "$(echo "$phpversion < 8.1" | bc)" -eq 1 ]; then
    echo "${red}PHP version must be 8.1 or greater. Exiting here.${normal}"
    exit 1
  fi

  echo ""
  echo "${yellow}Checking PHP version and maybe updating to ${phpversion}.${normal}"

  # Check for pantheon.yml file.
  if [ ! -f "pantheon.yml" ]; then
    echo "${red}No pantheon.yml file found. Exiting here.${normal}"
    echo "Make sure you are inside a valid Pantheon repository."
    exit 1
  fi

  # Check the current PHP version declared in pantheon.yml
  currentPhpVersion=$(awk '/php_version:/{print $2}' pantheon.yml)

  if [ -z "$currentPhpVersion" ]; then
    # Add the PHP version declaration if not present.
    echo "" >> pantheon.yml
    echo "php_version: ${phpversion}" >> pantheon.yml
  elif [ "$(echo "$currentPhpVersion < 8.1" | bc)" -eq 1 ]; then
    # Update the PHP version declaration if it's less than 8.1.
    sed -i.bak "s/php_version: [0-9.]*/php_version: ${phpversion}/" pantheon.yml && rm pantheon.yml.bak
  else
    # We've got a good PHP version, so we can bail here.
    echo "${green}PHP version is already ${currentPhpVersion} which is >= 8.1.${normal}"
    exit 0
  fi

  # If we're in CI, don't run the push actions. Note: if you're running Bats tests locally, you should pass CI=1 before running the tests.
  if [ "$is_ci" -eq 0 ]; then
    git add pantheon.yml
    git commit -m "[Sage Install] Update PHP version to ${phpversion}"
    git push origin "$branch"
  else
    echo "${yellow}CI detected. Skipping Git operations. PHP updated to ${phpversion}.${normal}"
  fi
}

# Install sage and related dependencies.
function install_sage_theme() {
  # Check if the directory $sagedir is empty. If it's not, bail.
  echo "Checking if ${sagedir} exists and if it's empty."

  if [ "$(ls -A "$sagedir")" ]; then
    echo "${red}Directory not empty!${normal}"
    if [ "$is_ci" -eq 1 ]; then
      echo "${yellow}Removing ${sagedir} for CI tests.${normal}"
      rm -rf "$sagedir"
    fi
    echo "Trying to install into ${sagedir}. Exiting."
    exit 1;
  fi

  echo "${yellow}Installing Sage.${normal}"
  # Create the new Sage theme
  composer create-project roots/sage "$sagedir"

  # Require Roots/acorn
  composer require roots/acorn --working-dir="$sagedir"

  # Install all the Sage dependencies
  composer install --no-dev --prefer-dist --working-dir="$sagedir"

  # NPM the things
  npm install --prefix "$sagedir"
  npm run build --prefix "$sagedir"

  # Remove /public from .gitignore
  sed -i '' "s/\/public//" "$sagedir"/.gitignore

  # Commit the theme
  git add "$sagedir"
  git commit -m "[Sage Install] Add the Sage theme ${sagename}."
  git push
  echo "${green}Sage installed!${normal}"
}

# Create the symlink to the cache directory.
function add_symlink() {
  echo "Waiting for the last step to finish before switching to SFTP mode."
  terminus workflow:wait "$sitename"."$siteenv"

  # Switch to SFTP mode
  terminus connection:set "$sitename"."$siteenv" sftp

  if [ ! -d "web/app/uploads" ]; then
    echo "${yellow}Creating the uploads directory.${normal}"
    mkdir web/app/uploads
  fi

  if [ ! -d "web/app/uploads/cache" ]; then
    echo "${yellow}Creating the cache directory.${normal}"
    mkdir web/app/uploads/cache
  fi

  # Create a files/cache directory on the host if one does not already exist.
  echo "Checking if /files/cache exists..."
  if [ "$(sftp -P 2222 "$sftpuser"@"$sftphost" <<< "ls /files" | grep -c "/cache[[:space:]]*$")" -eq 0 ]; then
    echo "Creating /files/cache directory..."
    sftp -P 2222 "$sftpuser"@"$sftphost" <<EOF
cd /files
mkdir cache
EOF
  fi

    # Switch back to Git mode.
    terminus connection:set "$sitename"."$siteenv" git

  # Create the symlink to /files/cache.
  cd web/app || return
  echo "${yellow}Adding a symlink to the cache directory.${normal}"
  ln -sfn uploads/cache .
  git add .
  git commit -m "[Sage Install] Add a symlink for /files/cache to /uploads/cache"
  cd ../..
}

# Check if jq is installed. If it's not, try a couple ways of installing it.
function check_jq() {
  echo "${yellow}Checking if jq is installed.${normal}"
  # Check if jq is already installed.
  if command -v jq &> /dev/null; then
      return # jq is already installed
  fi
  echo "${yellow}jq is not installed.${normal}"
  # Check if brew is installed and install jq with it
  if command -v brew &> /dev/null; then
    echo "${yellow}Installing jq with Homebrew.${normal}"
    brew install jq
    return
  fi
  # Check if apt-get is installed and install jq with it
  if command -v apt-get &> /dev/null; then
    echo "${yellow}Installing jq with apt-get.${normal}"
    sudo apt-get install -y jq
    return
  fi
  echo "${yellow}Attempted to install jq but could not find Brew (for MacOS) or apt-get (for WSL2/Linux). Exiting here. You'll need to add the following lines to your composer.json:${normal}"
  echo '  "scripts": {'
  echo '      "post-install-cmd": ['
  echo "          \"@composer install --no-dev --prefer-dist --ignore-platform-reqs --working-dir=web/app/themes/$sagename\""
  echo '      ],'
  echo "${yellow}You might try running 'lando composer install-sage' if you are running locally. Alternately, you can try installing jq another way and then running the script again: https://stedolan.github.io/jq/download/${normal}"
  exit 1
}

# Add a post-install hook to the composer.json.
function update_composer() {
  composer update
  # Check if the lock file has been modified.
  if [ -n "$(git status --porcelain)" ]; then
    echo "${yellow}Updating composer.lock.${normal}"
    git add composer.lock
    git commit -m "[Sage Install] Update composer.lock"
  fi

  echo "${yellow}Attempting to add a post-install hook to composer.json.${normal}"

  # Check of jq is installed
  check_jq

  # Add a post-install hook to the composer.json.
  echo "${yellow}Adding a post-install hook to composer.json.${normal}"
  jq -r '.scripts += { "post-install-cmd": [ "@composer install --no-dev --prefer-dist --ignore-platform-reqs --working-dir=%sagedir%" ] }' composer.json > composer.new.json

  if [[ "$OSTYPE" == "darwin"* ]]; then
    # Mac OS
    if ! sed -i '' "s,%sagedir%,$sagedir," composer.new.json; then
      echo "${red}Failed to add post-install hook to composer.json. Exiting here.${normal}"
      exit 1;
    fi
  else
    # Linux
    if ! sed -i "s,%sagedir%,$sagedir," composer.new.json; then
      echo "${red}Failed to add post-install hook to composer.json. Exiting here.${normal}"
      exit 1;
    fi
  fi

  rm composer.json
  mv composer.new.json composer.json

  # Commit the change to composer.json
  git add composer.json
  git commit -m "[Sage Install] Add post-install-cmd hook to also run install on ${sagename}"

  if ! git push origin "$branch"; then
    echo "${red}Push failed. Stopping here.${normal}"
    echo "Next steps are to push the changes to the repo and then set the connection mode back to Git."
    exit 1;
  fi

  # Wait for the build to finish.
  echo "${yellow}Waiting for the deploy to finish.${normal}"
  if [ "$is_ci" -eq 1 ]; then
    echo "${yellow}CI detected. We'll need to wait longer.${normal}"
    waittime=180
  else
    waittime=90
  fi
  if ! terminus workflow:wait --max="$waittime" "$sitename"."$siteenv"; then
    echo "${red}terminus workflow:wait command not found. Stopping here.${normal}"
    echo "You will need to install the terminus-build-tools-plugin."
    echo "terminus self:plugin:install terminus-build-tools-plugin"
    exit 1;
  fi

  # Check for long-running workflows.
  echo "${yellow}Checking for long-running workflows.${normal}"
  if [[ "$(terminus workflow:wait --max=1 "${sitename}"."$siteenv")" == *"running"* ]]; then
    waittime=$(( waittime / 3 ))
    echo "${yellow}Workflow still running, waiting another ${waittime} seconds.${normal}"
    terminus workflow:wait --max="$waittime" "$sitename"."$siteenv"
  fi

  git pull --ff --commit
}

# Finish up the Sage install process.
function clean_up() {
  # List the app/themes directory.
  echo "${yellow}Checking the themes directory for ${sagename}.${normal}"
  # If the previous output did not include $sagename, bail.
  if [[ ! "$(ls -la web/app/themes)" == *"$sagename"* ]]; then
    echo "${red}Theme not found. Exiting here.${normal}"
    exit 1;
  fi


  # If the site is multisite, we'll need to enable the theme so we can activate it.
  echo "${yellow}Checking if this is a multisite.${normal}"
  if terminus wp -- "$sitename"."$siteenv" config is-true MULTISITE > /dev/null 2>&1; then
    echo "${yellow}Site is multisite.${normal}"
    terminus wp -- "$sitename"."$siteenv" theme enable "$sagename"
  fi

  # Get the themes.
  themelist=$(terminus wp -- "$sitename"."$siteenv" theme list --format=csv | tr -d '\n' | tr -d ' ')

  if ! echo "$themelist" | grep -q "$sagename"; then
    echo "${red}Theme $sagename not found in the theme list. Exiting here.${normal}"
    terminus wp -- "$sitename"."$siteenv" theme list
    echo "Check the theme list above. If the theme you created is not listed, it's possible that the deploy has not completed. You can try again in a few minutes using the following command:"
    echo "terminus wp -- $sitename.dev theme activate $sagename"
    echo "Once you do this, you will need to open the site to generate the requisite files and then commit them in SFTP mode."
    exit 1;
  fi

  # Activate the new theme
  echo "${yellow}Activating the ${sagename} theme.${normal}"
  terminus wp -- "$sitename"."$siteenv" theme activate "$sagename"

  # If this is a CI environment, stop here.
  if [ "$is_ci" == 1 ]; then
    echo "${yellow}CI detected. All done here.${normal} 🍵"
    exit 0;
  fi

  # Switch back to SFTP so files can be written.
  terminus connection:set "$sitename"."$siteenv" sftp

  # Open the site. This should generate requisite files on page load.
  echo "${yellow}Opening the ${siteenv}-${sitename}.pantheonsite.io to generate requisite files.${normal}"
  open https://"$siteenv"-"$sitename".pantheonsite.io

  # Commit any additions found in SFTP mode.
  echo "${yellow}Committing any files found in SFTP mode that were created by Sage.${normal}"
  terminus env:commit "$sitename"."$siteenv" --message="[Sage Install] Add any leftover files found in SFTP mode."

  # Switch back to Git.
  terminus connection:set "$sitename"."$siteenv" git
  git pull --ff --commit
}

# Install Sage theme.
function install_sage() {
  sitename="${SITENAME:-}"

  # Check if the user is logged into Terminus before trying to run other Terminus commands.
  check_login

  themedir="web/app/themes"
  siteinfo=$(terminus site:info "$sitename")
  id=$(get_field "ID" "$siteinfo")
  name=$(get_field "Name" "$siteinfo")

  get_info
  update_php
  install_sage_theme
  add_symlink
  update_composer
  clean_up
}

# Maybe create symlinks for wp files.
function maybe_create_symlinks() {
  cd web || exit 1
  echo "${yellow}Checking if we need to create symlinks...${normal}"
  # Loop through all the files in wp/* and create a symbolic link to it in the current working directory unless it's index.php or wp-settings.php but only if a link does not already exist in the current directory.
  for file in wp/*; do
    if [[ "$file" != "wp/index.php" && "$file" != "wp/wp-settings.php" && ! -L "${file##*/}" ]]; then
      ln -s "$file" "${file##*/}"
      printf "\e[D!\e[C"
    else
      printf "\e[D.\e[C"
    fi
  done
  printf "\e[D✅\e[C"
  echo ""

  check_index_php
  echo "${green}Done creating symlinks!${normal} 🔗"
}

main "$@"
