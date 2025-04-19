<?php
declare (strict_types = 1);

namespace Live\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Live\Server\Pusher;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use React\ZMQ\Context;
use ZMQ;

/**
 * Server command.
 */
class ServerCommand extends Command
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
        $loop   = Loop::get();
        $pusher = new Pusher;
        // Listen for the web server to make a ZeroMQ push after an ajax request
        $context = new Context($loop);
        $pull    = $context->getSocket(ZMQ::SOCKET_PULL, 'push');
        $pull->bind(Configure::read('Live.WS.internal', 'tcp://127.0.0.1:44442')); // Binding to 127.0.0.1 means the only client that can connect is itself
        $pull->on('message', [$pusher, 'onServerMessage']);

        // Set up our WebSocket server for clients wanting real-time updates
        // Binding to 0.0.0.0 means remotes can connect
        // WE USE 127.0.0.1 SINCE ITS TUNNELED THROUGH PORT 443 (APACHE WS_TUNNEL)

        $webSocket = new SocketServer(Configure::read('Live.WS.external', '127.0.0.1:44443'));
        new IoServer(
            new HttpServer(
                new WsServer(
                    $pusher
                )
            ),
            $webSocket
        );

        $loop->run();
    }

}
