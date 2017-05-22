<?php
namespace ByTorsten\SockIt;

use Neos\Flow\Annotations as Flow;

use Ratchet\ComponentInterface;
use Ratchet\Http\HttpServerInterface;
use Ratchet\Http\OriginCheck;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\WampServer;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as SocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * @Flow\Scope("singleton")
 */
class WebSocketServer
{
    /**
     * @var RouteCollection
     */
    public $routes;


    /**
     * @var \Ratchet\Server\IoServer
     */
    protected $server;

    /**
     * @var string
     */
    protected $httpHost;

    /***
     * @var int
     */
    protected $port;

    /**
     * @var int
     */
    protected $routeCount = 0;

    /**
     * @var array
     */
    protected $webSocketComponents = [];

    /**
     * WebSocketServer constructor.
     * @param string $address
     * @param int $port
     */
    public function __construct(string $address = '0.0.0.0', int $port = 8080)
    {

        $loop = LoopFactory::create();
        $this->port = $port;

        $socket = new SocketServer($loop);
        $socket->listen($port, $address);

        $this->routes  = new RouteCollection;
        $this->server = new IoServer(new HttpServer(new Router(new UrlMatcher($this->routes, new RequestContext))), $socket, $loop);
    }

    /**
     * @param $path
     * @param ComponentInterface $controller
     * @param array $allowedOrigins
     * @return ComponentInterface|HttpServerInterface|OriginCheck|WsServer
     */
    public function route($path, ComponentInterface $controller, array $allowedOrigins = [])
    {
        if ($controller instanceof WebSocketComponent) {
            $this->webSocketComponents[\spl_object_hash($controller)] = $controller;
        }

        if ($controller instanceof HttpServerInterface || $controller instanceof WsServer) {
            $decorated = $controller;
        } elseif ($controller instanceof WampServerInterface) {
            $decorated = new WsServer(new WampServer($controller));
        } elseif ($controller instanceof MessageComponentInterface) {
            $decorated = new WsServer($controller);
        } else {
            $decorated = $controller;
        }

        $allowedOrigins = array_values($allowedOrigins);
        if ('*' !== $allowedOrigins[0]) {
            $decorated = new OriginCheck($decorated, $allowedOrigins);
        }

        $route = new Route($path, ['_controller' => $decorated], [], [], '', [], ['GET']);
        $this->routes->add('rr-' . ++$this->routeCount, $route);

        return $decorated;
    }

    /**
     * Run the server by entering the event loop
     */
    public function run()
    {
        $this->server->run();
    }

    /**
     * @return mixed
     */
    public function getServerAddress()
    {
        /** @var SocketServer $socket */
        $socket = $this->server->socket;
        return stream_socket_get_name($socket->master, false);
    }

    /**
     * @param string $componentHash
     * @param string $connectionHash
     * @param string $data
     */
    public function sendMessageToConnection(string $componentHash, string $connectionHash, string $data)
    {
        /** @var WebSocketComponent $component */
        $component = $this->webSocketComponents[$componentHash];
        $connection = $component->getConnectionByHash($connectionHash);

        $connection->send($data);
    }

    /**
     * @param string $componentHash
     * @param string $connectionHash
     * @return string
     */
    public function getAccountIdentifierFromConnection(string $componentHash, string $connectionHash)
    {
        /** @var WebSocketComponent $component */
        $component = $this->webSocketComponents[$componentHash];
        $connection = $component->getConnectionByHash($connectionHash);

        $account = $connection->getAccount();

        if ($account) {
            return $account->getAccountIdentifier();
        }

        return null;
    }

    /**
     * @param string $componentHash
     * @param string $connectionHash
     */
    public function closeConnection(string $componentHash, string $connectionHash)
    {
        /** @var WebSocketComponent $component */
        $component = $this->webSocketComponents[$componentHash];
        $connection = $component->getConnectionByHash($connectionHash);

        $connection->close();
    }
}