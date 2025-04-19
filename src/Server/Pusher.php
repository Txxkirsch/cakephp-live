<?php
namespace Live\Server;

use Cake\Core\Configure;
use Exception;
use GuzzleHttp\Psr7\Header;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

/**
 * Pusher
 * Service has to be restarted after changes here
 */
class Pusher implements MessageComponentInterface
{
    /**
     * @var mixed
     */
    protected $_clients;

    public function __construct()
    {
        $this->clients = new SplObjectStorage;
    }

    public function onServerMessage($msg)
    {
        echo "Got Server-Message:" . PHP_EOL;
        $msg = json_decode($msg);
        pr($msg);
        $this->publish($msg->topic, $msg->message, $msg->resourceId);
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $cookiesRaw = $conn->httpRequest->getHeader('Cookie');

        if (count($cookiesRaw)) {
            $cookiesArr = Header::parse($cookiesRaw)[0]; // Array of cookies
            $sessionId  = $cookiesArr[Configure::read('Session.ini')['session.name'] ?? session_name()];
        }
        // Store the new connection to send messages to later
        $this->clients->attach($conn, [
            'sessionId'     => $sessionId,
            'subscriptions' => [],
        ]);
        echo "New connection! ({$conn->resourceId}, {$sessionId})" . PHP_EOL;
        $conn->send(json_encode(['type' => 'identifier', 'message' => ['resourceId' => $conn->resourceId, 'sessionId' => $sessionId]]));
    }

    public function onSubscribe(ConnectionInterface $conn, string $topic)
    {
        $info                          = $this->clients->offsetGet($conn);
        $info['subscriptions'][$topic] = time();

        $this->clients->offsetSet($conn, $info);
        return true;
    }

    public function onUnsubscribe(ConnectionInterface $conn, string $topic)
    {
        $info = $this->clients->offsetGet($conn);
        if ($this->_isSubscribed($conn, $topic)) {
            unset($info['subscriptions'][$topic]);
            $this->clients->offsetSet($conn, $info);
            return true;
        }
        return false;
    }

    protected function _isSubscribed(ConnectionInterface $conn, $topic)
    {
        $info = $this->clients->offsetGet($conn);
        return !empty($info['subscriptions'][$topic]);
    }

    public function publish(string $topic, $msg, $conn = null)
    {
        $sendTo = 0;
        foreach ($this->clients as $client) {
            // The sender is not the receiver, send to each client connected
            $resourceId = null;
            if (is_object($conn)) {
                $resourceId = $conn->resourceId;
            } else {
                $resourceId = $conn;
            }
            $receiverIsSender = $client->resourceId == $resourceId;
            if ($receiverIsSender || !$this->_isSubscribed($client, $topic)) {
                continue;
            }
            $info    = $this->clients->getInfo();
            $message = [
                'from'    => [
                    'resourceId' => $resourceId,
                    'sessionId'  => $info['sessionId'],
                ],
                'topic'   => $topic,
                'message' => is_array($msg) ? $msg : [$msg],
            ];
            $client->send(json_encode($message));
            ++$sendTo;
        }
        return $sendTo;
    }

    public function onPublish(ConnectionInterface $conn, string $topic, $msg)
    {
        if ($this->_isSubscribed($conn, $topic)) {
            return $this->publish($topic, $msg, $conn);
        }
        return false;
    }

    /**
     * @param ConnectionInterface $conn
     * @param $msg
     */
    public function onMessage(ConnectionInterface $conn, $msg)
    {
        $this->_handleMessage($conn, $msg);
    }

    protected function _handleMessage(ConnectionInterface $conn, string $msgStr)
    {
        $isJson = (function ($msg) {
            return json_decode($msg) !== null;
        })($msgStr);

        if ($isJson) {
            $msg = json_decode($msgStr);

            if ($msg->type === 'subscribe') {
                $sub = $this->onSubscribe($conn, $msg->topic);
                if ($sub) {
                    $info = $this->clients->offsetGet($conn);
                    echo "{$conn->resourceId} subscribed to {$msg->topic}" . PHP_EOL;
                    echo "Current subscriptions: [" . implode(', ', array_keys($info['subscriptions'])) . "]" . PHP_EOL;
                }
                return $sub;
            } elseif ($msg->type === 'unsubscribe') {
                $unsub = $this->onUnsubscribe($conn, $msg->topic);
                if ($unsub) {
                    $info = $this->clients->offsetGet($conn);
                    echo "{$conn->resourceId} unsubscribed from {$msg->topic}" . PHP_EOL;
                    echo "Remaining subscriptions: [" . implode(', ', array_keys($info['subscriptions'])) . "]" . PHP_EOL;
                }
                return $unsub;
            } elseif ($msg->type === 'publish') {
                $pub = $this->onPublish($conn, $msg->topic, $msg->message);
                if ($pub !== false) {
                    echo "Published: {$msgStr} to $pub Clients" . PHP_EOL;
                }
                return $pub;
            }
        }
        echo "Got non-JSON message from {$conn->resourceId}" . PHP_EOL;
        return $msgStr;
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected" . PHP_EOL;
    }

    /**
     * @param ConnectionInterface $conn
     * @param Exception $e
     */
    public function onError(ConnectionInterface $conn, Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}" . PHP_EOL;
        $conn->close();
    }
}
