<?php

namespace Adadgio\ParseBundle\Component;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Inflector;

use Adadgio\DoctrineDQLBundle\DQL\Limit;
use Adadgio\DoctrineDQLBundle\DQL\Offset;
use Adadgio\DoctrineDQLBundle\DQL\OrderBy;
use Adadgio\DoctrineDQLBundle\DQL\Where;
use Medical\CoreBundle\Component\ORM\Enum\DocumentCategorizer;

use Adadgio\ParseBundle\Factory\ParseObjectFactory;
use Adadgio\ParseBundle\Factory\ParseObjectIdFactory;

use Adadgio\ParseBundle\Component\Utility\Decoder;
use Adadgio\ParseBundle\Component\Utility\AliasHelper;
use Adadgio\ParseBundle\Component\Utility\ParseExpr;

/**
 * Analyses and perses parse queries passed in the
 * request body in a MongodDB style (by SDKs)
 */
class QueryComposer
{
    /**
     * \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var string Class shortname
     * @var [type]
     */
    private $class;

    /**
     * @var Current repository
     */
    private $respository;

    /**
     * @var array Bundle configuraiton nodes.
     */
    private $config;

    /**
     * Parse mapping
     */
    private $mapping;

    /**
     * Fields prefix
     */
    private $prefix;

    /**
     * @var array
     */
    private $where;

    /**
     * @var array Query parameters ready to use in Doctrine DQL
     */
    private $params;

    /**
     * @var integer
     */
    private $limit;

    /**
     * @var integer
     */
    private $offset;

    /**
     * @var ?
     */
    private $orderBy;

    /**
     * @var array relationships inclusions
     */
    private $includes;

    /**
     * @var array Possible combinators for complex queries
     */
    private $combinators = array('$or');

    /**
     * Constructor.
     */
    public function __construct(EntityManager $em = null, array $config)
    {
        $this->em = $em;

        $this->offset = 0;
        $this->limit = 1000;
        $this->where = array();
        $this->orderBy =  array();
        
        $this->includes = array();
        $this->config = $config;
        $this->mapping = $config['mapping'];
        $this->prefix = $config['settings']['field_prefix'];
    }

    /**
     * Get where.
     *
     * @return array
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * Get limit.
     *
     * @return integer
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Get limit.
     *
     * @return integer
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Get includes.
     *
     * @return array
     */
    public function getIncludes()
    {
        return $this->includes;
    }

    /**
     * Get order by
     *
     * @return array
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    public function getResult()
    {
        $this->repository = $this->getAbstractRepository($this->class);

        // test if there is a better method for the repository in the config...
        if (!empty($this->mapping[$this->class]['query_optimizer']['method'])) {

            $repositoryMethod = $this->mapping[$this->class]['query_optimizer']['method'];

            return $this->makeOptimizedQuery($repositoryMethod);

        } else {

            return $this->makeDefaultQuery();
        }
    }

    /**
     * Perform a default query with parameters from an abstract repository.
     */
    private function makeDefaultQuery()
    {
        $builder = $this->repository
            ->createQueryBuilder('e')
            ->select('e')
            ->setMaxResults($this->getLimit())
        ;

        // handle where conditions
        $builder = (new Where('e', $this->getWhere()))->digest($builder);
        // $builder = Where::andWhere('e', $builder, $this->getWhere());

        // handle limit and /offset
        $builder->setMaxResults(Limit::enforce($this->getLimit())); // prevents too high limits event if > 1000

        // handle order by
        $builder = OrderBy::orderBy('e', $builder, $this->getOrderBy());

        // handle offset
        $builder->setFirstResult(Offset::offset($this->getOffset()));

        return $builder
            ->getQuery()
            ->getResult();
    }

    /**
     * Perform an optimized query defined in the config
     */
    private function makeOptimizedQuery($repositoryMethod)
    {

        return $this->repository->$repositoryMethod($this->getWhere(), $this->getOrderBy(), $this->getLimit(), $this->getOffset());
    }

    /**
     * Creates and analyze queries from POST body parameters
     *
     * @param string or array Decoded body or not
     */
    public function createFromParameters(array $where, $class = null)
    {
        $fakeRequestBody = json_encode($where);
        $this->createFromRequestBody($fakeRequestBody, $class);
        // $this->class = $class;
    }

    /**
     * Creates and analyze queries from POST body parameters
     *
     * @param string or array Decoded body or not
     */
    public function createFromRequestBody($requestBody, $class = null)
    {
        $this->class = $class;

        // Decoder is smart and wont decode arrays or objects
        $decoded = Decoder::decode($requestBody);

        if (isset($decoded['where'])) {
            $decoded['where'] = Decoder::decode($decoded['where']); // try decodeing again for substrings 'where' => "{\"objectId\":\"oDoc11x\"}",

            // special values for getting only one object
            if (isset($decoded['where']['objectId'])) {
                $this->addLimit(1);
                $this->addWhereByObjectId($decoded['where']['objectId'], $this->class);
                return $this;

            } else {
                $this->addWhere($decoded['where']);
            }
        }

        if (isset($decoded['limit'])) {
            $this->addLimit($decoded['limit']);
        }

        if (isset($decoded['order'])) {
            $this->addOrder($decoded['order']);
        }

        if (isset($decoded['skip'])) {
            $this->addOffset($decoded['skip']);
        }

        if (isset($decoded['include'])) {
            if (is_array($decoded['include'])) {
                foreach ($decoded['include'] as $includedField) {
                    $this->includes[] = $this->unprefix($includedField);
                }
            } else {
                $this->includes[] = $this->unprefix($decoded['include']);
            }
        }

        // @todo Fix MB01 (Thomas Besnehard mobile bug)
        // if ($class === 'Document') {
        //     $this->where['category'] = DocumentCategorizer::getCategory('MEDICINE', DocumentCategorizer::KEY);
        // }

        return $this;
    }

    /**
     * Handle query parts like {"limit":"4"}
     */
    private function addLimit($limit)
    {
        $this->limit = (int) $limit;
    }

    /**
     * Handle query parts like {"offset":"4"}
     */
    private function addOffset($offset)
    {
        $this->offset = (int) $offset;
    }

    /**
     * Handle where expresison from an object id
     */
    private function addWhereByObjectId($objectId, $class)
    {
        if (null === $class) {
            throw new Error(sprintf('Class second parameter cannot be null when selecting an object by "objectId"'));
        }

        $this->where['id'] = ParseObjectIdFactory::getIdFromObjectId($class, $objectId);

        return $this;
    }

    /**
     * Handle complex {"where": ...} structure
     *
     * {"where": "m_field" => "value..." }
     * {"where": "$regexp" => array() }
     *
     * @param array
     * @return \ParseQueryComposer
     */
    private function addWhere($where)
    {
        // Decoder is smart and wont decode arrays or objects
        $decoded = Decoder::decode($where);

        // $field a field name "m_email" or special keyword "$or"...
        foreach ($decoded as $field => $value) {

            // first of all un prefix the field
            $fieldName = $this->unprefix($field);

            // check if the field name is a combinator "$or"
            // or a simple scalar field name
            $condition = $this->guessComparisonType($fieldName, $value);

            $key = $condition['field'];
            $this->where[$key] = $condition['value'];
        }

        return $this;
    }

    /**
     * Return a pair of field and value for a where condition or and subset of those nested inside an "($OR)".
     *
     * @param string Field name un-refixed
     * @param Raw value
     * @return array Array(field, value) field (ready for WhereCondition) value decoded value, even for likes
     */
    public function guessComparisonType($fieldName, $value)
    {
        if (in_array($fieldName, $this->combinators)) {
            $condition = $this->addAndOrXNestedComparison($fieldName, $value);

        } else {

            // else the value after the field name "label" => (?) can be
            // an array with a regex or a simple value to match against
            if ($this->isScalar($value)) {
                $condition = $this->addStrictComparison($fieldName, $value);
            } else {
                $condition = $this->addRegexComparison($fieldName, $value);
            }
        }

        return array(
            'field' => $condition['field'],
            'value' => $condition['value'],
        );
    }

    /**
     * Check is the current value is a scalar value.
     *
     * @param mixed Raw input value
     * @return boolean
     */
    public function isScalar($value)
    {
        return (is_string($value) OR is_numeric($value)) ? true : false;
    }

    /**
     * Check if the field name is a $regexp special expression.
     *
     * @param mixed Raw input value
     * @return boolean
     */
    public function isRegex($fieldName)
    {
        return ($fieldName = '$regex') ? true : false;
    }

    /**
     * Return a simple pair of field and value forr an strict equal where statement.
     *
     * @param string Field name un-prefixed
     * @param integer|string Raw input value
     * @return array Array(field, value) both decoded
     */
    public function addStrictComparison($fieldName, $value)
    {
        return array(
            'field' => $fieldName,
            'value' => $value,
        );
    }

    /**
     * Return a simple pair of field and value for a LIKE comparison with the field name suffixed with "($LIKE)"
     * and the value surrounded (or prefixed) with "%" for LIKE comparison.
     *
     * @param string Field name un-prefixed
     * @param array Value such as {\"$regex\":\"\\\\QANILI\\\\E\"}}
     * @return array Array(field, value) both decoded
     */
    public function addRegexComparison($fieldName, $value)
    {
        // value is here: {\"$regex\":\"\\\\QANILI\\\\E\"}}
        // the value needs to be transformed as a LIKE value
        return array(
            'field' => $fieldName.'($LIKE)',
            'value' => ParseExpr::castAsLike($value['$regex']),
        );
    }

    /**
     * Matches sub andXOr where statements.
     *
     * @param string Field name
     * @param array  Nested array condtional expressions
     * @return array ['field': '($OR)' , 'value': array(...)]
     */
    public function addAndOrXNestedComparison($fieldName, $value)
    {
        if ($fieldName !== '$or') {
            throw new \Exception(sprintf('Or nested comparison can only be done with "$or" field, "%s" given', $fieldName));
        }

        $where = array();
        foreach ($value as $row) {

            foreach ($row as $field => $value) {
                $fieldName = $this->unprefix($field);

                $condition = $this->guessComparisonType($fieldName, $value);

                $key = $condition['field'];
                $where[$key] = $condition['value'];
            }
        }

        return array(
            'field' => '($OR)',
            'value' => $where,
        );
    }

    /**
     * Handles query parts like {"order": "m_sort",..}
     *
     * @param string Sort in mongoDB is a simple
     * @return \ParseQueryComposer
     */
    private function addOrder($field)
    {
        $orderBy = $this->unprefix(ParseExpr::abs($field));
        $order = ParseExpr::ascOrDesc($field);

        $this->orderBy = array($orderBy => $order);
    }

    /**
     * Unprefixes a field.
     */
    private function unprefix($field)
    {
        return str_replace($this->prefix, '', $field);
    }

    /**
     * Return an abstract class repository for the CoreBundle.
     *
     * @return object \RepositoryInterface
     */
    protected function getAbstractRepository($classShortName)
    {
        $helper = new AliasHelper($this->config);
        $alias = $helper->getAlias($classShortName);

        if (false === $alias) {
            throw new \Exception(sprintf('The class "%s" is not defined in your parse mapping config', $classShortName));
        }

        return $this->em->getRepository($alias);
    }
}
