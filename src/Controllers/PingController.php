<?php
namespace Segura\AppCore\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Segura\AppCore\Abstracts\Controller;

class PingController extends Controller
{
    public function __construct(\Slim\Container $container)
    {
        $this->setApiExplorerEnabled(false);
    }

    public function doPing(Request $request, Response $response, array $args)
    {
        return $this->jsonResponse(
            [
                'Status' => 'Okay',
            ],
            $request,
            $response
        );
    }
}
