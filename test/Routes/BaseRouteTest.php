<?php

namespace pjdietz\WellRESTed\Test;

use pjdietz\WellRESTed\Routes\StaticRoute;

class BaseRouteTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Create a route that will match, but has an incorrect handler assigned.
     * @expectedException  \UnexpectedValueException
     */
    public function testSinglePathMatch()
    {
        $path = "/";

        $mockRequest = $this->getMock('\pjdietz\WellRESTed\Interfaces\RequestInterface');
        $mockRequest->expects($this->any())
            ->method('getPath')
            ->will($this->returnValue($path));

        $path = "/";
        $route = new StaticRoute($path, __NAMESPACE__ . '\NotAHandler');
        $resp = $route->getResponse($mockRequest);
    }
}

class NotAHandler
{
}