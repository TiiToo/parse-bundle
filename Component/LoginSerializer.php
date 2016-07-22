<?php

namespace Adadgio\ParseBundle\Component;

use Doctrine\Common\Inflector;
use Adadgio\ParseBundle\Factory\ParseSessionFactory;

class LoginSerializer extends EntitySerializer
{
    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }
    
    /**
     * Serializes one or more entities.
     *
     * @param mixed Input can be an array, an ArrayCollection or one entity.
     */
    public function serialize($user, $isoTypeHinting = false)
    {
        $serializedUser = $this->serializeObject($user);

        $serializedUser['sessionToken'] = ParseSessionFactory::createToken($user);
        $serializedUser['m_enabled'] = (boolean) $user->getEnabled();

        return $serializedUser;
    }
}
