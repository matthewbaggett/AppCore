<?php

namespace Gone\AppCore\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class JSONResponseLinter
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
            $trace = explode("\n", $exception->getTraceAsString());
            array_walk($trace, function (&$elem) {
                $pieces = explode(" ", $elem, 2);
                $elem = $pieces[1];
                $highlightLocations = [
                    '/app/src/',
                    '/app/tests/'
                ];
                foreach ($highlightLocations as $highlightLocation) {
                    if (substr($elem, 0, strlen($highlightLocation)) == $highlightLocation) {
                        $elem = "*** {$elem}";
                    }
                }
            });
            $response = $response->withJson(
                [
                    'Status' => 'Fail',
                    'Exception' => get_class($exception),
                    'Reason' => $exception->getMessage(),
                    'Trace' => $trace,
                ],
                500,
                JSON_PRETTY_PRINT
            );
            return $response;
        }
    }
}
