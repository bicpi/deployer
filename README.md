Deployer
========

To make a long story short
--------------------------

The Deployer tool reduces the complexity of a deployment from a local directory to the directory
on a remote server to a simple command line call:

    $ cd /path/to/local/project/dir
    $ /path/to/deployer/bin/deployer

You're done.


*The long story*
----------------

1) Installation
---------------

### Requirements

* PHP > 5.3.2

### Clone the git repository

Run the following commands:

    $ cd /install/dir
    $ git clone http://github.com/bicpi/deployer.git
    $ cd deployer
    $ wget http://getcomposer.org/composer.phar
    $ php composer.phar install

### Add deployer to your PATH

If you want to use the `deployer` command without specifing the whole path to the installation
directory over an over again just add it to your PATH environment variable:

    $ PATH=$PATH":/path/to/deployer/bin/deployer"
    $ export PATH

To persist this setting add the line to your `~./.profile` file.

Now you can use the `deployer` command from everywhere on your system.

2) Configuration
----------------

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