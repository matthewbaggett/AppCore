<?php
namespace Segura\AppCore\Abstracts;

use Segura\AppCore\Interfaces\ModelInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Zend\Db\Adapter\Exception\InvalidQueryException;

abstract class CrudController extends Controller
{
    public function listRequest(Request $request, Response $response, $args)
    {
        $objects = [];
        $service = $this->getService();
        if ($this->requestHasFilters($request, $response)) {
            $filterBehaviours = $this->parseFilters($request, $response);
            $foundObjects     = $service->getAll(
                $filterBehaviours->getLimit(),
                $filterBehaviours->getOffset(),
                $filterBehaviours->getWheres(),
                $filterBehaviours->getOrder(),
                $filterBehaviours->getOrderDirection()
            );
        } else {
            $foundObjects = $service->getAll();
        }

        foreach ($foundObjects as $object) {
            $objects[] = $object->__toPublicArray();
        }

        return $this->jsonResponse(
            [
                'Status'                        => 'OKAY',
                'Action'                        => 'LIST',
                $this->service->getTermPlural() => $objects,
            ],
            $request,
            $response
        );
    }

    public function getRequest(Request $request, Response $response, $args)
    {
        $object = $this->getService()->getById($args['id']);
        if ($object) {
            return $this->jsonResponse(
                [
                    'Status'                          => 'OKAY',
                    'Action'                          => 'GET',
                    $this->service->getTermSingular() => $object->__toArray(),
                ],
                $request,
                $response
            );
        }
        return $this->jsonResponse(
                [
                    'Status'                          => 'FAIL',
                    'Reason'                          => sprintf(
                        "No such %s found with id %s",
                        strtolower($this->service->getTermSingular()),
                        $args['id']
                    )
                ],
                $request,
                $response
            );
    }

    public function createRequest(Request $request, Response $response, $args)
    {
        $newObjectArray = $request->getParsedBody();
        try {
            $object = $this->getService()->createFromArray($newObjectArray);
            return $this->jsonResponse(
                [
                    'Status'                          => 'OKAY',
                    'Action'                          => 'CREATE',
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
        /** @var ModelInterface $object */
        $object = $this->getService()->getById($args['id']);
        if ($object) {
            $array = $object->__toArray();
            $object->destroy();

            return $this->jsonResponse(
                [
                    'Status'                          => 'OKAY',
                    'Action'                          => 'DELETE',
                    $this->service->getTermSingular() => $array,
                ],
                $request,
                $response
            );
        }
        return $this->jsonResponse(
                [
                    'Status'                          => 'FAIL',
                    'Reason'                          => sprintf(
                        "No such %s found with id %s",
                        strtolower($this->service->getTermSingular()),
                        $args['id']
                    )
                ],
                $request,
                $response
            );
    }
}
