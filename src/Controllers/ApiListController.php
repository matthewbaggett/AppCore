<?php
namespace Gone\AppCore\Controllers;

use Gone\AppCore\Abstracts\Controller;
use Gone\AppCore\App;
use Gone\AppCore\Redis\Redis;
use Gone\AppCore\Router\Router;
use Monolog\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class ApiListController extends Controller
{
    /** @var Logger */
    private $logger;

    public function __construct(
        Logger $logger
    )
    {
        $this->logger = $logger;
    }

    public function listAllRoutes(Request $request, Response $response, $args)
    {
        $this->logger->info("listAllRoutes requested");

        if ($request->getContentType() == "application/json" || $request->getHeader("Accept")[0] == "application/json") {
            $json           = [];
            $json['Status'] = "Okay";
            foreach (Router::Instance()->getRoutes() as $route) {
                $routeArray = [
                    'name'               => $route->getName(),
                    'class'              => $route->getSDKClass(),
                    'function'           => $route->getSDKFunction(),
                    'template'           => $route->getSDKTemplate(),
                    'endpoint'           => $route->getHttpEndpoint(),
                    'pattern'            => $route->getRouterPattern(),
                    'method'             => $route->getHttpMethod(),
                    'singular'           => $route->getSingular(),
                    'plural'             => $route->getPlural(),
                    'properties'         => $route->getProperties(),
                    'propertiesOptions'  => $route->getPropertyOptions(),
                    'propertyData'       => $route->getPropertyData(),
                    'access'             => $route->getAccess(),
                    'example'            => $route->getExampleEntity() ? $route->getExampleEntity()->__toArray() : null,
                    'callbackProperties' => $route->getCallbackProperties(),
                ];

                $json['Routes'][] = array_filter($routeArray);
            }
            return $this->jsonResponse($json, $request, $response);
        }
        $router = App::Container()->get("router");
        $routes = $router->getRoutes();

        $displayRoutes = [];

        foreach (Router::Instance()->getRoutes() as $route) {
            if (json_decode($route->getName()) !== null) {
                $routeJson            = json_decode($route->getName(), true);
                $routeJson['pattern'] = $route->getPattern();
                $routeJson['methods'] = $route->getMethods();
                $displayRoutes[]      = $routeJson;
            } else {
                $callable = $route->getCallback();

                if ($callable instanceof \Closure) {
                    $callable = "\Closure";
                }

                if (is_array($callable)) {
                    list($callableClass, $callableFunction) = $callable;
                    if (is_object($callableClass)) {
                        $callableClass = get_class($callableClass);
                    }
                    $callable = "{$callableClass}:{$callableFunction}";
                }

                $displayRoutes[] = [
                    'name'     => $route->getName(),
                    'pattern'  => $route->getRouterPattern(),
                    'methods'  => $route->getHttpMethod(),
                    'callable' => $callable,
                    'access'   => $route->getAccess(),
                    'properties'=> $route->getCallbackProperties(),
                ];
            }
        }

        /** @var Twig $twig */
        $twig = App::Instance()->getContainer()->get("view");

        return $twig->render($response, 'api/list.html.twig', [
            'page_name'  => "API Endpoint List",
            'routes'     => $displayRoutes,
            'inline_css' => $this->renderInlineCss([
                __DIR__ . "/../../assets/css/reset.css",
                __DIR__ . "/../../assets/css/api-explorer.css",
                __DIR__ . "/../../assets/css/api-list.css",
            ])
        ]);
    }
}
