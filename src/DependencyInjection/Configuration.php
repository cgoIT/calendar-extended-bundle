<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cgoit_calendar_extended');

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()

            ->integerNode('max_repeat_count')
            ->info('The maximum number an event is repeated. Default: 365.')
            ->min(1)
            ->defaultValue(365)
            ->end()

            ->append($this->getExceptionsConfigNode())

            ->arrayNode('filter_fields')
            ->info("Define which fields of an event should be available as filter. Default: ['title', 'location_name', 'location_str', 'location_plz'].")
            ->prototype('scalar')->end()
            ->defaultValue(['title', 'location_name', 'location_str', 'location_plz'])
            ->example(['title', 'location_name', 'location_str', 'location_plz'])

            ->end()
        ;

        return $treeBuilder;
    }

    private function getExceptionsConfigNode(): NodeDefinition
    {
        return (new TreeBuilder('exceptions'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()

            ->integerNode('max_count')
            ->info('The maximum number of repeat exceptions for an event. Default: 250.')
            ->min(1)
            ->defaultValue(250)
            ->end()

            ->integerNode('move_days')
            ->info('The range of days for the move date option. 14 means from -14 days to 14 days. Default: 7.')
            ->defaultValue(7)
            ->end()

            ->append($this->getMoveTimesNode())

            ->end()
        ;
    }

    private function getMoveTimesNode(): NodeDefinition
    {
        return (new TreeBuilder('move_times'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()

            ->scalarNode('from')
            ->info('The start time for the move time option. Default: 00:00.')
            ->defaultValue('00:00')
            ->end()

            ->scalarNode('to')
            ->info('The end time for the move time option. Default: 23:59.')
            ->defaultValue('23:59')
            ->end()

            ->integerNode('interval')
            ->info('The interval in minutes for the move time option. Default: 15.')
            ->defaultValue(15)
            ->end()

            ->end()
        ;
    }
}
