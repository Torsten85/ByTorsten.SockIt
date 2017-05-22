<?php
namespace ByTorsten\SockIt\Command;

use ByTorsten\SockIt\Controller\SocketController;
use ByTorsten\SockIt\WebSocketComponent;
use Neos\Flow\Annotations as Flow;

use ByTorsten\SockIt\Service\RpcService;
use ByTorsten\SockIt\WebSocketServer;
use ByTorsten\Test\Service\ChatService;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Session\SessionManagerInterface;
use Neos\Flow\Session\TransientSession;
use Neos\Utility\ObjectAccess;

class SocketServerCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var RpcService
     */
    protected $rpcService;

    /**
     * @Flow\Inject
     * @var WebSocketServer
     */
    protected $socketServer;

    /**
     * Runs the socket server(s)
     */
    public function runCommand()
    {
        $transientSession = new TransientSession();
        $transientSession->start();
        $sessionManager = $this->objectManager->get(SessionManagerInterface::class);
        ObjectAccess::setProperty($sessionManager, 'currentSession', $transientSession, true);

        $this->rpcService->setSocketContext(true);
        $this->socketServer->route('/_rpc', new SocketController(), ['127.0.0.1']);

        /** @var ChatService $chatService */
        $chatService = $this->objectManager->get(ChatService::class);
        $chatServiceComponent = new WebSocketComponent($chatService);
        $this->socketServer->route('/', $chatServiceComponent, ['*']);

        $this->outputFormatted('Server running at <info>%s</info>', [$this->socketServer->getServerAddress()]);
        $this->socketServer->run();
    }
}