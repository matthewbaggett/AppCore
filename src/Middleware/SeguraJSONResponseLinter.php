<?php

namespace Segura\AppCore\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class SeguraJSONResponseLinter
{
    public function __invoke(Request $request, Response $response, $next)
    {
        try {
            $response = $next($request, $response);
            $jsonMode = (isset($response->getHeader('Content-Type')[0]) and stripos($response->getHeader('Content-Type')[0], 'application/json') !== false);
            if ($jsonMode) {
                $body = $response->getBody();
                $body->rewind();
                $json = json_decode($body->getContents(), true);
                if (isset($json['Status'])) {
                    $json['Status'] = ucfirst(strtolower($json['Status']));
                }
                $response = $response->withJson($json, null, JSON_PRETTY_PRINT);
            }
            return $response;
        } catch (\Exception $exception) {
            $response = $response->withJson(
                [
                    'Status' => 'Fail',
                    'Exception' => get_class($exception),
                    'Reason' => $exception->getMessage(),
                    'Trace' => $exception->getTraceAsString(),
                ],
                500,
                JSON_PRETTY_PRINT
            );
            return $response;
        }
    }
}
