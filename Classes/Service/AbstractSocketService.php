<?php
namespace ByTorsten\SockIt\Service;

use Neos\Flow\Annotations as Flow;

use ByTorsten\SockIt\Domain\Model\SocketConnection;

/**
 * @Flow\Scope("singleton")
 */
abstract class AbstractSocketService implements SocketServiceInterface
{
    /**
     * @param SocketConnection $connection
     */
    public function onOpen(SocketConnection $connection)
    {
    }

    /**
     * @param SocketConnection $connection
     */
    public function onClose(SocketConnection $connection)
    {
    }

    /**
     * @param SocketConnection $connection
     * @param string $message
     */
    abstract public function onMessage(SocketConnection $connection, string $message);

    /**
     * @param SocketConnection $connection
     * @param \Exception $exception
     */
    public function onError(SocketConnection $connection, \Exception $exception)
    {
        $connection->close();
        throw $exception;
    }
}