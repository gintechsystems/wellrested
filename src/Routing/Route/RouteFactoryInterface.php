<?php

namespace WellRESTed\Routing\Route;

interface RouteFactoryInterface
{
    /**
     * Creates a route for the given target.
     *
     * - Target with no special characters will create StaticRoutes
     * - Target ending with * will create PrefixRoutes
     * - Target containing URI variables (e.g., {id}) will create TemplateRoutes
     * - Regular exressions will create RegexRoutes
     *
     * @param string $target Route target or target pattern
     * @return RouteInterface
     */
    public function create($target);
}
