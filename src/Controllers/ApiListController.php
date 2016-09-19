<?php
namespace Segura\AppCore\Controllers;

use Segura\AppCore\Abstracts\Controller;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Route;
use Segura\AppCore\App;

class ApiListController extends Controller
{
    public function listAllRoutes(Request $request, Response $response, $args)
    {
        $loader = new \Twig_Loader_Filesystem(APP_ROOT .  "/views");
        $twig   = new \Twig_Environment($loader);


        $router = App::Container()->get("router");
        $routes = $router->getRoutes();

        $displayRoutes = [];

        foreach ($routes as $route) {
            /** @var $route Route */
            $displayRoutes[] = [
                'name'    => $route->getName(),
                'pattern' => $route->getPattern(),
                'methods' => $route->getMethods()
            ];
        }

        #!\Kint::dump($displayRoutes);exit;

        $response = $response->getBody()->write($twig->render('api-list.html.twig', [
            'page_name' => "API Endpoint List",
            'routes'    => $displayRoutes,
        ]));
        
        return $response;
    }
}
