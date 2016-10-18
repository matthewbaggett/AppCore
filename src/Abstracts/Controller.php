<?php
namespace Segura\AppCore\Abstracts;

use Slim\Http\Request;
use Slim\Http\Response;

abstract class Controller
{

    /** @var Service */
    protected $service;
    /** @var bool */
    protected $apiExplorerEnabled = true;

    /**
     * @return Service
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param Service $service
     */
    public function setService($service)
    {
        $this->service = $service;
    }

    /**
     * @return bool
     */
    public function isApiExplorerEnabled()
    {
        return $this->apiExplorerEnabled;
    }

    /**
     * @param bool $apiExplorerEnabled
     */
    public function setApiExplorerEnabled(bool $apiExplorerEnabled)
    {
        $this->apiExplorerEnabled = $apiExplorerEnabled;
    }

    public function jsonResponse($json, Request $request, Response $response)
    {
        if (strtolower($json['Status']) != "okay") {
            $response = $response->withStatus(400);
        } else {
            $response = $response->withStatus(200);
        }
        $json['Extra']['Hostname']   = gethostname();
        $json['Extra']['PHPVersion'] = phpversion();
        if (file_exists(APP_ROOT . "/version.txt")) {
            $json['Extra']['GitVersion'] = trim(file_get_contents(APP_ROOT . "/version.txt"));
        }
        $json['Extra']['TimeExec'] = microtime(true) - APP_START;
        if (
            ($request->hasHeader('Content-type') && $request->getHeader('Content-type')[0] == 'application/json')  ||
            ($request->hasHeader('Accept') && $request->getHeader('Accept')[0] == 'application/json')  ||
            $this->isApiExplorerEnabled() === false
        ) {
            $response = $response->withJson($json, null, JSON_PRETTY_PRINT);
            return $response;
        } else {
            $loader   = new \Twig_Loader_Filesystem(APP_ROOT . "/views");
            $twig     = new \Twig_Environment($loader);
            $response = $response->getBody()->write($twig->render('api-explorer.html.twig', [
                'page_name'                => "API Explorer",
                'json'                     => $json,
                'json_pretty_printed_rows' => explode("\n", json_encode($json, JSON_PRETTY_PRINT)),
            ]));
            return $response;
        }
    }

    public function jsonResponseException(\Exception $e, Request $request, Response $response)
    {
        return $this->jsonResponse(
            [
                'Status' => 'FAIL',
                'Reason' => $e->getMessage(),
            ],
            $request,
            $response
        );
    }
}
