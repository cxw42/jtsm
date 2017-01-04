<?php

namespace FRTest;

define('JTSM', __DIR__ . DIRECTORY_SEPARATOR);

require JTSM . 'vendor/autoload.php';
require JTSM . 'config.php';

class App {
    // TODO see about including https://github.com/paragonie/anti-csrf

    // HTTP
    protected $request;
    protected $response;
    protected $emitter;

    // Routing
    private $collector;
    private $dispatcher;
    protected $generator;

    // Templating
    private $twig;

    /**
     * Make a new instance to handle requests.
     * If any of the parameters are NULL (the default), the corresponding
     * superglobals are used.
     *
     * @param $server $_SERVER or equivalent
     * @param $get $_GET or equivalent
     * @param $post $_POST or equivalent
     * @param $cookie $_COOKIE or equivalent
     * @param $files $_FILES or equivalent
     */
    public function __construct($server=NULL, $get=NULL, $post=NULL,
                                $cookie=NULL, $files=NULL) {
        // Process defaults
        if(is_null($server))    { $server = $_SERVER; }
        if(is_null($get))       { $get = $_GET; }
        if(is_null($post))      { $post = $_POST; }
        if(is_null($cookie))    { $cookie = $_COOKIE; }
        if(is_null($files))     { $files = $_FILES; }

        //error_log("Howdy!");  // Debugging message to the console
        // Set up HTTP interaction
        $this->request = \Zend\Diactoros\ServerRequestFactory::fromGlobals(
            $server, $get, $post, $cookie, $files
        );

        $this->response = new \Zend\Diactoros\Response\HtmlResponse("");
            // https://zendframework.github.io/zend-diactoros/custom-responses/
        $this->emitter = new \Zend\Diactoros\Response\SapiEmitter();

        // Set up routing
        $this->dispatcher =
            \FastRoute\simpleDispatcher([$this,'populateRoutes']);
        $routeData = $this->collector->getParsedRoutes();
        $this->generator = new \FastRoute\RouteGenerator\Std($routeData);
        $this->collector = NULL;    // we don't need it any more

        // Set up templates
        global $jtsm_template_path;
        $loader = new \Twig_Loader_Filesystem($jtsm_template_path);
        $this->twig = new \Twig_Environment($loader,
            ['cache' => JTSM . 'cache']);

        // Templates can generate routes with gen()
        $fn = new \Twig_SimpleFunction('gen', [$this, 'generateRoute'],
            [ 'is_variadic' => 'true', 'is_safe' => ['html'], ]);
            // is_safe: output should be included raw in the html
        $this->twig->addFunction($fn);

    } //constructor

    /**
     * Interface from templates to the route generator
     */
    public function generateRoute($routeName, $values = []) {
        return $this->generator->gen($routeName, $values);
    } //generateRoute()

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
                    $this->response =
                        $this->response->withStatus(404, 'Not found');
                    break;
                case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    $allowedMethods = $routeInfo[1];
                    $this->response = $this->response->withStatus(405,
                        'Not allowed: try ' . join(", ", $allowedMethods));
                    break;
                case \FastRoute\Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];
                    //$this->response->getBody()->write(
                    //    "Got route to $handler\n" . print_r($vars, TRUE));
                    $this->$handler($vars);
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
    } //run()

    // === Functions to be overridden in subclasses ========================

    // TODO add a function to create a route generator so the subclass can
    // choose, e.g., a cached generator

    /**
     * Fill in the routes we can respond to.
     *
     * This implementation uses annotations to determine the routes.
     * The route name is the name of the function
     * (TODO strip `do_` prefix if present).
     * The route string, as in FastRoute, is the parameter of AT-`route`.
     * The route method is GET unless another method is specified with
     * `@routemethod`.  For example (with AT replaced with an at sign):
     *
     * ```php
     * /**
     *  * Route foo
     *  *
     *  * AT-routemethod POST
     *  * AT-route /foo/{id:\d+}
     *  ...
     *  function foo($values=[]) { ... }
     * ```
     */
    function populateRoutes(\FastRoute\RouteCollector $r) {
        $this->collector = $r;
        $cls = new \Notoj\ReflectionClass($this);
        foreach($cls->getMethods() as $method) {
            $anns = $method->getAnnotations();
            if(!$anns->has('route')) { continue; }

            $route = trim($anns->getOne('route')->getArg(0));

            if($anns->has('routemethod')) {
                $http_method = trim($anns->getOne('routemethod')->getArg(0));
            } else {
                $http_method = 'GET';
            }

            $r->addRoute($http_method, $route, $method->getName());
        } //foreach $method
    } //populateRoutes()

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
    } //getTarget()

    // === Test routes =====================================================

    /**
     * @route /users
     */
    function users() {
        $this->response->getBody()->write(
            $this->twig->render('users.twig.php')
        );
    }

    /**
     * @route /foo/{id}
     */
    function things($vars) {
        $this->response->getBody()->write("things!\n" .
            print_r($vars, TRUE));
    }

} //App

(new App())->run();

// vi: set ts=4 sts=4 sw=4 et ai: //

