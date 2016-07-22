<?php

namespace Adadgio\ParseBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Parse base controller.
 * Error codes info {@link https://parse.com/docs/dotnet/api/html/T_Parse_ParseException_ErrorCode.htm}
 */
class BaseController extends Controller
{
    protected $em;
    protected $serializer;
    protected $converter;
    protected $validator;
    protected $userManager;
    protected $loginSerializer;

    const HTTP_OK = 200;
    const NOT_FOUND = 404;
    const BAD_REQUEST = 400;

    const PARSE_NOT_FOUND           = 101;
    const PARSE_INVALID_QUERY       = 102;
    const PARSE_INVALID_CLASSNAME   = 103;
    const PARSE_MISSING_OBJECT_ID   = 104;
    const PARSE_INVALID_KEYNAME     = 105;
    const PARSE_INVALID_JSON        = 107;
    const PARSE_INVALID_EMAIL       = 125;
    const PARSE_INCORRECT_TYPE      = 111;
    const PARSE_EMAIL_TAKEN         = 203;
    const PARSE_EMAIL_NOT_FOUND     = 205;
    const PARSE_VALIDATION_FAILED   = 142;
    const PARSE_FORBIDDEN           = 119;
    const PARSE_OBJECT_TOOLARGE     = 116;
    const PARSE_INTERNAL_ERROR      = 1;
    const PARSE_OTHER_CAUSE         = -1;

    const DEFAULT_LIMIT = 99;

    /**
     * Inject dependencies we use everywhere.
     */
    protected function injectDependencies()
    {
        $this->validator = $this->get('validator');
        $this->em = $em = $this->getDoctrine()->getManager();
        $this->userManager = $this->get('fos_user.user_manager');
        $this->serializer = $this->get('adadgio.rocket.parse.entity_serializer');
        $this->converter = $this->get('adadgio.rocket.parse.entity_converter');
        $this->loginSerializer = $this->get('adadgio.rocket.parse.login_serializer');
    }

    /**
     * Return an abstract class repository for the CoreBundle.
     *
     * @return object \RepositoryInterface
     */
    protected function getAbstractRepository($className)
    {
        return $this->em->getRepository('MedicalCoreBundle:' . $className);
    }

    /**
     * Create abstract class instance.
     *
     * @return object \Class
     */
    protected function newAbstractInstance($className)
    {
        $reflection = new \ReflectionClass('Medical\CoreBundle\Entity\\' . $className);
        return $reflection->newInstance();
    }

    /**
     * Return one object JSON directly in the response body.
     *
     * @return \Response
     */
    protected function signupResponse($api, $object)
    {
        return $api->response(array('objectId' => $object['objectId'], 'createdAt' => $this->justNow(), 'sessionToken' => $object['sessionToken']), static::HTTP_OK);
    }

    /**
     * Return one object JSON directly in the response body.
     *
     * @return \Response
     */
    protected function objectResultResponse($api, $object)
    {
        return $api->response($object, static::HTTP_OK);
    }

    /**
     * Return one object JSON directly in the response body.
     *
     * @return \Response
     */
    protected function createdAtResponse($api, $object)
    {
        return $api->response(array('objectId' => $object['objectId'], 'createdAt' => $this->justNow()), static::HTTP_OK);
    }

    /**
     * Return one object JSON directly in the response body.
     *
     * @return \Response
     */
    protected function updatedAtResponse($api)
    {
        return $api->response(array('updatedAt' => $this->justNow()), static::HTTP_OK);
    }

    /**
     * Return an array of results object(s) in the response body.
     *
     * @return \Response
     */
    protected function queryResultsResponse($api, array $results)
    {
        return $api->response(array('results' => $results), static::HTTP_OK);
    }

    /**
     * Returns a cloud response always encapsulated in a "result" body (warning, no "s"!).
     *
     * @return \Response
     */
    protected function cloudResultResponse($api, $result)
    {
        return $api->response(array('result' => $result), static::HTTP_OK);
    }

    protected function emailNotFoundException($api)
    {
        return $api->response(array('code' => static::PARSE_EMAIL_NOT_FOUND, 'error' => 'email not found'), static::HTTP_OK);
    }

    /**
     * @return \Response
     */
    protected function invalidEndpointException($api)
    {
        return $api->response(array('code' => static::PARSE_NOT_FOUND, 'error' => 'invalid API endpoint'), static::HTTP_OK);
    }

    /**
     * @return \Response
     */
    protected function invalidMethodException($api, $requiredMethod)
    {
        return $api->response(array('code' => static::PARSE_NOT_FOUND, 'error' => 'invalid method, must be '.$requiredMethod), static::HTTP_OK);
    }

    /**
     * @return \Response
     */
    protected function invalidLoginParameters($api)
    {
        return $api->response(array('code' => static::PARSE_NOT_FOUND, 'error' => 'invalid login parameters'), static::NOT_FOUND);
    }

    /**
     * @return \Response
     */
    protected function notFoundException($api)
    {
        return $api->response(array('code' => static::PARSE_NOT_FOUND, 'error' => 'object not found'), static::HTTP_OK);
    }

    /**
     * @return \Response
     */
    protected function invalidQueryException($api, $message = 'invalid query parameters')
    {
        return $api->response(array('code' => static::PARSE_INVALID_QUERY, 'error' => $message), static::HTTP_OK);
    }

    /**
     * @return \Response
     */
    protected function fileUploadException($api, $message = 'could not save remote file')
    {
        return $api->response(array('code' => static::PARSE_OTHER_CAUSE, 'error' => $message), static::HTTP_OK);
    }

    /**
     * Entity validation violation exception
     */
    protected function violationException($api, $message)
    {
        return $api->response(array('code' => static::PARSE_INCORRECT_TYPE, 'error' => $message), static::BAD_REQUEST); // signup error
    }

    /**
     * User post (signup) validation violation exception
     */
    protected function signupViolationException($api, $message)
    {
        return $api->response(array('code' => static::PARSE_INCORRECT_TYPE, 'error' => $message), static::BAD_REQUEST); // signup error
    }

    /**
     * Get violation list first User entity violation.
     *
     * @param \ConstraintViolationListInterface
     * @return string Readable error message for the violation
     */
    protected function getFirstViolationMessage(\Symfony\Component\Validator\ConstraintViolationListInterface $violationList)
    {
        $errorString = null;

        foreach ($violationList as $violation) {
            $fieldName = $violation->getPropertyPath();
            $invalidValue = $violation->getInvalidValue();

            $errorString = $violation->getMessage();
            continue;
        }

        return $errorString;
    }

    /**
     * Returns a datetime in the parse format.
     *
     * @return string
     */
    protected function justNow()
    {
        $datetime = new \Datetime();
        return $datetime->format(\DateTime::ISO8601);
    }
}
