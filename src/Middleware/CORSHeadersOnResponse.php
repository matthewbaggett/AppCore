<?php
/**
 * Created by PhpStorm.
 * User: wolfgang
 * Date: 01/11/18
 * Time: 12:54
 */

namespace Gone\AppCore\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class CORSHeadersOnResponse
{
    private $domainRegexs = "";

    public function __construct(array $domainRegexs)
    {
        if (!is_array($domainRegexs)) {
            if (is_string($domainRegexs)) {
                $domainRegexs =[$domainRegexs];
            } else {
                $domainRegexs = null;
            }
        } else {
            foreach ($domainRegexs as $regex) {
                if (!is_string($regex)) {
                    $domainRegexs = null;
                    break;
                }
            }
        }
        if (empty($domainRegexs)) {
            throw new \Exception("Invalid domainRegex for CORS Middleware. Expected string or array");
        }
        $this->domainRegexs = $domainRegexs;
    }

    public function __invoke(Request $request, Response $response, $next)
    {

        /** @var Response $response */
        $response = $next($request, $response);
        if (!empty($request->getHeader("HTTP_ORIGIN")[0])) {
            $origin = $request->getHeader("HTTP_ORIGIN")[0];
            $pass = false;
            foreach ($this->domainRegexs as $regex) {
                if (preg_match($regex, $origin)) {
                    $pass = true;
                    break;
                }
            }
            if ($pass) {
                return $response
                    ->withHeader('Access-Control-Allow-Origin', $origin)
                    ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Ticket')
                    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            }
        }
        return $response;
    }
}
