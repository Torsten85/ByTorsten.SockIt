<?php
namespace ByTorsten\SockIt\Domain\Model;

use Neos\Flow\Annotations as Flow;

use ByTorsten\SockIt\Controller\SocketController;
use ByTorsten\SockIt\Exception;
use ByTorsten\SockIt\WebSocketComponent;
use ByTorsten\SockIt\WebSocketServer;
use Guzzle\Http\Message\EntityEnclosingRequest;
use ByTorsten\SockIt\Service\RpcService;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Uri;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\AccountRepository;
use Neos\Flow\Session\Aspect\LazyLoadingAspect;
use Neos\Flow\Session\SessionManagerInterface;
use Ratchet\ConnectionInterface;
use Neos\Flow\Security\Context as SecurityContext;

class SocketConnection implements ConnectionInterface
{
    /**
     * @var AccountRepository
     * @Flow\Inject
     */
    protected $accountRepository;

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var ActionRequest
     */
    protected $actionRequest;


    /**
     * @Flow\InjectConfiguration(package="Neos.Flow", path="session.name")
     * @var string
     */
    protected $sessionName;

    /**
     * @Flow\Inject
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var RpcService
     */
    protected $rpcService;

    /**
     * @Flow\Inject
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var string
     */
    protected $connectionHash;

    /**
     * @var string
     */
    protected $componentHash;

    /**
     * @var array
     */
    protected $sessionObjects = [];

    /**
     * @var string
     */
    protected $sessionIdentifier;

    /**
     * @var bool
     */
    protected $sessionLoaded = false;

    /**
     * SocketConnection constructor.
     * @param ConnectionInterface $connection
     * @param WebSocketComponent $component
     */
    public function __construct(ConnectionInterface $connection, WebSocketComponent $component)
    {
        $this->connection = $connection;

        $this->connectionHash = \spl_object_hash($connection);
        $this->componentHash = \spl_object_hash($component);
    }

    /**
     *
     */
    public function initializeObject()
    {
        if ($this->connection && isset($this->connection->WebSocket) && $this->connection->WebSocket instanceof \StdClass) {
            /** @var EntityEnclosingRequest $webSocketRequest */
            $webSocketRequest = $this->connection->WebSocket->request;

            $httpRequest = Request::create(new Uri($webSocketRequest->getUrl()), $webSocketRequest->getMethod(), $webSocketRequest->getParams()->toArray());

            $httpHeaders = $httpRequest->getHeaders();
            foreach ($webSocketRequest->getHeaders()->toArray() as $name => $value) {
                $httpHeaders->set($name, $value);
            }

            $this->sessionIdentifier = $httpRequest->hasCookie($this->sessionName) ? $httpRequest->getCookie($this->sessionName)->getValue() : null;
            $this->actionRequest = new ActionRequest($httpRequest);
            $this->actionRequest->setControllerObjectName(SocketController::class);
        }

    }

    /**
     *
     */
    protected function loadSession()
    {
        if ($this->sessionLoaded) {
            return;
        }

        $this->sessionLoaded = true;

        if (!$this->sessionIdentifier) {
            return;
        }

        $session = $this->sessionManager->getSession($this->sessionIdentifier);

        if (!$session) {
            return;
        }

        $sessionObjects = $session->getData('Neos_Flow_Object_ObjectManager');

        foreach($sessionObjects as $sessionObject) {
            $objectName = $this->objectManager->getObjectNameByClassName(get_class($sessionObject));
            $this->sessionObjects[$objectName] = $sessionObject;
        }
    }

    /**
     * @param \Closure $closure
     * @throws Exception
     */
    public function withSessionObjectsLoaded(\Closure $closure)
    {
        if (!$this->rpcService->isInSocketContext()) {
            throw new Exception('withSessionObjectsLoaded can only be called on the socket side');
        }

        $this->loadSession();
        $lazyLoadingAspect = $this->objectManager->get(LazyLoadingAspect::class);
        $originalInstances = [];

        foreach($this->sessionObjects as $objectName => $sessionObject) {
            $originalInstances[$objectName] = $this->objectManager->get($objectName);

            $lazyLoadingAspect->registerSessionInstance($objectName, $sessionObject);
            $this->objectManager->setInstance($objectName, $sessionObject);

            if ($sessionObject instanceof SecurityContext) {
                $sessionObject->setRequest($this->actionRequest);
            }
        }

        $closure();

        foreach ($originalInstances as $objectName => $instance) {
            $this->objectManager->forgetInstance($objectName);
            $lazyLoadingAspect->registerSessionInstance($objectName, $instance);
        }
    }

    /**
     * @param string $data
     * @return ConnectionInterface
     */
    public function send($data)
    {
        if ($this->rpcService->isInSocketContext()) {
            return $this->connection->send($data);
        }

        $this->rpcService->request(
            WebSocketServer::class,
            'sendMessageToConnection',
            [
                $this->componentHash,
                $this->connectionHash,
                $data
            ]
        );
        return $this;
    }

    /**
     *
     */
    public function close()
    {
        if ($this->rpcService->isInSocketContext()) {
            return $this->connection->close();
        }

        $this->rpcService->request(
            WebSocketServer::class,
            'closeConnection',
            [
                $this->componentHash,
                $this->connectionHash
            ]
        );
        return $this;
    }

    /**
     * @return Account
     */
    public function getAccount()
    {
        $account = null;
        if ($this->rpcService->isInSocketContext()) {
            $this->withSessionObjectsLoaded(function () use (&$account) {
                $securityContext = $this->objectManager->get(SecurityContext::class);
                if ($securityContext->canBeInitialized()) {
                    $account = $securityContext->getAccount();
                }
            });
        } else {
            $accountIdentifier = $this->rpcService->request(
                WebSocketServer::class,
                'getAccountIdentifierFromConnection',
                [
                    $this->componentHash,
                    $this->connectionHash
                ]
            );

            if ($accountIdentifier) {
                $account = $this->accountRepository->findOneByAccountIdentifier($accountIdentifier);
            }
        }

        return $account;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return ['connectionHash', 'componentHash'];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('%s -> %s', $this->componentHash, $this->connectionHash);
    }
}