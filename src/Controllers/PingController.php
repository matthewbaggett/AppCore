<?php
namespace Gone\AppCore\Controllers;

use Gone\AppCore\Abstracts\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

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
