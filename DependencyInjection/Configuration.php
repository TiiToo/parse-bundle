<?php

namespace Adadgio\ParseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('adadgio_parse');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.
        $rootNode
            ->children()

                ->arrayNode('miscellaneous')
                    ->children()
                    ->scalarNode('protocol')->defaultValue(null)->end()
                    ->scalarNode('hostname')->defaultValue(null)->end()
                    ->end()
                ->end()

                ->arrayNode('settings')
                    ->children()
                        ->scalarNode('field_prefix')->defaultValue(null)->end()
                        ->arrayNode('never_prefixed')
                            ->defaultValue(array('email', 'password'))
                            ->prototype('scalar')->end()
                        ->end()
                        ->append($this->simpleArrayPrototype('serialization', 'hidden', array('password', 'salt')))
                        ->append($this->simpleArrayPrototype('conversion', 'reserved', array('id', 'objectId', 'password', 'salt', 'email', 'username', 'confirmation_token')))
                    ->end()
                ->end()

                ->arrayNode('application')
                    ->children()
                    ->scalarNode('client_id')->isRequired()->end() // application id
                    ->scalarNode('client_key')->isRequired()->end() // application secret key
                    ->scalarNode('rest_key')->defaultValue(null)->end()
                    ->scalarNode('master_key')->defaultValue(null)->end()
                    ->end()
                ->end()
                
                ->arrayNode('mapping')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('class')->isRequired()->end()
                            ->arrayNode('fields')->isRequired()->cannotBeEmpty()
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('name')->defaultValue(null)->end()
                                        ->scalarNode('type')->cannotBeEmpty()->defaultValue('auto')->end()
                                        ->scalarNode('method')->defaultValue(null)->end()
                                        ->scalarNode('arg')->defaultValue(null)->end()
                                        ->scalarNode('inversedBy')->defaultValue(null)->end()
                                        ->scalarNode('inversedRepositoryMethod')->defaultValue(null)->end()
                                        ->scalarNode('putMethod')->defaultValue(null)->end()
                                        ->scalarNode('parallelHydrationMethod')->defaultValue(null)->end()
                                        ->scalarNode('parallelHydrationSetter')->defaultValue(null)->end()
                                        ->arrayNode('without')
                                            ->prototype('scalar')->defaultValue(null)->end()
                                        ->end() # avoid fetching children under children
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('query_optimizer')->cannotBeEmpty()
                                ->prototype('scalar')->defaultValue(null)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

            ->end();

        return $treeBuilder;
    }

    private function simpleArrayPrototype($section, $name, array $defaults)
    {
        $builder = new TreeBuilder();
        $node = $builder->root($section);

        $node
            ->children()
            ->arrayNode($name)
                   ->defaultValue(array($defaults))
                   ->prototype('scalar')->end()
               ->end()
            ->end();
        return $node;
    }
}
