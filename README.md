# Deployer

The Deployer tool reduces the complexity of a deployment from a local directory to the directory
on a remote server to a simple command line call:

    $ cd /path/to/local/project/dir
    $ deployer

You'll be guided through a short interactive process with a preview (dry run) what will be synced
and if you confirm you deployment will roll out.

## 1) Installation

### Requirements

You'll need the following software installed on your server:

* PHP > 5.3.2
* rsync
* ssh

For the recommended way of installation you may also need
* git
* wget

Deployer depends on some third party software. All dependencies are usally resolved and installed by Composer.

### Clone the git repository

Run the following commands:

    $ cd /install/dir
    $ git clone http://github.com/bicpi/deployer.git
    $ cd deployer
    $ wget http://getcomposer.org/composer.phar
    $ php composer.phar install

### Create symbolic link in your ~/bin directory

If you want to use the `deployer` command without specifing the whole path to the installation
directory over an over again just create a symbolic link to it in a directory of your PATH
environment variable, e.g.:

    $ cd ~/bin
    $ ln -s /path/to/deployer/bin/deployer deployer

Now you're able to call the `deployer` command from everywhere on your system.

## 2) Configuration

Before the first deployment you have to save the configuration into a file called `.deployer.yml`
inside the root of your project directory. Here is a sample configuration:

    default: # Configuration use for all targets
        target: # Details about the remote host
            host: HOSTNAME # Hostname according to your ~/.ssh/config file
        commands: # Commands that may be hooked into the deployment process
            post_deploy: # Array of commands to be executed after the deployment on the remote server
                - ...
                - ...
        excludes: | # File to be excluded from the deployment
            .deployer.yml
            .gitignore
            .gitkeep
            /build.xml
            /cache/*
            /logs/*
    dev: # Aditional configuration for the 'dev' target
        target:
            dir: /path/to/dev/project/dir/on/remote/host
        excludes: |
            /web/front_stage.php
            /web/front.php
    stage: # Aditional configuration for the 'stage' target
        target:
            dir: /path/to/stage/project/dir/on/remote/host
        excludes: |
            /web/config.php
            /web/front_dev.php
            /web/front.php
    prod: # Aditional configuration for the 'prod' target
        target:
            dir: /path/to/prod/project/dir/on/remote/host
        excludes: |
            /web/config.php
            /web/front_*.php

The custom target configuration will be merged with the default configuration. All custom target setting
will either take precedence over the default settings (`target|host`, `target|dir`) or will be added to
the default setting (`commands|post_commit`, `excludes`). Thus, you'll have a minimum effort to write your
configuration and there are no redundancies.

## 3) Deploy

The usual way of deployment is to cd into the project directory you want to deploy. After setting up the
`.deployer.yml` (on first deployment only) you just need to execute `deployer`. By default, Deployer
assumes that you want to sync the current directory to the `dev` target. You may change these defaults
(e.g for a productive deployment) during the interactive deployment process. A dry run then checks and displays
what needs to be synced with the remote directory. All configured post deploy hooks (optional) are displayed, too.
Deployer the asks for a final confirmation to do the actual deployment.