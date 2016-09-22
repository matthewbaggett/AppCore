<?php
namespace Segura\AppCore\Controllers;

use Segura\AppCore\Abstracts\Controller;
use Segura\AppCore\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Route;

class ApiListController extends Controller
{
    public function listAllRoutes(Request $request, Response $response, $args)
    {
        $loader = new \Twig_Loader_Filesystem(APP_ROOT . "/views");
        $twig   = new \Twig_Environment($loader);


        $router = App::Container()->get("router");
        $routes = $router->getRoutes();

        $displayRoutes = [];

        foreach ($routes as $route) {
            /** @var $route Route */
            if (json_decode($route->getName()) !== null) {
                $routeJson       = json_decode($route->getName(), true);
                $displayRoutes[] = $routeJson;
            } else {
                $displayRoutes[] = [
                    'name'    => $route->getName(),
                    'pattern' => $route->getPattern(),
                    'methods' => $route->getMethods()
                ];
            }

        }

        #!\Kint::dump($displayRoutes);exit;

        if ($request->getContentType() == "application/json") {
            return $this->jsonResponse([
                'Status' => "Okay",
                'Routes' => $displayRoutes
            ], $request, $response);
        } else {
            return $response->getBody()->write($twig->render('api-list.html.twig', [
                'page_name' => "API Endpoint List",
                'routes'    => $displayRoutes,
            ]));
        }
    }
}
