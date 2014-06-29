WellRESTed
==========

WellRESTed is a microframework for creating RESTful APIs in PHP. It provides a lightwight yet powerful routing system and classes to make working with HTTP requests and responses clean and easy.

Requirements
------------

- PHP 5.3
- [Composer](http://getcomposer.org/) for autoloading
- [PHP cURL](http://php.net/manual/en/book.curl.php) for making requests


Install
-------

Add an entry for "pjdietz/wellrested" to your composer.json file's **require** property. If you are not already using Composer, create a file in your project called **composer.json** with the following content:

```json
{
    "require": {
        "pjdietz/wellrested": "2.*"
    }
}
```

Use Composer to download and install WellRESTed. Run these commands from the directory containing the **composer.json** file.

```bash
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install
```

You can now use WellRESTed by including the **autoload.php** file generated by Composer. `vendor/autoload.php`


Examples
--------

### Routing

WellRESTed's primary goal is to facilitate mapping of URIs to classes that will provide or accept representations. To do this, create a Router instance and load it up with some Routes. Each Route is simply a mapping of a URI pattern to a class name. The class name represents the Handler (any class implementing `HandlerInterface`) which the router will dispatch at the time it receives a request for the given URI. **The handlers are never loaded unless they are needed.**

Here's an example of a Router that will handle two URIs:

```php
// Build the router.
$myRouter = new Router();
$myRouter->addRoutes(array(
    new StaticRoute("/", "\\myapi\\Handlers\\RootHandler")),
    new StaticRoute("/cats/", "\\myapi\\Handlers\\CatCollectionHandler")),
    new TemplateRoute("/cats/{id}/", "\\myapi\\Handlers\\CatItemHandler"))
);
$myRouter->respond();
```

### Building Routes with JSON

WellRESTed also provides a class to construct routes for you based on a JSON description. Here's an example.

```php
$json = <<<'JSON'
{
    "handlerNamespace": "\\myapi\\Handlers",
    "routes": [
        {
            "path": "/",
            "handler": "RootHandler"
        },
        {
            "path": "/cats/",
            "handler": "CatCollectionHandler"
        },
        {
            "tempalte": "/cats/{id}",
            "handler": "CatItemHandler"
        }
    ]
}
JSON;

$builder = new RouteBuilder();
$routes = $builder->buildRoutesFromJson($json);

$router = new Router();
$router->addRoutes($routes);
$router->respond();
```

Notice that when you build routes through JSON, you can provide a `handlerNamespace` to be affixed to the front of every `handler`.

### Handlers

Any class that implements `HandlerInterface` may be the handler for a route. This could be a class that builds the actual response, or it could another `Router`.

For most cases, you'll want to use a subclass of the `Handler` class, which provides methods for responding based on HTTP method. When you create your Handler subclass, you will implement a method for each HTTP verb you would like the endpoint to support. For example, if `/cats/` should support `GET`, you would override the `get()` method. For `POST`, `post()`, etc.

If your endpoint should reject particular verbs, no worries. The Handler base class defines the default verb-handling methods to respond with a **405 Method Not Allowed** status.

Here's a simple Handler that matches the first endpoint, `/things/`.

```php
class CatsCollectionHandler extends \pjdietz\WellRESTed\Handler
{
    protected function get()
    {
        // Read some things from the database, cache, whatever.
        // ...read this into the variable $cat

        // Set the values for the instance's response member. This is what the
        // Router will eventually use to output a response to the client.
        $this->response->setStatusCode(200);
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setBody(json_encode($cat));
    }

    protected function post()
    {
        // Read from the instance's request member and store a new cat.
        $cat = json_decode($this->request->getBody());
        // ...store $cat to database...

        // Build a response to send to the client.
        $this->response->setStatusCode(201);
        $this->response->setBody('You added a new cat!');
    }
}
```

This Handler works with the endpoint, `/cats/{id}`. The template for this endpoint has the variable `{id}` in it. The Handler can access path variables through its `args` member, which is an associative array of variables from the URI.

```php
class ThingItemHandler extends \pjdietz\WellRESTed\Handler
{
    protected function get()
    {
        // Lookup a cat ($cat) based on $this->args['id']
        // ...do lookup here...

        if ($cat) {
            // The cat exists! Let's output a representation.
            $this->response->setStatusCode(200);
            $this->response->setHeader('Content-Type', 'application/json');
            $this->response->setBody(json_encode($cat));
        } else {
            // The ID did not match anything.
            $this->response->setStatusCode(404);
            $this->response->setHeader('Content-Type', 'text/plain');
            $this->response->setBody('No cat with id ' . $this->args['id']);
        }
    }
}
```


### Requests and Responses

You've already seen a `Response` in use in the examples above. You can also use `Response`s outside of `Handler`s. Let's take a look at creating a new `Response`, setting a header, supplying the body, and outputting.

```php
$resp = new \pjdietz\WellRESTed\Response();
$resp->setStatusCode(200);
$resp->setHeader('Content-type', 'text/plain');
$resp->setBody('Hello world!');
$resp->respond();
exit;
```

The `Request` class goes hand-in-hand with the `Response` class. Again, this is used in the Handler class to read the information from the request being handled. From outside the context of a `Handler`, you can also use the `Request` class to read info for the request sent to the server.

```php
// Call the static method Request::getRequest() to get a reference to the Request
// singleton that represents the request made to the server.
$rqst = \pjdietz\WellRESTed\Request::getRequest();

if ($rqst->getMethod() === 'PUT') {
    $obj = json_decode($rqst->getBody());
    // Do something with the JSON sent as the message body.
    // ...
}
```

The Request class can also make a request to another server and provide the response as a Response object. (This feature requires [PHP cURL](http://php.net/manual/en/book.curl.php).)

```php
// Prepare a request.
$rqst = new \pjdietz\WellRESTed\Request();
$rqst->setUri('http://my.api.local/resources/');
$rqst->setMethod('POST');
$rqst->setBody(json_encode($newResource));

// Make the request.
$resp = $rqst->request();

// Read the response.
if ($resp->getStatusCode() === 201) {
    // The new resource was created.
    $createdResource = json_decode($resp->getBody());
}
```


More Examples
---------------

For more examples, see the project [pjdietz/wellrested-samples](https://github.com/pjdietz/wellrested-samples). **Not yet updated for version 2.0**


Copyright and License
---------------------
Copyright © 2014 by PJ Dietz
Licensed under the [MIT license](http://opensource.org/licenses/MIT)
