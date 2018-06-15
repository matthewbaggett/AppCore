<?php

namespace Segura\AppCore\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class TrailingSlashMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $uri  = $request->getUri();
        $path = $uri->getPath();

        // permanently redirect paths with a trailing slash to their non-trailing counterpart
        if ($path != '/' && substr($path, -1) == '/') {
            $uri = $uri->withPath(substr($path, 0, -1));
            return $response->withRedirect((string) $uri, 301);
        }

        return $next($request, $response);
    }
}
