<?php

namespace Adadgio\ParseBundle\Factory;

use Adadgio\ParseBundle\Factory\ParseDateFactory;

class ParseObjectFactory
{
    const NUMBER_OF_LETTERS = 3;

    /**
     * Creates the objectId.
     *
     * @param object \Entity
     */
    public static function createObject($entity)
    {
        $createdAt = "2015-12-01T01:01:01.000Z";
        $updatedAt = ParseDateFactory::createDate();

        // these are ISO field needed by parse SDK
        return array(
            'objectId'  => self::createObjectId($entity), // 7ZWXZ0Pfe1
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt,
        );
    }

    /**
     * Add ISO "__type" and "className" needed to return Parse query results.
     * There is no need for that when returning a single object (see the parse entity serializer to where it is used)
     */
    public static function typeHint($entity, array $parseObject)
    {
        $parseObject['__type'] = 'Object';
        $parseObject['className'] = self::getClassName($entity);

        return $parseObject;
    }

    /**
     * Get object class name from entity.
     *
     * @return string
     */
    public static function getClassName($entity)
    {
        $reflection = new \ReflectionClass($entity);

        return $reflection->getShortname();
    }
    
    /**
     * Creates the objectId.
     *
     * @param object \Entity
     */
    public static function createObjectId($entity)
    {
        $prefix = 'o';
        $suffix = 'x';

        $entityId = $entity->getId();
        $entityThreeFirstLetters = substr(self::getClassName($entity), 0, static::NUMBER_OF_LETTERS);

        return $prefix.$entityThreeFirstLetters.$entityId.$suffix;
    }

    /**
     * Guesses the real id from object id.
     *
     * @param object \Entity
     */
    public static function getIdFromObjectId($className, $objectId)
    {
        $prefix = 'o';
        $suffix = 'x';

        $className = str_replace('_', '', $className); // \User class at 360 medical does not have an underscore

        $entityThreeFirstLetters = substr($className, 0, static::NUMBER_OF_LETTERS);
        $id = str_replace($suffix, '', str_replace($prefix.$entityThreeFirstLetters, '', $objectId));

        return (int) $id;
    }
}
