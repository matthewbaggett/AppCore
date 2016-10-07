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
        foreach($this->routes as $route){
            $route->populateRoute($app);
        }
        return $app;
    }

    public function addRoute(Route $route)
    {
        $this->routes[] = $route;
        return $this;
    }

}