<?php
namespace ByTorsten\SockIt;

use ByTorsten\SockIt\Domain\Model\SocketConnection;
use ByTorsten\SockIt\Service\SocketServiceInterface;
use Neos\Flow\Utility\Algorithms;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class WebSocketComponent implements MessageComponentInterface
{
    /**
     * @var SocketServiceInterface
     */
    protected $service;

    /**
     * @var array
     */
    protected $connections;

    /**
     * @param SocketServiceInterface $socketService
     */
    public function __construct(SocketServiceInterface $socketService)
    {
        $this->service = $socketService;
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function onOpen(ConnectionInterface $connection)
    {
        $socketConnection = new SocketConnection($connection, $this);
        $connectionKey = \spl_object_hash($connection);
        $this->connections[$connectionKey] = $socketConnection;

        $socketConnection->withSessionObjectsLoaded(function () use ($socketConnection) {
            $this->service->onOpen($socketConnection);
        });
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function onClose(ConnectionInterface $connection)
    {
        $connectionKey = \spl_object_hash($connection);
        /** @var SocketConnection $socketConnection */
        $socketConnection = $this->connections[$connectionKey];
        unset($this->connections[$connectionKey]);

        $socketConnection->withSessionObjectsLoaded(function () use ($socketConnection) {
            $this->service->onClose($socketConnection);
        });
    }

    /**
     * @param ConnectionInterface $connection
     * @param \Exception $exception
     */
    function onError(ConnectionInterface $connection, \Exception $exception)
    {
        $connectionKey = \spl_object_hash($connection);
        /** @var SocketConnection $socketConnection */
        $socketConnection = $this->connections[$connectionKey];

        $socketConnection->withSessionObjectsLoaded(function () use ($socketConnection, $exception) {
            $this->service->onError($socketConnection, $exception);
        });
    }

    /**
     * @param ConnectionInterface $connection
     * @param string $message
     */
    function onMessage(ConnectionInterface $connection, $message)
    {
        $connectionKey = \spl_object_hash($connection);
        /** @var SocketConnection $socketConnection */
        $socketConnection = $this->connections[$connectionKey];

        $socketConnection->withSessionObjectsLoaded(function () use ($socketConnection, $message) {
            $this->service->onMessage($socketConnection, $message);
        });
    }

    /**
     * @param string $hash
     * @return SocketConnection
     */
    public function getConnectionByHash(string $hash): SocketConnection
    {
        return $this->connections[$hash];
    }
}