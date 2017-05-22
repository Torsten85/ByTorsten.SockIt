<?php
namespace ByTorsten\SockIt\Service;

use ByTorsten\SockIt\Domain\Model\SocketConnection;

interface SocketServiceInterface
{
    /**
     * @param SocketConnection $connection
     */
    function onOpen(SocketConnection $connection);

    /**
     * @param SocketConnection $connection
     */
    function onClose(SocketConnection $connection);

    /**
     * @param SocketConnection $connection
     * @param string $message
     */
    function onMessage(SocketConnection $connection, string $message);

    /**
     * @param SocketConnection $connection
     * @param \Exception $exception
     */
    function onError(SocketConnection $connection, \Exception $exception);
}