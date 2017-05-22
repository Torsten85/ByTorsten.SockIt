<?php
namespace ByTorsten\SockIt\Service\Aspect;

use Neos\Flow\Annotations as Flow;

use ByTorsten\SockIt\Service\RpcService;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Aspect
 */
class RpcAspect
{
    /**
     * @Flow\Inject
     * @var RpcService
     */
    protected $rpcService;

    /**
     * @Flow\Pointcut("within(ByTorsten\SockIt\Service\AbstractSocketService) && method(public .*->(?!on).*()) && !method(.*->__construct())")
     */
    public function rpcMethods() {}

    /**
     * @param JoinPointInterface $joinPoint
     * @Flow\Around("ByTorsten\SockIt\Service\Aspect\RpcAspect->rpcMethods")
     * @return mixed
     */
    public function redirectToRpc(JoinPointInterface $joinPoint)
    {
        if ($this->rpcService->isInSocketContext()) {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        return $this->rpcService->request($joinPoint->getClassName(), $joinPoint->getMethodName(), $joinPoint->getMethodArguments());
    }
}