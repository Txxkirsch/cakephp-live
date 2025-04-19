<?php
declare (strict_types = 1);

namespace Live\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use ZMQ;
use ZMQContext;

/**
 * Push command.
 */
class PushCommand extends Command
{
    protected $_defaultMessages = [
        'update',
    ];

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
        $parser->addArgument('message', [
            'required' => true,
            'help'     => 'Message to send. JSON-encoded with escaped " (e.g. "{\\"type\\":\\"test\\"}")',
        ]);

        $parser->addArgument('topic', [
            'required' => false,
            'help'     => 'Topic for the Message. Defaults to Configure::read("WS.defaultTopic") if not set.',
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
        $context = new ZMQContext();
        $socket  = $context->getSocket(ZMQ::SOCKET_PUSH, 'push');
        $socket->connect(Configure::read('Live.WS.internal', 'tcp://127.0.0.1:44442')); //has tp be put in config

        $message = $args->getArgument('message');
        if (substr($message, 0, 1) === '{') {
            $message = json_decode($message);
        } elseif (in_array($message, $this->_defaultMessages)) {
            $message = $this->$message();
        } else {
            $io->warning('Invalid Message!');
            return 0;
        }
        $message = [
            'message'    => $message,
            'resourceId' => 1,
            'topic'      => $args->getArgument('topic') ?? Configure::read('Live.WS.defaultTopic'),
        ];

        $io->out("sending:");
        $io->out(json_encode($message, JSON_PRETTY_PRINT));
        $socket->send(json_encode($message));
    }

    public function update()
    {
        return [
            'type'   => 'noty',
            'config' => [
                'type'    => 'info',
                'text'    => 'Update available. Please reload the page (Ctrl + F5)',
                'timeout' => 0,
            ],
        ];
    }
}
