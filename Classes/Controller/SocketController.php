<?php
namespace ByTorsten\SockIt\Controller;

use Neos\Flow\Annotations as Flow;

use ByTorsten\SockIt\Exception;
use ByTorsten\SockIt\Service\RpcService;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class SocketController implements MessageComponentInterface
{

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var RpcService
     */
    protected $rpcService;

    /**
     * @param ConnectionInterface $connection
     */
    public function onOpen(ConnectionInterface $connection)
    {
        // do nothing
    }

    /**
     * @param ConnectionInterface $from
     * @param string $message
     * @throws Exception
     */
    public function onMessage(ConnectionInterface $from, $message)
    {
        $parsedMessage = unserialize((string) $message);

        $className = $parsedMessage['className'];
        $methodName = $parsedMessage['methodName'];
        $arguments = $parsedMessage['arguments'];

        $implementation = $this->objectManager->get($className);

        if (!($implementation && is_callable([$implementation, $methodName]))) {
            throw new Exception(sprintf('Cannot call %s->%s', $className, $methodName), 1494825119);
        }

        $result = call_user_func_array([$implementation, $methodName], $arguments);
        $from->send(serialize($result));
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function onClose(ConnectionInterface $connection)
    {
        // do nothing
    }

    /**
     * @param ConnectionInterface $connection
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        #\Neos\Flow\var_dump($e);
        $connection->close();
        throw $e;
    }
}