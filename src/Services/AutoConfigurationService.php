<?php
namespace Gone\AppCore\Services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Gone\AppCore\Exceptions\AutoConfigurationException;
use Gone\AppCore\Exceptions\TemporaryAutoConfigurationException;

class AutoConfigurationService
{
    /** @var EnvironmentService */
    protected $environmentService;

    /** @var \GuzzleHttp\Client */
    protected $guzzleClient;

    public function __construct(
        \GuzzleHttp\Client $guzzleClient
    ) {
        $this->guzzleClient = $guzzleClient;
    }

    public function setEnvironmentService(
        EnvironmentService $environmentService
    ) {
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
        try {
            $response = $this->guzzleClient->get(
                $getConfigurationUrl,
                [
                    "headers" => [
                        "Accept" => "application/json"
                    ]
                ]
            );
        } catch (ClientException $clientException) {
            throw new AutoConfigurationException("Cannot connect to Gondalez. Got status code \"{$clientException->getResponse()->getStatusCode()}\" . Got guzzle client exception: {$clientException->getMessage()}");
        } catch (ConnectException $connectException) {
            throw new TemporaryAutoConfigurationException("Cannot currently contact Gondalez.");
        }
        if ($response->getStatusCode() !== 200) {
            throw new AutoConfigurationException("Cannot connect to Gondalez. Got status code \"{$response->getStatusCode()}\"");
        }
        $responseBody = $response->getBody()->getContents();
        return $this->parseConfiguration($responseBody);
    }

    public function run()
    {
        $this->environmentService->clearCache();
        return $this->environmentService->rebuildEnvironmentVariables();
    }

    private function parseConfiguration($responseBody)
    {
        $json = json_decode($responseBody, true);
        return $json['Service']['Config'];
    }
}
