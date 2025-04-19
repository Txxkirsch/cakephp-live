<?php
declare (strict_types = 1);

namespace Live\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;

/**
 * InstallService command.
 */
class InstallServiceCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addArgument('user', [
            'help' => 'User for the Service. Default: www-data',
        ]);
        $parser->addArgument('group', [
            'help' => 'Group for the Service. Default: www-data',
        ]);
        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $path = ROOT . DS . 'bin' . DS . 'cake live.server';

        $servicePath      = '/etc/systemd/system/';
        $serviceNameShort = Configure::read('App.name') . '-ws';
        $serviceName      = $serviceNameShort . '.service';

        $output = "[Unit]
		Description=" . Configure::read('App.name') . " Websocket-Server
		After=network.target

		[Service]
		Type=simple
		Restart=always
		User=www-data
		Group=www-data
		ExecStart={$path}

		[Install]
		WantedBy=multi-user.target\n";

        $installQ = str_replace("\t", '', "Write:
		-------------
		{$output}
		-------------
		into: {$servicePath}{$serviceName} ?:");
        $installQ = $io->askChoice($installQ,
            ['Y', 'n'], 'Y');

        if ($installQ !== 'Y') {
            $io->out('abort!');
            return 0;
        }

        $write = @file_put_contents($servicePath . $serviceName, str_replace("\t", '', $output));

        if ($write) {
            $io->out('Service installed');
            $io->out("You need to run:\nsudo systemctl enable {$serviceName}\nsudo service {$serviceNameShort} start");
            return 0;
        }
        $io->error('Error installing service. Are you root/sudo?');
        return 1;
    }

}
