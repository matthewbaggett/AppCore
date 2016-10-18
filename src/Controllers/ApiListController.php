<?php
namespace Segura\AppCore\Controllers;

use Segura\AppCore\Abstracts\Controller;
use Segura\AppCore\App;
use Segura\AppCore\Router\Router;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Route;

class ApiListController extends Controller
{
    public function listAllRoutes(Request $request, Response $response, $args)
    {
        if ($request->getContentType() == "application/json" || $request->getHeader("Accept")[0] == "application/json") {
            $json           = [];
            $json['Status'] = "Okay";
            foreach(Router::Instance()->getRoutes() as $route){
                $routeArray = [
                    'name'       => $route->getName(),
                    'class'      => $route->getClass(),
                    'template'   => $route->getTemplate(),
                    'function'   => $route->getFunction(),
                    'endpoint'   => $route->getHttpEndpoint(),
                    'pattern'    => $route->getRouterPattern(),
                    'method'     => $route->getHttpMethod(),
                    'singular'   => $route->getSingular(),
                    'plural'     => $route->getPlural(),
                    'properties' => $route->getProperties(),
                    'example'    => $route->getExampleEntity(),
                    'callbackProperties' => $route->getCallbackProperties(),
                ];

                $json['Routes'][] = array_filter($routeArray);
            }
            return $this->jsonResponse($json, $request, $response);
        }else {
            $loader = new \Twig_Loader_Filesystem(APP_ROOT . "/views");
            $twig   = new \Twig_Environment($loader);

            $router = App::Container()->get("router");
            $routes = $router->getRoutes();

            $displayRoutes = [];

            foreach ($routes as $route) {
                /** @var $route Route */
                if (json_decode($route->getName()) !== null) {
                    $routeJson            = json_decode($route->getName(), true);
                    $routeJson['pattern'] = $route->getPattern();
                    $routeJson['methods'] = $route->getMethods();
                    $displayRoutes[]      = $routeJson;
                } else {
                    $displayRoutes[] = [
                        'name'    => $route->getName(),
                        'pattern' => $route->getPattern(),
                        'methods' => $route->getMethods()
                    ];
                }

            }

            #!\Kint::dump($displayRoutes);exit;

            return $response->getBody()->write($twig->render('api-list.html.twig', [
                'page_name' => "API Endpoint List",
                'routes'    => $displayRoutes,
            ]));

        }
    }
}
