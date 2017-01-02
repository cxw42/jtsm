<?php namespace FRTest;

# Modified from the FastRoute README.md
require 'vendor/autoload.php';

use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

/*
echo "Server\n";
print_r($_SERVER);
echo "\nRequest\n";
print_r($_REQUEST);
*/

/*
$collector=NULL;

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = "/" . $_SERVER['QUERY_STRING'];

echo "PSR7-queryparams\n";
$request = \Zend\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

print_r($request->getQueryParams());
*/

class App {
    //HTTP
    private $request;
    private $response;
    private $emitter;

    //Routing
    private $collector;
    private $dispatcher;
    private $routeData;

    public function __construct() {
        // HTTP
        $this->request = \Zend\Diactoros\ServerRequestFactory::fromGlobals(
            $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
        );
        $this->response = new \Zend\Diactoros\Response\HtmlResponse("");
        //$this->response->getBody()->write(print_r($this->request, TRUE));
        $this->emitter = new \Zend\Diactoros\Response\SapiEmitter();

        // Routing
        $this->dispatcher =
            \FastRoute\simpleDispatcher([$this,'populateRoutes']);
        $this->routeData = $this->collector->getParsedRoutes();
        $this->collector = NULL;    // we don't need it any more
        //print_r($this->routeData);

    } //constructor

    function populateRoutes(\FastRoute\RouteCollector $r) {
        $this->collector = $r;
        $r->addRoute('GET', '/users', 'get_all_users_handler');
        // {id} must be a number (\d+)
        $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler', 'user');
        // The /{title} suffix is optional
        $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler',
                        'article');
    }

    /**
     * Returns the target, the path to be checked against the available routes
     *
     * By default, pulls from the "p" query parameter.
     * Override in subclasses to change where the target comes from.
     */
    public function getTarget() {
        $qps = $this->request->getQueryParams();
        if(!isset($qps['p'])) {
            throw new \Exception('No query');
        }
        return $qps['p'];
    } //getTarget

    /**
     * Dispatches to the request and returns a result
     */
    public function run() {
        $httpMethod = $this->request->getMethod();

        // Write the header
        $this->response->getBody()->write( <<<'EOD'
<html><head><title>FRTest</title></head><body>
<pre>
EOD
        );

        $got_target = FALSE;
        $target = "";
        try {
            $target = $this->getTarget();
            $got_target = TRUE;
        } catch(\Exception $e) {
            $this->response = $this->response->withStatus(400,
                'Could not determine which target is requested');
        }

        if($got_target) {
            $routeInfo = $this->dispatcher->dispatch($httpMethod, $target);

            switch ($routeInfo[0]) {
                case \FastRoute\Dispatcher::NOT_FOUND:
                    $this->response = $this->response->withStatus(404, 'Not found');
                    break;
                case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    $allowedMethods = $routeInfo[1];
                    $this->response = $this->response->withStatus(405,
                        'Not allowed: try ' . join(", ", $allowedMethods));
                    break;
                case \FastRoute\Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];
                    $this->response->getBody()->write("Got route to $handler\n" .
                        print_r($vars, TRUE));
                    break;
            }
        } //endif got_target

        if($this->response->getStatusCode() !== 200) {
            $this->response->getBody()->write('An error occurred: ' .
                $this->response->getReasonPhrase());
        }

        // Write the trailer
        $this->response->getBody()->write( <<<'EOD'
</pre>
</body></html>
EOD
        );

        // Fire away!
        $this->emitter->emit($this->response);
    } //run

    /**
     * @route('GET','/users)
     */
    function TODO() {
    }

} //App

$app = new App();
$app->run();

/*
function populateRoutes(\FastRoute\RouteCollector $r) {
    global $collector;
    $collector = $r;
    $r->addRoute('GET', '/users', 'get_all_users_handler');
    // {id} must be a number (\d+)
    $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler', 'user');
    // The /{title} suffix is optional
    $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler',
                    'article');
}

$dispatcher = \FastRoute\simpleDispatcher('\FRTest\populateRoutes');

echo "Parsed routes\n";
print_r($collector->getParsedRoutes());


// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case \FastRoute\Dispatcher::NOT_FOUND:
        echo(" ... 404 Not Found\n");
        break;
    case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        echo(" ... 405 Method Not Allowed\n");
        break;
    case \FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        echo "Got route to $handler\n";
        print_r($vars);
        break;
}
*/

// vi: set ts=4 sts=4 sw=4 et ai: //

