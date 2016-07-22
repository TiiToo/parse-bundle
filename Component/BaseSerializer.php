<?php

namespace Adadgio\ParseBundle\Component;

use Doctrine\Common\Inflector;
use Doctrine\Common\Collections\ArrayCollection;
use Adadgio\ParseBundle\Factory\ParsePointerFactory;

class BaseSerializer
{
    /**
     * @var array  Configured parse entity(ies) mapping
     */
    protected $mapping;

    /**
     * @var array Which fields must never be prefixed
     */
    protected $neverPrefixed;

    /**
     * @var array List of fields to be hidden when serializing a parse
     * object returned from the API to the SDKs
     */
    protected $hiddenProperties;

    /**
     * @var array Reserved properties to prevent parse PUT|POST from updating those fields.
     */
    protected $reservedProperties;

    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        $this->mapping = $config['mapping'];
        $this->prefix = $config['settings']['field_prefix'];
        $this->neverPrefixed = $config['settings']['never_prefixed'];
        $this->hiddenProperties = $config['settings']['serialization']['hidden'];
        $this->reservedProperties = $config['settings']['conversion']['reserved'];
    }

    /**
     * Form some classes the prefix is not needed so
     * we give the ability to set our own (like null))
     *
     * @param string Custom prefix that overrides config prefix value
     * @return \ParseEntityConverter
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Prefix an un-prefixed field name.
     *
     * @param string Field name (un-prefixed)
     * @return string Field name (prefixed)
     */
    public function prefix($fieldName)
    {
        if (in_array($fieldName, $this->neverPrefixed) OR $this->prefix === null) {
            return $fieldName;
        } else {
            return $this->prefix.strtolower($fieldName);
        }
    }

    /**
     * Un-prefix a prefixed field name.
     *
     * @param string Field name (prefixed)
     * @return string Field name (un-prefixed)
     */
    public function unprefix($fieldName)
    {
        if (null === $this->prefix) {
            return $fieldName;
        } else {
            return str_replace($this->prefix, '', $fieldName);
        }
    }

    /**
     * From a field name, return a getter method.
     *
     * @return string
     */
    protected function getterize($name, $field)
    {
        // if the field has a "method" provided, we use it otherwise fallback to default Doctrine getter from field name.
        if (!empty($field['method'])) {
            return $field['method']; // method can hypotetically forward to user reflection of static classes
        } else {
            return 'get'.Inflector\Inflector::classify($name);
        }
    }

    /**
     * Guess the setter for a Medical\..\Entity based on the non-prefixed field name.
     *
     * @return string
     */
    protected function setterize($unprefixedFieldname, $field = null)
    {
        // overrite setter method eventually
        if (null !== $field && !empty($field['putMethod'])) {

            return $field['putMethod'];

        } else {
            return 'set'.Inflector\Inflector::classify($unprefixedFieldname);
        }
    }

    /**
     * Get entity class short name.
     *
     * @param object \Class
     * @return string Reflected class short name
     */
    protected function getClassShortName($entity)
    {
        $reflection = new \ReflectionClass($entity);
        return $reflection->getShortName();
    }

    /**
     * Get entity mapping (look for this entity mapping).
     *
     * @param array Mapping for this entity ($mapping[$classShortName]['fields'])
     * @param string Field name (un-prefixed)
     * @return mixed
     */
    protected function getMappedField($mapping, $fieldName)
    {
        return isset($mapping[$fieldName]) ? $mapping[$fieldName] : false;
    }

    /**
     * Get mapping fo an entity from the class.
     *
     * @param string Entity class short name
     * @return array Configured mapping for the entity
     */
    protected function getEntityMappingByEntity($entity)
    {
        return $this->getEntityMappingByClassName(
            $this->getClassShortName($entity)
        );
    }
    
    /**
     * Get mapping fo an entity from its class name.
     *
     * @param string Entity class short name
     * @return array Configured mapping for the entity
     */
    protected function getEntityMappingByClassName($classShortName)
    {
        $classShortName = strtolower($classShortName);

        if (!isset($this->mapping[$classShortName])) {
            throw new \Exception(sprintf('No mapping found in your config for class "%s"', $classShortName));
        }

        return $this->mapping[$classShortName];
    }

    /**
     * Creates a new \Entity class instance from a classname.
     *
     * @param string Class short name
     * @return object New class instance
     */
    protected function factoryCreateEntityClassInstance($classShortName)
    {
        $reflection = new \ReflectionClass('Medical\CoreBundle\Entity\\' . $classShortName);

        return $reflection->newInstance();
    }

    /**
     * Test if the given URL is absolute or relative.
     *
     * @param string Url
     * @return boolean
     */
    protected function isAbsoluteUrl($url)
    {
        if (strpos($url, 'http') === 0 OR strpos($url, 'ftp') === 0 OR strpos($url, 'www') === 0) {
            return true;
        } else {
            return false;
        }
    }
}
