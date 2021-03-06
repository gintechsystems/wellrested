<?php

namespace WellRESTed\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use WellRESTed\Dispatching\Dispatcher;
use WellRESTed\Dispatching\DispatcherInterface;
use WellRESTed\Routing\Route\RouteFactory;
use WellRESTed\Routing\Route\RouteFactoryInterface;
use WellRESTed\Routing\Route\RouteInterface;

class Router
{
    /** @var string attribute name for matched path variables */
    private $pathVariablesAttributeName;
    /** @var DispatcherInterface */
    private $dispatcher;
    /** @var RouteFactoryInterface */
    private $factory;
    /** @var RouteInterface[] Array of Route objects */
    private $routes;
    /** @var RouteInterface[] Hash array mapping exact paths to routes */
    private $staticRoutes;
    /** @var RouteInterface[] Hash array mapping path prefixes to routes */
    private $prefixRoutes;
    /** @var RouteInterface[] Hash array mapping path prefixes to routes */
    private $patternRoutes;
    /** @var mixed[] List array of middleware */
    private $stack;
    /** @var bool Call the next middleware when no route matches */
    private $continueOnNotFound = false;

    /**
     * Create a new Router.
     *
     * By default, when a route containing path variables matches, the path
     * variables are stored individually as attributes on the
     * ServerRequestInterface.
     *
     * When $pathVariablesAttributeName is set, a single attribute will be
     * stored with the name. The value will be an array containing all of the
     * path variables.
     *
     * @param DispatcherInterface $dispatcher
     *     Instance to use for dispatching middleware and handlers.
     * @param string|null $pathVariablesAttributeName
     *     Attribute name for matched path variables. A null value sets
     *     attributes directly.
     */
    public function __construct($dispatcher = null, $pathVariablesAttributeName = null)
    {
        $this->dispatcher = $dispatcher ?: $this->getDefaultDispatcher();
        $this->pathVariablesAttributeName = $pathVariablesAttributeName;
        $this->factory = $this->getRouteFactory($this->dispatcher);
        $this->routes = [];
        $this->staticRoutes = [];
        $this->prefixRoutes = [];
        $this->patternRoutes = [];
        $this->stack = [];
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $next
    ) {
        // Use only the path for routing.
        $requestTarget = parse_url($request->getRequestTarget(), PHP_URL_PATH);

        $route = $this->getStaticRoute($requestTarget);
        if ($route) {
            return $this->dispatch($route, $request, $response, $next);
        }

        $route = $this->getPrefixRoute($requestTarget);
        if ($route) {
            return $this->dispatch($route, $request, $response, $next);
        }

        // Try each of the routes.
        foreach ($this->patternRoutes as $route) {
            if ($route->matchesRequestTarget($requestTarget)) {
                $pathVariables = $route->getPathVariables();
                if ($this->pathVariablesAttributeName) {
                    $request = $request->withAttribute($this->pathVariablesAttributeName, $pathVariables);
                } else {
                    foreach ($pathVariables as $name => $value) {
                        $request = $request->withAttribute($name, $value);
                    }
                }
                return $this->dispatch($route, $request, $response, $next);
            }
        }

        if (!$this->continueOnNotFound) {
            return $response->withStatus(404);
        }

        return $next($request, $response);
    }

    private function dispatch(
        $route,
        ServerRequestInterface $request,
        ResponseInterface $response,
        $next
    ) {
        if (!$this->stack) {
            return $route($request, $response, $next);
        }
        $stack = array_merge($this->stack, [$route]);
        return $this->dispatcher->dispatch(
            $stack, $request, $response, $next
        );
    }

    /**
     * Register handlers and middleware with the router for a given path and
     * method.
     *
     * $method may be:
     * - A single verb ("GET"),
     * - A comma-separated list of verbs ("GET,PUT,DELETE")
     * - "*" to indicate any method.
     *
     * $target may be:
     * - An exact path (e.g., "/path/")
     * - A prefix path ending with "*"" ("/path/*"")
     * - A URI template with variables enclosed in "{}" ("/path/{id}")
     * - A regular expression ("~/cat/([0-9]+)~")
     *
     * $dispatchable may be:
     * - An instance implementing one of these interfaces:
     *     - Psr\Http\Server\RequestHandlerInterface
     *     - Psr\Http\Server\MiddlewareInterface
     *     - WellRESTed\MiddlewareInterface
     *     - Psr\Http\Message\ResponseInterface
     * - A string containing the fully qualified class name of a class
     *     implementing one of the interfaces listed above.
     * - A callable that returns an instance implementing one of the
     *     interfaces listed above.
     * - A callable with a signature matching the signature of
     *     WellRESTed\MiddlewareInterface::__invoke
     * - An array containing any of the items in this list.
     * @see DispatchedInterface::dispatch
     *
     * @param string $target Request target or pattern to match
     * @param string $method HTTP method(s) to match
     * @param mixed $dispatchable Handler or middleware to dispatch
     * @return static
     */
    public function register($method, $target, $dispatchable)
    {
        $route = $this->getRouteForTarget($target);
        $route->register($method, $dispatchable);
        return $this;
    }

    /**
     * Push a new middleware onto the stack.
     *
     * Middleware for a router runs before the middleware and handler for the
     * matched route and runs only when a route matched.
     *
     * $middleware may be:
     * - An instance implementing MiddlewareInterface
     * - A string containing the fully qualified class name of a class
     *     implementing MiddlewareInterface
     * - A callable that returns an instance implementing MiddleInterface
     * - A callable matching the signature of MiddlewareInterface::dispatch
     * @see DispatchedInterface::dispatch
     *
     * @param mixed $middleware Middleware to dispatch in sequence
     * @return static
     */
    public function add($middleware)
    {
        $this->stack[] = $middleware;
        return $this;
    }

    /**
     * Configure the instance to delegate to the next middleware when no route
     * matches.
     *
     * @return static
     */
    public function continueOnNotFound()
    {
        $this->continueOnNotFound = true;
        return $this;
    }

    /**
     * Return an instance to dispatch middleware.
     *
     * @return DispatcherInterface
     */
    protected function getDefaultDispatcher()
    {
        return new Dispatcher();
    }

    /**
     * @param DispatcherInterface
     * @return RouteFactoryInterface
     */
    protected function getRouteFactory($dispatcher)
    {
        return new RouteFactory($dispatcher);
    }

    /**
     * Return the route for a given target.
     *
     * @param $target
     * @return RouteInterface
     */
    private function getRouteForTarget($target)
    {
        if (isset($this->routes[$target])) {
            $route = $this->routes[$target];
        } else {
            $route = $this->factory->create($target);
            $this->registerRouteForTarget($route, $target);
        }
        return $route;
    }

    private function registerRouteForTarget($route, $target)
    {
        // Store the route to the hash indexed by original target.
        $this->routes[$target] = $route;

        // Store the route to the array of routes for its type.
        switch ($route->getType()) {
            case RouteInterface::TYPE_STATIC:
                $this->staticRoutes[$route->getTarget()] = $route;
                break;
            case RouteInterface::TYPE_PREFIX:
                $this->prefixRoutes[rtrim($route->getTarget(), "*")] = $route;
                break;
            case RouteInterface::TYPE_PATTERN:
                $this->patternRoutes[] = $route;
                break;
        }
    }

    private function getStaticRoute($requestTarget)
    {
        if (isset($this->staticRoutes[$requestTarget])) {
            return $this->staticRoutes[$requestTarget];
        }
        return null;
    }

    private function getPrefixRoute($requestTarget)
    {
        // Find all prefixes that match the start of this path.
        $prefixes = array_keys($this->prefixRoutes);
        $matches = array_filter(
            $prefixes,
            function ($prefix) use ($requestTarget) {
                return $this->startsWith($requestTarget, $prefix);
            }
        );

        if ($matches) {
            if (count($matches) > 0) {
                // If there are multiple matches, sort them to find the one with
                // the longest string length.
                $compareByLength = function ($a, $b) {
                    return strlen($b) - strlen($a);
                };
                usort($matches, $compareByLength);
            }
            /** @var string $bestMatch */
            $bestMatch = $matches[0];
            $route = $this->prefixRoutes[$bestMatch];
            return $route;
        }
        return null;
    }

    private function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
}
