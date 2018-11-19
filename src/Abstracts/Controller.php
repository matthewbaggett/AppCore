<?php
namespace Segura\AppCore\Abstracts;

use Segura\AppCore\Controllers\InlineCssTrait;
use Segura\AppCore\Exceptions\FilterDecodeException;
use Segura\AppCore\Filters\Filter;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class Controller
{
    use InlineCssTrait;

    /** @var Service */
    protected $service;
    /** @var bool */
    protected $apiExplorerEnabled = true;

    public function __construct()
    {
    }

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
    public function setService($service) : self
    {
        $this->service = $service;
        return $this;
    }

    /**
     * @return bool
     */
    public function isApiExplorerEnabled()  : self
    {
        return $this->apiExplorerEnabled;
    }

    /**
     * @param bool $apiExplorerEnabled
     */
    public function setApiExplorerEnabled(bool $apiExplorerEnabled) : self
    {
        $this->apiExplorerEnabled = $apiExplorerEnabled;
        return $this;
    }

    public function jsonResponse($json, Request $request, Response $response) : Response
    {
        return $response->withJson($json);
    }

    public function jsonResponseException(\Exception $e, Request $request, Response $response) : Response
    {
        return $this->jsonResponse(
            [
                'Status' => 'Fail',
                'Reason' => $e->getMessage(),
            ],
            $request,
            $response
        );
    }

    /**
     * Decide if a request has a filter attached to it.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @throws FilterDecodeException
     *
     * @return bool
     */
    protected function requestHasFilters(Request $request, Response $response) : bool
    {
        if ($request->hasHeader("Filter")) {
            $filterText = trim($request->getHeader('Filter')[0]);
            if (!empty($filterText)) {
                $decode = json_decode($filterText);
                if ($decode !== null) {
                    return true;
                }
                throw new FilterDecodeException("Could not decode given Filter. Reason: Not JSON. Given: \"" . $filterText . "\"");
            }
        }
        return false;
    }

    /**
     * Parse filters header into filter objects.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return Filter
     */
    protected function parseFilters(Request $request, Response $response) : Filter
    {
        $filter = new Filter();
        $filter->parseFromHeader(json_decode($request->getHeader('Filter')[0], true));

        return $filter;
    }
}
