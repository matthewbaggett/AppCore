<?php

namespace Segura\AppCore\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class SeguraJSONResponseLinter
{
    protected $apiExplorerEnabled = true;

    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Response $response */
        $response = $next($request, $response);
        if (isset($response->getHeader('Content-Type')[0])
            and stripos($response->getHeader('Content-Type')[0], 'application/json') !== false
        ) {
            $body = $response->getBody();
            $body->rewind();

            $json = json_decode($body->getContents(), true);

            if (isset($json['Status'])) {
                $json['Status'] = ucfirst(strtolower($json['Status']));
            }

            $response = $response->withJson($json, null, JSON_PRETTY_PRINT);
        }

        return $response;
    }
}
