<?php
namespace ByTorsten\SockIt\Service;

use Neos\Flow\Annotations as Flow;
use Ratchet\Client\WebSocket;
use React\EventLoop\Factory as LoopFactory;
use Ratchet\Client\Connector;

/**
 * @Flow\scope("singleton")
 */
class RpcService
{
    const RPC_ENDPOINT = 'ws://127.0.0.1:8080/_rpc';

    /**
     * @var bool
     */
    protected $socketContext = false;

    /**
     * @return bool
     */
    public function isInSocketContext()
    {
        return $this->socketContext;
    }

    /**
     * @param bool $socketContext
     */
    public function setSocketContext(bool $socketContext)
    {
        $this->socketContext = $socketContext;
    }

    /**
     * @param \Closure $closure
     */
    public function inSocketContext(\Closure $closure)
    {
        $this->socketContext = true;
        $closure();
        $this->socketContext = false;
    }

    /**
     * @param string $className
     * @param string $methodName
     * @param array $arguments
     * @return mixed
     * @throws \Throwable
     */
    public function request(string $className, string $methodName, array $arguments = [])
    {
        $loop = LoopFactory::create();
        $result = null;
        $exception = null;

        $connector = new Connector($loop);
        $connector(self::RPC_ENDPOINT)->then(function (WebSocket $socket) use ($loop, $className, $methodName, $arguments, &$result) {
            $socket->on('message', function ($message) use ($loop, &$result) {
                $result = unserialize((string) $message);
                $loop->stop();
            });

            $socket->send(serialize([
                'className' => $className,
                'methodName' => $methodName,
                'arguments' => $arguments
            ]));

        }, function ($e) use ($loop, &$exception) {
            $loop->stop();
            $exception = $e;
        });
        $loop->run();

        if ($exception instanceof \Throwable) {
            throw $exception;
        }

        return $result;
    }
}