<?php

namespace JTSM;

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
    protected $twig;

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
    public function __construct($site_root,
                                $server=NULL, $get=NULL, $post=NULL,
                                $cookie=NULL, $files=NULL) {
        // Process defaults
        if(is_null($server))    { $server = $_SERVER; }
        if(is_null($get))       { $get = $_GET; }
        if(is_null($post))      { $post = $_POST; }
        if(is_null($cookie))    { $cookie = $_COOKIE; }
        if(is_null($files))     { $files = $_FILES; }

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
        $loader = new \Twig_Loader_Filesystem($site_root . "skins");
        $this->twig = new \Twig_Environment($loader,
            ['cache' => $site_root . 'skins' . DIRECTORY_SEPARATOR . 'cache',
            'debug' => TRUE,
            'auto_reload' => TRUE]
        );

        // Templates can generate routes with gen()
        $fn = new \Twig_SimpleFunction('gen', [$this, 'generateRoute'],
            [ 'is_variadic' => 'true', 'is_safe' => ['html'], ]);
            // is_safe: output should be included raw in the html
        $this->twig->addFunction($fn);

    } //constructor

    /**
     * Dispatches to the request and returns a result
     */
    public function run() {
        $httpMethod = $this->request->getMethod();

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
                    $this->$handler($vars);
                    break;
            }
        } //endif got_target

        if($this->response->getStatusCode() !== 200) {
            $this->response->getBody()->write('An error occurred: ' .
                $this->response->getReasonPhrase());
        }

        // Fire away!
        $this->emitter->emit($this->response);
    } //run()

    /**
     * Return the value of a string query parameter, or NULL.
     * TODO add QPi, QP<whatever else> for other types
     * @param $name the name of the query parameter
     */
    function QPs($name, $filtering = FILTER_UNSAFE_RAW) {
        $parms = $this->request->getQueryParams();

        if(array_key_exists($name, $parms) && (!is_null($parms[$name]))) {
            $val = $parms[$name];
            if(is_string($val) && (strlen($val)<65535)) {
                // arbitrary length limit
                $q = filter_var($parms[$name], $filtering);
                if($q !== FALSE) {  // Valid query
                    return $q;
                }
            }
        }
        return NULL;
    } //QPs

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
            if(!is_string($route) || (strlen($route)==0)) {
                throw new \Exception("Invalid route for " . $method->name);
            }

            if(!$anns->has('routemethod')) {
                $http_method = 'GET';
            } else {
                $http_method = trim($anns->getOne('routemethod')->getArg(0));
                if(!is_string($http_method) || (strlen($http_method)==0)) {
                    throw new \Exception(
                                "Invalid HTTP method for " . $method->name);
                }
            } //endif @routemethod

            $r->addRoute($http_method, $route, $method->name, $method->name);
        } //foreach $method
    } //populateRoutes()

    // === Functions to be overridden in subclasses ========================

    // TODO add a function to create a route generator so the subclass can
    // choose, e.g., a cached generator

    // === Request processing

    /**
     * Returns the target, the path to be checked against the available routes
     *
     * By default, pulls from the "p" query parameter.
     * Override in subclasses to change where the target comes from.
     */
    public function getTarget() {
        $p = $this->QPs('p');
        if($p === NULL) {
            return "/";     // Default to the root
        }
        return $p;
    } //getTarget()

    // TODO add makeTarget() that will build a target from a route.
    // It should also support <input type=hidden> if the route is
    // stored in a query parameter.

    /**
     * Interface from templates to the route generator
     */                                    // VVVVV required by Twig
    public function generateRoute($routeName, array $values = []) {
        return '/?p=' . $this->generator->gen($routeName, $values);
            // We use a query parameter rather than the REQUEST_URI
    } //generateRoute()


} //App

// vi: set ts=4 sts=4 sw=4 et ai: //

