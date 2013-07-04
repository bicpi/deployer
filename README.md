# Deployer

**The Deployer command line tool allows to synchronize a local with a remote directory.**

A simple Yaml configuration file eases the complexity of handling connection data to
different remote servers by providing support for

* **multiple targets** - e.g. development, stage or production servers
* **excluding special files or directories** - e.g. cache, logs, configuration files
* **command hooks** - e.g. to clear a cache or rebuilding search indices after deployment

The configuration allows to merge default settings with specific target settings to avoid
redundancy.

Using the Deployer is dead simple:

    $ deployer <TARGET-NAME>

The target name defines the configuration to be used, e.g. for a "dev", "stage" or "prod"
environment. The detailed target data is read from the configuration file `.deployer.yml`
in the local source directory.

The current directory will be used as the local source directory - it may be changed by
the `--source-dir=<SOURCE-DIR>` parameter.

The deployment process then starts with a dry run (preview). You'll see a detailed list
of what will happen during the deployment process.

The command runs in dry mode by default. If you're ready to do the real sync, just add the
`--force` parameter, e.g.:

    $ deployer stage --force

This will roll out the real deployment!

## 1) Installation

### Requirements

You'll need the following software installed on your server:

* OS: *nix
* PHP > 5.3.2
* rsync
* ssh

For the recommended way of installation you may also need to install:

* git
* wget

Deployer depends on some Symfony components. All dependencies are usually resolved and installed by
[Composer](http://getcomposer.org) as you'll see below.

### Clone the Deployer git repository

Run the following commands:

    $ cd /install/dir
    $ git clone http://github.com/bicpi/deployer.git
    $ cd deployer
    $ wget http://getcomposer.org/composer.phar
    $ php composer.phar install

Deployer is now installed on your system.

### Create symbolic link in your ~/bin directory

If you want to use the `deployer` command without specifing the whole path to the installation
directory over an over again just create a symbolic link to it in a directory which is included
in your PATH environment variable, e.g.:

    $ cd ~/bin
    $ ln -s /path/to/deployer/bin/deployer deployer

Now you're able to call the `deployer` command from everywhere on your system.

## 2) Configuration

Before the first deployment of a project or directory you have to save the configuration into a
file called `.deployer.yml` inside the root of your local source directory. It defines the target
server(s), exclude files and command hooks. Please note that only a hostname alias is used in the Deployer
configuration. You need to setup a key based SSH authentication using the `~/.ssh/config` file
([tutorial](http://nerderati.com/2011/03/simplify-your-life-with-an-ssh-config-file))) so that Deployer
can safely connect to your remote server(s).

Here is a sample configuration:

    default: # Configuration used by **ALL targets**
        target: # Details about the remote host
            host: ALIAS # Hostname alias according to your ~/.ssh/config file
        commands: # Commands that may be hooked into the deployment process
            pre_deploy: # Array of commands to be executed before the deployment on the local server
                - app/console assetic:dump --env=prod --no-debug
            post_deploy: # Array of commands to be executed after the deployment on the remote server
                - app/console cache:clear
                - ...
        excludes: | # Files to be excluded from the deployment
            .deployer.yml
            .gitignore
            .gitkeep
            /build.xml
            /cache/*
            /logs/*
    dev: # Aditional configuration for the **dev** target only
        target:
            dir: /path/to/dev/project/dir/on/remote/host
        excludes: |
            /web/front_stage.php
            /web/front.php
    stage: # Aditional configuration for the **stage** target only
        target:
            dir: /path/to/stage/project/dir/on/remote/host
        excludes: |
            /web/config.php
            /web/front_dev.php
            /web/front.php
    prod: # Aditional configuration for the **prod** target only
        target:
            dir: /path/to/prod/project/dir/on/remote/host
        excludes: |
            /web/config.php
            /web/front_*.php

The custom target configuration will be merged with the default configuration. All custom target settings
will either take precedence over the default settings (`target|host`, `target|dir`) or will be added to
the default settings (`commands|post_commit`, `excludes`). Thus, you'll have a minimum effort to write your
configuration and there are no redundancies.

## 3) Deploy

The usual way of deployment is to execute Deployer from within the local source directory

    $ cd /path/to/local/project/directory

After setting up the `.deployer.yml` in this directory (before first deployment only) you just need to
execute the `deployer` command with a valid target name to start the above described deployment process:

    $ deployer stage
