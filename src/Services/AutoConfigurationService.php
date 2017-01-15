<?php
namespace Segura\AppCore\Services;

use Monolog\Logger;
use Predis\Client;
use SebastianBergmann\Diff\Differ;
use Segura\Session\Session;

class AutoConfigurationService
{
    /** @var EnvironmentService */
    protected $environmentService;

    /** @var \GuzzleHttp\Client */
    protected $guzzleClient;

    public function __construct(
        \GuzzleHttp\Client $guzzleClient
    )
    {
        $this->guzzleClient = $guzzleClient;
    }

    public function setEnvironmentService(
        EnvironmentService $environmentService
    ){
        $this->environmentService = $environmentService;
    }

    public function isGondalezConfigurationPresent()
    {
        return $this->environmentService->isSet("GONDALEZ_HOST")
            && $this->environmentService->isSet("GONDALEZ_API_KEY");
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        $getConfigurationUrl = $this->environmentService->get("GONDALEZ_HOST") . "/v1/whoami/" . $this->environmentService->get("GONDALEZ_API_KEY");
        $response = $this->guzzleClient->get($getConfigurationUrl);
        \Kint::dump(
            $getConfigurationUrl,
            $response->getStatusCode(),
            $response->getBody()->getContents()
        );
        exit;
    }
}