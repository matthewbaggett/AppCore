<?php
namespace Segura\AppCore\Traits;

use Slim\Http\Response;

trait RedirectTrait
{
    protected function redirect($url) : Response
    {
        $response = new Response();
        return $response->withRedirect($url);
    }
}
