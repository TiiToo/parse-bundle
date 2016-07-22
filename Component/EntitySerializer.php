<?php

namespace Adadgio\ParseBundle\Component;

use Doctrine\Common\Inflector;
use Doctrine\Common\Collections\ArrayCollection;

use Adadgio\ParseBundle\Factory\ParseObjectFactory;
use Adadgio\ParseBundle\Factory\ParsePointerFactory;
use Adadgio\ParseBundle\Component\ParallelHydrator;

class EntitySerializer extends BaseSerializer
{
    const ISO_QUERY_OBJECT_TYPE_HINTING = true;
    const ISO_QUERY_OBJECT_NOT_TYPE_THINTING = true;
    
    /**
     * App url: domain protocol + hostname
     */
    protected $url;

    protected $parallelHydrator;

    /**
     * List of unprefixed field names that we should see as whole serialized entity instead of pointers
     */
    protected $includes = array();

    /**
     * Constructor
     */
    public function __construct(array $config, ParallelHydrator $parallelHydrator = null)
    {
        parent::__construct($config);

        $this->url = $config['miscellaneous']['protocol'].$config['miscellaneous']['hostname'];
        $this->parallelHydrator = $parallelHydrator;
    }

    public function setIncludes(array $includes = array())
    {
        $this->includes = $includes;

        return $this;
    }

    /**
     * Serializes one or more entities.
     *
     * @param mixed Input can be an array, an ArrayCollection or one entity.
     * @param boolean Whethere to serialize children related objects deeply
     * @param array include, List of unprefixed fields for relations, whether the relation should include the whole object or just the pointer for that field relationsuip
     */
    public function serialize($input, $isoTypeHinting = false)
    {
        if (is_array($input) OR $input instanceof ArrayCollection) {
            $output = array();

            foreach ($input as $entity) {
                $output[] = $this->serializeObject($entity, static::ISO_QUERY_OBJECT_TYPE_HINTING);
            }
        } else {
            $output = $this->serializeObject($input, $isoTypeHinting);
        }

        return $output;
    }

    /**
     * Serialize a single \Entity
     *
     * @param object \Entity
     * @param boolean Cast type relations and files to mongoDB pointer formats
     * @param boolean Allow children to be serialized as well
     * @return array Serialized objects the mongDB way
     */
    protected function serializeObject($entity, $isoTypeHinting = false, array $without = array())
    {
        $entityMapping = $this->getEntityMappingByEntity($entity);

        // initizalize the ParseObject with and objectId
        $parseObject = ParseObjectFactory::createObject($entity);

        // loop through mapping and analyse field type and add properties to the ParseObject
        foreach ($entityMapping['fields'] as $name => $field) {

            if (in_array($name, $this->hiddenProperties) OR in_array($name, $without)) {
                continue;
            }

            // serializing many object(s) is always the result of a Parse query, and it
            // required to add two more field to the JSON object ("__type" and "className")
            if ($isoTypeHinting === static::ISO_QUERY_OBJECT_TYPE_HINTING) {
                $parseObject = ParseObjectFactory::typeHint($entity, $parseObject);
            }

            $parseFieldName = $this->getParseFieldName($name, $field);
            $parseFieldValue = $this->getParseFieldValue($name, $field, $entity);

            $parseObject[$parseFieldName] = $parseFieldValue;
        }

        return $parseObject;
    }

    /**
     * Get final futur parse field value to be returned
     */
    protected function getParseFieldName($name, $field)
    {
        if (null === $field['name']) {
            return $this->prefix($name);
        } else {
            return $this->prefix($field['name']);
        }
    }

    /**
     * Get final parse field name.
     */
    protected function getParseFieldValue($name, $field, $entity)
    {
        $getMethod = 'get'.ucfirst($field['type']).'FieldValue';

        return $this->$getMethod($name, $field, $entity);
    }

    /**
     * Get field value from its type.
     */
    protected function getStringFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            return $entity->$getter($field['arg']);
        }

        return $entity->$getter();
    }

    /**
     * Get field value from its type.
     */
    protected function getDateFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        $datetime = $entity->$getter();

        if ($datetime instanceof \Datetime) {
            return $datetime->format('Y-m-d H:i:s');
        } else {
            return null;
        }
    }

    /**
     * Get field value from its type.
     */
    protected function getLinkFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            $link = $entity->$getter($field['arg']);
        } else {
            $link = $entity->$getter();
        }

        if (empty($link)) {
            return null;
        }

        return $link;
    }

    /**
     * Get field value from its type.
     */
    protected function getUrlFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        $url = $entity->$getter();

        if (empty($url)) {
            return null;
        }

        if (false === $this->isAbsoluteUrl($url)) {
            return $this->url.'/'.trim($url, '/');
        } else {
            return $url;
        }
    }

    /**
     * Get field value from its type.
     */
    protected function getIntegerFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            return $entity->$getter($field['arg']);
        }

        return $entity->$getter();
    }

    /**
     * Get field value from its type.
     */
    protected function getArrayFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            return $entity->$getter($field['arg']);
        }

        return $entity->$getter();
    }

    /**
     * Get field value from its type.
     */
    protected function getObjectFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        $object = $entity->$getter();

        if (null !== $object) {
            $without = $field['without']; // prevent fetching nested children again !! ex. array('tocs', 'related_docs');
            $object = $this->serializeObject($object, static::ISO_QUERY_OBJECT_TYPE_HINTING, $without);
        }

        return $object;
    }

    /**
     * Get field value from its type but guess the type.
     *
     * @return mixed
     */
    protected function getAutoFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            return $entity->$getter($field['arg']);
        }

        return $entity->$getter();
    }

    /**
     * Get entity (relation) value from its type but guess the type.
     *
     * @return mixed
     */
    protected function getEntityFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            return $entity->$getter($field['arg']);
        }

        return $entity->$getter();
    }

    /**
     * Get entity file accessible web asset.
     */
    protected function getFileFieldValue($name, $field, $entity)
    {
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            $fileWebpath = $entity->$getter($field['arg']);
        } else {
            $fileWebpath = $entity->$getter();
        }

        if (!empty($fileWebpath)) {
            $fileWebpath = $this->url.$fileWebpath;
            return ParseFileFactory::createFileObject($fileWebpath);
        } else {
            return null;
        }
    }

    public function executeParallelHydration($class, $includes, $collection)
    {
        $mapping = $this->getEntityMappingByClassName($class);

        foreach ($includes as $include) {
            $field = $mapping['fields'][$include];

            $method = $field['parallelHydrationMethod'];
            if (null === $method) {
                continue;
            }

            $this->parallelCollections[$include] = $this->parallelHydrator->$method($collection);
        }
    }

    /**
     * Get entity relationship value.
     */
    protected function getRelationFieldValue($name, $field, $entity)
    {
        $pointers = array();

        // check if a parallel collection exists
        if (isset($this->parallelCollections[$name])) {

            // look for the parallel collection item (because parallell collections are indexed by entity id(s))
            // it means that relationships exists for this entity in the parallell collection
            $entityId = $entity->getId();
            if (isset($this->parallelCollections[$name][$entityId])) {

                // guess which setter will be used..
                $setRelationship = $field['parallelHydrationSetter']; // ex addRelDocs

                foreach ($this->parallelCollections[$name][$entityId] as $parallelCollectionItem) {
                    // set the relationships for the entity
                    $entity->$setRelationship($parallelCollectionItem);
                }
            }
        }

        // now if entity was properly hydrated using parallel hydration the getters
        // will hopefully wont be lazy-loading all the related children entities
        // but STILL DONT FORGET to use ->setInitialized(true) inside your entity relationship getter (ex getParseRelatedDocuments)
        $getter = $this->getterize($name, $field);

        if ($getter === 'useReflection') {
            $children = $entity->$getter($field['arg']);
        } else {
            $children = $entity->$getter();
        }

        // now create the pointers or put related objects inside the parent entity
        $pointers = array();
        foreach ($children as $child) {
            // if the field name is in the include list, we serialize the whole entity
            // instead if simply creating the pointer
            if (in_array($name, $this->includes)) { // un-prefixed fields
                $pointers[] = $this->serializeObject($child, static::ISO_QUERY_OBJECT_TYPE_HINTING); // add "__type"
            } else {
                $pointers[] = ParsePointerFactory::createPointer($child); // pointer automaticall has a "__type":" pointer"
            }
        }

        return $pointers;
    }
}
