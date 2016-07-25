<?php

namespace Adadgio\ParseBundle\Factory;

class ParseConfigFactory
{
    /**
     * Create a config object (see Parse config legacy)
     * Fields in here are arbitray and depend on the user application (config can be empty, parse SDKs does not care)
     *
     * @param array Custom parse config
     */
    public static function createConfig(array $config = array())
    {
        return $config;
    }
}
