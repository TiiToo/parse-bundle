<?php

namespace Adadgio\ParseBundle\Component\Utility;

class AliasHelper
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getNamespace($shortClassName)
    {
        $class = strtolower($shortClassName);
        $classKeys = array_map('strtolower', array_keys($this->config['mapping']));

        if (in_array($class, $classKeys)) {
            return $this->config['mapping'][$class]['class'];
        } else {
            return false;
        }
    }

    public function getAlias($shortClassName)
    {
        $class = strtolower($shortClassName);
        $classKeys = array_map('strtolower', array_keys($this->config['mapping']));

        if (in_array($class, $classKeys)) {
            return self::getAliasFromNamespace($this->config['mapping'][$class]['class']);
        } else {
            return false;
        }
    }

    public function getAliasFromNamespace($namespace)
    {
        $parts = explode('\\', $namespace);

        $parts = array_filter($parts, function ($e) {
            return ($e === 'Entity') ? false : true;
        });

        $class = array_pop($parts);
        return join($parts).':'.$class;
    }
}
