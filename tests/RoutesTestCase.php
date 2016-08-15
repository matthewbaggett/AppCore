<?php
namespace Segura\AppCore\Test;

use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Response;
use Slim\Http\Uri;

abstract class RoutesTestCase extends BaseTestCase
{

    private $defaultEnvironment = [];
    private $defaultHeaders = [];

    public function setUp()
    {
        $this->defaultEnvironment = [
            'SCRIPT_NAME'    => '/index.php',
            'RAND'           => rand(0, 100000000),
        ];
        $this->defaultHeaders = [];
        parent::setUp();
    }

    protected function setEnvironmentVariable($key, $value)
    {
        $this->defaultEnvironment[$key] = $value;
        return $this;
    }

    protected function setRequestHeader($header, $value)
    {
        $this->defaultHeaders[$header] = $value;
        return $this;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $post
     * @param bool   $isJsonRequest
     *
     * @return Response
     */
    public function request(string $method, string $path, $post = null, $isJsonRequest = true)
    {
        /**
         * @var \Slim\App $app
         */
        $this->waypoint("Before App Fetch");
        $applicationInstance = $this->getApp();
        $this->waypoint("After App Fetch");
        $calledClass = get_called_class();

        if (defined("$calledClass")) {
            $modelName = $calledClass::MODEL_NAME;
            require(APP_ROOT . "/src/Routes/{$modelName}Route.php");
        } else {
            require(APP_ROOT . "/src/Routes.php");
        }
        if (file_exists(APP_ROOT . "/src/RoutesExtra.php")) {
            require(APP_ROOT . "/src/RoutesExtra.php");
        }
        $this->waypoint("Loaded Routes");

        $envArray = array_merge($this->defaultEnvironment, $this->defaultHeaders);
        $envArray = array_merge($envArray, [
            'REQUEST_URI'    => $path,
            'REQUEST_METHOD' => $method,
        ]);

        $env = Environment::mock($envArray);
        $uri     = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);

        $cookies      = [];
        $serverParams = $env->all();
        $body         = new RequestBody();
        if (!is_array($post) && $post != null) {
            $body->write($post);
            $body->rewind();
        } elseif (is_array($post) && count($post) > 0) {
            $body->write(json_encode($post));
            $body->rewind();
        }


        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);
        if ($isJsonRequest) {
            $request = $request->withHeader("Content-type", "application/json");
        }
        $this->waypoint("Before Response");
        $response = new Response();
        // Invoke app
        $response = $applicationInstance->getApp()->process($request, $response);
        #echo "\nRequesting {$method}: {$path} : ".json_encode($post) . "\n";
        #echo "Response: " . (string) $response->getBody()."\n";
        $this->waypoint("After Response");

        return $response;
    }
}
