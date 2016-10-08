<?php
namespace Segura\AppCore\Router;

use Slim\App;

class Router {

    static protected $instance;

    /** @var Route[] */
    protected $routes;

    public function __construct()
    {
    }

    /**
     * @return Router
     */
    public static function Instance()
    {
        if( ! self::$instance instanceof Router){
            self::$instance = new Router;
        }
        return self::$instance;
    }

    public function populateRoutes(App $app)
    {
        if(count($this->routes) > 0) {
            foreach ($this->routes as $route) {
                $route->populateRoute($app);
            }
        }
        return $app;
    }

    public function addRoute(Route $route)
    {
        $this->routes[$route->getUniqueIdentifier()] = $route;
        return $this;
    }

    /**
     * @return Route[]
     */
    public function getRoutes()
    {
        return $this->routes;
    }

}