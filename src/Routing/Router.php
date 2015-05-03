<?php

namespace WellRESTed\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use WellRESTed\HttpExceptions\HttpException;
use WellRESTed\Message\Response;
use WellRESTed\Message\ServerRequest;
use WellRESTed\Message\Stream;
use WellRESTed\Routing\ResponsePrep\ContentLengthPrep;
use WellRESTed\Routing\ResponsePrep\HeadPrep;
use WellRESTed\Routing\Route\RouteFactory;
use WellRESTed\Routing\Route\RouteFactoryInterface;

class Router implements MiddlewareInterface
{
    /** @var DispatcherInterface  */
    protected $dispatcher;

    /** @var  MiddlewareInterface[] List of middleware to dispatch immediatly before outputting the response */
    protected $responsePreparationHooks;

    /** @var MiddlewareInterface[] List of middleware to dispatch before the router evaluates the route. */
    private $preRouteHooks;

    /** @var MiddlewareInterface[] List of middleware to dispatch after the router dispatches all other middleware */
    private $postRouteHooks;

    /** @var array Hash array of status code => middleware */
    private $statusHandlers;

    /** @var RouteTable Collection of routes */
    private $routeTable;

    /** @var RouteFactoryInterface */
    private $routeFactory;

    // ------------------------------------------------------------------------

    public function __construct()
    {
        $this->responsePreparationHooks = $this->getResponsePreparationHooks();
        $this->routeFactory = $this->getRouteFactory();
        $this->routeTable = $this->getRouteTable();
        $this->statusHandlers = [];
    }

    // ------------------------------------------------------------------------

    /**
     * Create and return a route given a string path, a handler, and optional
     * extra arguments.
     *
     * The method will determine the most appropriate route subclass to use
     * and will forward the arguments on to the subclass's constructor.
     *
     * - Paths with no special characters will generate StaticRoutes
     * - Paths ending with * will generate PrefixRoutes
     * - Paths containing URI variables (e.g., {id}) will generate TemplateRoutes
     * - Regular exressions will generate RegexRoutes
     *
     * @param string $target Path, prefix, or pattern to match
     * @param mixed $middleware Middleware to dispatch
     * @param mixed $extra
     */
    public function add($target, $middleware, $extra = null)
    {
        if (is_array($middleware)) {
            $map = $this->getMethodMap();
            $map->addMap($middleware);
            $middleware = $map;
        }
        $this->routeFactory->registerRoute($this->routeTable, $target, $middleware, $extra);
    }

    public function addPreRouteHook($middleware)
    {
        if (!isset($this->preRouteHooks)) {
            $this->preRouteHooks = [];
        }
        $this->preRouteHooks[] = $middleware;
    }

    public function addPostRouteHook($middleware)
    {
        if (!isset($this->postRouteHooks)) {
            $this->postRouteHooks = [];
        }
        $this->postRouteHooks[] = $middleware;
    }

    public function addResponsePreparationHook($middleware)
    {
        $this->responsePreparationHooks[] = $middleware;
    }

    public function setStatusHandler($statusCode, $middleware)
    {
        $this->statusHandlers[$statusCode] = $middleware;
    }

    public function dispatch(ServerRequestInterface $request, ResponseInterface &$response)
    {
        $this->disptachPreRouteHooks($request, $response);
        try {
            $this->routeTable->dispatch($request, $response);
        } catch (HttpException $e) {
            $response = $response->withStatus($e->getCode());
            $response = $response->withBody(new Stream($e->getMessage()));
        }
        $statusCode = $response->getStatusCode();
        if (isset($this->statusHandlers[$statusCode])) {
            $middleware = $this->statusHandlers[$statusCode];
            $dispatcher = $this->getDispatcher();
            $dispatcher->dispatch($middleware, $request, $response);
        }
        $this->disptachPostRouteHooks($request, $response);
        $this->dispatchResponsePreparationHooks($request, $response);
    }

    public function respond()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();
        $this->dispatch($request, $response);
        $responder = $this->getResponder();
        $responder->respond($response);
    }

    // ------------------------------------------------------------------------
    // The following methods provide instaces the router will use. Override
    // to provide custom classes or configured instances.

    // @codeCoverageIgnoreStart

    /**
     * Return an instance that can dispatch middleware.
     * Override to provide a custom class.
     *
     * @return DispatcherInterface
     */
    protected function getDispatcher()
    {
        if (!isset($this->dispatcher)) {
            $this->dispatcher = new Dispatcher();
        }
        return $this->dispatcher;
    }

    /**
     * @return MethodMapInterface
     */
    protected function getMethodMap()
    {
        return new MethodMap();
    }

    /**
     * @return ServerRequestInterface
     */
    protected function getRequest()
    {
        return ServerRequest::getServerRequest();
    }

    /**
     * @return ResponderInterface
     */
    protected function getResponder()
    {
        return new Responder();
    }

    /**
     * @return ResponseInterface
     */
    protected function getResponse()
    {
        return new Response();
    }

    /**
     * @return MiddlewareInterface[]
     */
    protected function getResponsePreparationHooks()
    {
        return [
            new ContentLengthPrep(),
            new HeadPrep()
        ];
    }

    /**
     * @return RouteFactoryInterface
     */
    protected function getRouteFactory()
    {
        return new RouteFactory();
    }

    /**
     * @return RouteTableInterface
     */
    protected function getRouteTable()
    {
        return new RouteTable();
    }

    // @codeCoverageIgnoreEnd

    // ------------------------------------------------------------------------

    private function disptachPreRouteHooks(ServerRequestInterface $request, ResponseInterface &$response)
    {
        if ($this->preRouteHooks) {
            $dispatcher = $this->getDispatcher();
            foreach ($this->preRouteHooks as $hook) {
                $dispatcher->dispatch($hook, $request, $response);
            }
        }
    }

    private function disptachPostRouteHooks(ServerRequestInterface $request, ResponseInterface &$response)
    {
        if ($this->postRouteHooks) {
            $dispatcher = $this->getDispatcher();
            foreach ($this->postRouteHooks as $hook) {
                $dispatcher->dispatch($hook, $request, $response);
            }
        }
    }

    private function dispatchResponsePreparationHooks(ServerRequestInterface $request, ResponseInterface &$response)
    {
        $dispatcher = $this->getDispatcher();
        foreach ($this->responsePreparationHooks as $hook) {
            $dispatcher->dispatch($hook, $request, $response);
        }
    }
}
