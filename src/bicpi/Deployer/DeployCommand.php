<?php
namespace bicpi\Deployer;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Process\Process;
use bicpi\Deployer\AbortException;

class DeployCommand extends Command
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var DialogHelper
     */
    protected $dialog;

    /**
     * @var string
     */
    protected $sourceDir;

    /**
     * @var string
     */
    protected $targetKey;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $excludesFilepath = null;

    protected function configure()
    {
        $this
            ->setName('deploy')
            ->setDescription('Deploy an application via rsync')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->dialog = $this->getHelperSet()->get('dialog');
        $this->sourceDir = $this->dialog->ask($this->output, "<question>Please enter the source directory: [.]</question>", '.');
        $this->targetKey = $this->dialog->ask($this->output, "<question>Please enter the target name: [dev]</question>", 'dev');

        try {
            $this->abortUnless('root' != $this->process('whoami'), "Don't call this command as root on local machine");
            $this->abortUnless(file_exists($this->sourceDir), 'Source directory does not exist: '.$this->sourceDir);
            $this->sourceDir = realpath($this->sourceDir);
            $this->parseConfig();
            $cmd = sprintf('ssh %s "whoami"', $this->config['host']);
            $this->abortUnless('root' != $this->process($cmd), "Don't call this command as root on remote machine");
            $this->deploy();
        } catch (AbortException $e) {
            $output->writeln($e->getMessage());
        }
    }

    protected function deploy()
    {
        $output = $this->output;
        $writeLn = function ($msg, $isDry) use ($output) {
            $prefix = $isDry ? '!DRY RUN > ' : '> ';
            $output->writeLn($prefix.$msg);
        };
        foreach (array(true, false) as $isDry) {
            $writeLn("Starting deployment from '{$this->sourceDir}' to '{$this->config['host']}:{$this->config['dir']}'", $isDry);
            if (!$isDry) {
                if (!$this->dialog->askConfirmation($this->output, '<question>Sure to start real deployment?</question>', false)) {
                    $this->abort('Deployment aborted. No changes applied.');
                }
                $writeLn('Go for it!', $isDry);
            }
            $writeLn('Start syncing', $isDry);
            $this->sync($this->sourceDir, $this->config['host'], $this->config['dir'], $this->getExcludesFilepath(), $isDry);
            $writeLn('Syncing done', $isDry);
            if (array_key_exists('post_deploy', $this->config['commands'])) {
                $remoteCmds = (array) $this->config['commands']['post_deploy'];
                $writeLn(count($remoteCmds).' post deploy commands found', $isDry);
                foreach ($remoteCmds as $remoteCmd) {
                    $writeLn('Executing post deploy command: '.$remoteCmd, $isDry);
                    if ($isDry) {
                        $writeLn('Dummy executing: '.$remoteCmd, $writeLn);
                    } else {
                        $cmd = sprintf('ssh %s "cd %s && %s"', $this->config['host'], $this->config['dir'], $remoteCmd);
                        $this->process($cmd, true);
                    }
                }
            } else {
                $writeLn('No post deploy commands found', $isDry);
            }
            $writeLn('Deployment finished', $isDry);
        }
    }

    protected function parseConfig()
    {
        $configPath = $this->sourceDir.'/.deployer.yml';
        $this->abortUnless(file_exists($configPath), 'Config file not found: '.$configPath);

        $config = Yaml::parse($configPath);
        $this->abortUnless(
            array_key_exists('default', $config),
            "Config error: No default settings defined"
        );

        $settings = (array) $config['default'];
        if (array_key_exists($this->targetKey, $config)) {
            $settings = array_merge_recursive($settings, (array) $config[$this->targetKey]);
        }

        $this->abortUnless(
            array_key_exists('target', $settings),
            "Config error: No target defined"
        );
        $target = $settings['target'];
        $this->abortUnless(
            array_key_exists('host', $target),
            "Config error: No target host defined"
        );
        $hosts = (array) $target['host'];
        $host = array_pop($hosts);
        $this->abortUnless(
            array_key_exists('dir', $target),
            "Config error: No target dir defined"
        );
        $dirs = (array) $target['dir'];
        $dir = array_pop($dirs);

        $this->abortUnless(
            array_key_exists('commands', $settings),
            "Config error: No commands defined"
        );
        $commands = (array)$settings['commands'];

        $this->abortUnless(
            array_key_exists('excludes', $settings),
            "Config error: No excludes defined"
        );
        $excludes = implode("\n", (array)$settings['excludes']);

        $this->config = array(
            'host' => $host,
            'dir' => $dir,
            'commands' => $commands,
            'excludes' => $excludes
        );

        file_put_contents($this->getExcludesFilepath(), $this->config['excludes']);
    }

    protected function abort($msg)
    {
        throw new AbortException($msg);
    }

    protected function abortUnless($condition, $msg)
    {
        $condition || $this->abort($msg);
    }

    protected function process($cmd, $flush = false)
    {
        $output = $this->output;
        $callback = function ($type, $buffer) use ($output) {
            if ('err' === $type) {
                $output->writeln('ERR > '.$buffer);
            } else {
                $output->writeln($buffer);
            }
        };

        $process = new Process($cmd);
        $process->run($flush ? $callback : null);
        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            $msg = sprintf('Process failed: %s [%s]', $cmd, $error ?: 'No error details available');
            $this->abort($msg);
        }

        // trim this to remove new line at the end!
        return trim($process->getOutput());
    }

    protected function sync($source, $host, $targetDir, $excludePath, $dry = true)
    {
        $cmd = sprintf('if ssh %s "test -d %s"; then echo 1; else echo 0; fi', $host, $targetDir);
        $this->abortUnless((bool)$this->process($cmd), "Target directory '{$targetDir}' does not exist on host '$host'");

        $cmd = sprintf(
            '%s%s -azC --force --delete --progress --exclude-from=%s -e ssh %s/* %s:%s',
            $this->process('which rsync'),
            $dry ? ' --dry-run' : '',
            $excludePath,
            $source,
            $host,
            $targetDir
        );
        $this->process($cmd, true);
    }

    public function getExcludesFilepath()
    {
        if (null === $this->excludesFilepath) {
            $meta = stream_get_meta_data(tmpfile());
            $this->excludesFilepath = $meta['uri'];
        }

        return $this->excludesFilepath;
    }

    public function __destruct()
    {
        file_exists($this->excludesFilepath) && @unlink($this->excludesFilepath);
    }
}
