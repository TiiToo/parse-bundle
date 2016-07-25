<?php

namespace Adadgio\ParseBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Adadgio\ParseBundle\Controller\BaseController as Controller;
use Adadgio\GearBundle\Component\Api\ApiRequest;
use Adadgio\GearBundle\Component\Api\ApiResponse;
use Adadgio\GearBundle\Component\Api\Annotation\Api;

use Adadgio\ParseBundle\Factory\ParseConfigFactory;

/**
 * @Route("/parse")
 */
class ConfigController extends Controller
{
    /**
     * @Route("/config")
     * @Method("GET")
     * @Api(method={"GET"}, security=true, type="static",
     *     with={"client_id"="x-parse-application-id","token"="x-parse-client-key"}
     * )
     */
    public function getAction(ApiRequest $request)
    {
        $config = $this->getParameter('adadgio_parse.config')['application']['client_config'];
        $config = ParseConfigFactory::createConfig($config);

        return $this->simpleResponse(array('params' => $config), 200);
    }
}
