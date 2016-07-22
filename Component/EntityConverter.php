<?php

namespace Adadgio\ParseBundle\Component;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Inflector;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Does extactly the inverse of Parse entity serializer (convert an array from parse data into an \Entity)
 */
class EntityConverter extends BaseSerializer
{
    /**
     * \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * Kernel root directory
     */
    private $rootDir;
    
    /**
     * Constructor
     */
    public function __construct(EntityManager $em, array $config, $kernelRootDir)
    {
        parent::__construct($config);

        $this->em = $em;
        $this->rootDir = $kernelRootDir;
    }

    /**
     * Hydrate entity with POST or PUT data.
     */
    public function hydrate($entity = null, array $data)
    {
        $classShortName = $this->getClassShortName($entity);
        $mapping = $this->getEntityMappingByClassName($classShortName);

        // if no base entity to start from is passed create a new one
        if (null === $entity) {
            $entity = $this->factoryCreateEntityClassInstance($classShortName);
        }

        // loop through each posted data
        $filteredData = array();

        foreach ($data as $prefixedFieldName => $value) {

            $fieldName = $this->unprefix($prefixedFieldName);

            if (in_array($fieldName, $this->reservedProperties)) {
                continue; // reserved properties can never never set
            }

            $isFieldMapped = $this->getMappedField($mapping['fields'], $fieldName);
            if (false === $isFieldMapped) {
                continue; // the field is not mapped in the config
            }

            // from the mapping, guess how to get the property value
            $mappedField = $mapping['fields'][$fieldName];

            // change the field name to match 360 sql field name if "inversedBy" property is defined
            if (null !== $mappedField['inversedBy']) {
                $fieldName = $mappedField['inversedBy'];
            }

            if ($mappedField['type'] === 'entity') {
                $entity = $this->setEntityFieldValue($entity, $fieldName, $value, $mappedField);

            } else if ($mappedField['type'] === 'file') {
                $entity = $this->setFileFieldValue($entity, $fieldName, $value, $mappedField);

            } else if ($mappedField['type'] === 'object') {
                throw new \Exception('The field type "object" for PUT or POST is not yet supported');
            } else {
                $entity = $this->setFieldValue($entity, $fieldName, $value, $mappedField);
            }
        }

        return $entity;
    }

    /**
     * Set string, integer or array values.
     *
     * @param object \Entity
     * @param string Final unprefixed field name
     * @param object Property field value
     * @return object \Entity
     */
    private function setFieldValue($entity, $fieldName, $value, $mappedField)
    {
        $set = $this->setterize($fieldName, $mappedField);

        if ($this->isSpecialOperation($value) && $this->specialOperationHas($value, 'objects')) {
            $value = $this->specialOperationGet($value, 'objects');
        }

        if (!method_exists($entity, $set)) {
            throw new \Exception(sprintf('Method "%s" does not exist for entity "%s". The field "%s"is not allowed.', $set, $classShortName, $fieldName));
        }

        $entity->$set($value);

        return $entity;
    }

    /**
     * Set a field of type file get file in server assets/temp directory and move it.
     *
     * @param object \Entity
     * @param string Final unprefixed field name
     * @param object MongoDB type ParseFile object JSON {"__type": "File","url":"...","name":"..."}
     * @return object \Entity
     */
    private function setFileFieldValue($entity, $fieldName, $fileObject, $mappedField)
    {
        // $supportedFieldNames = array('photo_id');
        $putMethod = $this->setterize($fieldName, $mappedField);

        // we only support a few fields (for users pictures)
        // if (false === in_array($fieldName, $supportedFieldNames)) {
        //     throw new \Exception(sprintf('This field [%s] for file type is not supported and cannot be set. Supported field names are [%s]', $fieldName, implode(',', $supportedFieldNames)));
        // }

        $entity = $this->moveAttachment($entity, $fileObject, $fieldName, $putMethod);// fieldName ex. "photo_id", putMethod: setMetaAttachment('photo_id', 'filepath')

        return $entity;
    }

    /**
     * Set a field of type entity (get related entity from repository and set relationship)
     *
     * @param object \Entity
     * @param string Final unprefixed field name
     * @param object Related entity id reference to look for with \Repository::find()
     * @return object \Entity
     */
    private function setEntityFieldValue($entity, $fieldName, $value, $mappedField)
    {
        $set = $this->setterize($fieldName);

        if (!method_exists($entity, $set)) {
            return $entity;
        }

        if ($this->isSpecialOperation($value)) {
            // the SDK sends a delete order which is not an id
            // in that case we want the value to be set to null
            $entity->$set(null);

        } else {
            // the its necessary to get the reference of the related entity from then Entity Manager
            // and the property as the related underlying object
            $repository = $this->em->getRepository('MedicalCoreBundle:'.ucfirst(strtolower($fieldName)));

            // either an "inversedLookup" repository method exists, either we use the default one
            if (null !== $mappedField['inversedRepositoryMethod']) {
                $repositoryMethod = $mappedField['inversedRepositoryMethod'];
                $relatedEntity = $repository->$repositoryMethod($value);
            } else {
                $relatedEntity = $repository->find($value);
            }

            $entity->$set($relatedEntity);
        }

        return $entity;
    }

    /**
     * Moves a file uploaded in the temp directory from parse and add it to the user
     *
     * @return object \Entity
     */
    private function moveAttachment($entity, $fileObject, $fieldName, $putMethod)
    {
        // get the file from the temporary directory
        $tmpPath = $this->rootDir. '/../web/assets/temp/'.$fileObject['name'];

        if (!is_file($tmpPath)) {
            // the uploaded file is not available any more (has already been moved)
            return $entity;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // create an uploaded file for the user
        $uploadedFile = new UploadedFile(
            $tmpPath, basename($tmpPath), $mimeType, filesize($tmpPath), null, true
        );

        // move the file to new location and update the user
        $entity
            ->$putMethod($fieldName, $uploadedFile)
        ;

        return $entity;
    }

    private function specialOperationHas($value, $key)
    {
        return isset($value[$key]);
    }

    private function specialOperationGet($value, $key)
    {
        return isset($value[$key]) ? $value[$key] : array();
    }

    private function isSpecialOperation($value)
    {
        return (is_array($value) && array_key_exists('__op', $value));
    }

}
