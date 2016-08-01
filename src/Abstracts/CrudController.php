<?php
namespace Segura\AppCore\Abstracts;

use Slim\Http\Request;
use Slim\Http\Response;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Segura\AppCore\Exceptions\TableGatewayException;

abstract class CrudController extends Controller
{
    public function listRequest(Request $request, Response $response, $args)
    {
        $objects = [];
        foreach ($this->service->getAll() as $object) {
            $objects[] = $object->__toArray();
        }

        return $this->jsonResponse(
            [
                'Status'                        => 'OKAY',
                $this->service->getTermPlural() => $objects,
            ],
            $request,
            $response
        );
    }

    public function getRequest(Request $request, Response $response, $args)
    {
        try {
            $object = $this->service->getById($args['id'])->__toArray();

            return $this->jsonResponse(
                [
                    'Status'                          => 'OKAY',
                    $this->service->getTermSingular() => $object,
                ],
                $request,
                $response
            );
        } catch (TableGatewayException $tge) {
            return $this->jsonResponseException($tge, $request, $response);
        }
    }

    public function createRequest(Request $request, Response $response, $args)
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $object = $this->service->createFromArray($newObjectArray);
            return $this->jsonResponse(
                [
                    'Status'                          => 'OKAY',
                    $this->service->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        } catch (InvalidQueryException $iqe) {
            return $this->jsonResponseException($iqe, $request, $response);
        }
    }

    public function deleteRequest(Request $request, Response $response, $args)
    {
        try {
            $object = $this->service->getById($args['id'])->__toArray();
            $this->service->deleteByID($args['id']);
            return $this->jsonResponse(
                [
                    'Status'                          => 'OKAY',
                    $this->service->getTermSingular() => $object,
                ],
                $request,
                $response
            );
        } catch (TableGatewayException $tge) {
            return $this->jsonResponseException($tge, $request, $response);
        }
    }
}
