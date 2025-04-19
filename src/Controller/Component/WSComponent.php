<?php
namespace Live\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use ZMQ;
use ZMQContext;

/**
 * Websocket component
 */
class WSComponent extends Component
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $_defaultConfig = [];

    /**
     *
     * @var int|null
     */
    protected int|null $_currentConnectionId = null;

    protected string $_defaultTopic = 'default';
    /**
     * @param array $config
     */
    public function initialize(array $config): void
    {
        if (!empty($config['_Controller'])) {
            $request                    = $config['_Controller']->getRequest();
            $this->_currentConnectionId = $request->getHeader('X-WS-ID')[0] ?? null;
            $this->_defaultTopic        = $config['_topic'];
        }
    }

    /**
     * @param ComponentRegistry $registry
     * @param array $config
     */
    public function __construct($registry = null, array $config = [])
    {
        parent::__construct($registry, $config);
    }

    /**
     * @param $msg
     * @return mixed
     */
    public function emit($msg, $topic = null)
    {
        $context = (new ZMQContext())->getSocket(ZMQ::SOCKET_PUSH);
        $context->connect(Configure::read('Live.WS.internal'));
        if (!empty($this->_currentConnectionId ?? 1)) {
            $msg = [
                'message'    => $msg,
                'topic'      => $topic ?? $this->_defaultTopic,
                'resourceId' => $this->_currentConnectionId ?? null,
            ];
            return $context->send(json_encode($msg));
        }
        return false;
    }

}
