<?php

namespace Segura\AppCore\Monolog;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\JsonFormatter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class LumberjackHandler extends AbstractProcessingHandler
{
    protected $url;
    protected $apiKey;
    protected $client;
    protected $options = [];

    public function __construct(string $url, string $apiKey, $level = Logger::WARNING, $bubble = true, array $options = [])
    {
        parent::__construct($level, $bubble);
        $this->client = new Client();

        $this->url    = $url;
        $this->apiKey = $apiKey;

        $this->setOptions($options);
    }

    public function setOptions($options)
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function write(array $record)
    {
        $json = [
            'applicationKey' => $this->apiKey,
            'logLevel'       => $record['level'],
            'message'        => $record['message'],
            'data'           => null,
            'hostname'       => gethostname(),
        ];

        $promise = $this->client->sendAsync(
            new Request(
                'PUT',
                $this->url,
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                json_encode($json)
            ),
            $this->options
        );

        $promise->wait();
    }

    protected function getDefaultFormatter()
    {
        return new JsonFormatter();
    }
}