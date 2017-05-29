<?php
namespace Segura\AppCore\Router;

use Slim\App;

class Router
{
    protected static $instance;

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
        if (! self::$instance instanceof Router) {
            self::$instance = new Router;
        }
        return self::$instance;
    }

    public function weighRoutes() : Router
    {
        uasort($this->routes, function(Route $a, Route $b){
            return $a->getWeight() > $b->getWeight();
        });
        return $this;
    }

    public function populateRoutes(App $app)
    {
        $this->weighRoutes();
        if (count($this->routes) > 0) {
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
