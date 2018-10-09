<?php
namespace Segura\AppCore\Traits;

use Segura\AppCore\App;
use Segura\Session\Session;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

trait RedirectTrait {

    protected function redirect($url) : Response
    {
        $response = new Response();
        return $response->withRedirect($url);
    }
}