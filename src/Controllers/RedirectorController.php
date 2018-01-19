<?php
namespace Segura\AppCore\Controllers;

use Segura\AppCore\Abstracts\Controller;
use Segura\AppCore\App;
use Segura\AppCore\Router\Router;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Route;
use Slim\Views\Twig;

class RedirectorController extends Controller
{
    public function redirectToApi(Request $request, Response $response, $args)
    {
        return $response->withRedirect("/v1");
    }
}
