<?php
namespace Gone\AppCore\Controllers;

use Gone\AppCore\Abstracts\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class RedirectorController extends Controller
{
    public function redirectToApi(Request $request, Response $response, $args)
    {
        return $response->withRedirect("/v1");
    }
}
