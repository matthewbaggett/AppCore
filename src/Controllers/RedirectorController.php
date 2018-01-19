<?php
namespace Segura\AppCore\Controllers;

use Segura\AppCore\Abstracts\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class RedirectorController extends Controller
{
    public function redirectToApi(Request $request, Response $response, $args)
    {
        return $response->withRedirect("/v1");
    }
}
