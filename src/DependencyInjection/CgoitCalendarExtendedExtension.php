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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CgoitCalendarExtendedExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('di.yml');

        // Configuration
        $container->setParameter('cgoit_calendar_extended.max_repeat_count', (int) $config['max_repeat_count']);
        $container->setParameter('cgoit_calendar_extended.filter_fields', $config['filter_fields']);
        $container->setParameter('cgoit_calendar_extended.exceptions.max_count', (int) $config['exceptions']['max_count']);
        $container->setParameter('cgoit_calendar_extended.exceptions.move_days', (int) $config['exceptions']['move_days']);
        $container->setParameter('cgoit_calendar_extended.exceptions.move_times', $config['exceptions']['move_times']);

        $container->setParameter('cgoit_calendar_extended.month_array', array_reduce(range(1, 12),
            static function ($arr, $monthNr) {
                $arr[$monthNr] = date('F', mktime(0, 0, 0, $monthNr, 1));

                return $arr;
            },
            [],
        ));

        $container->setParameter('cgoit_calendar_extended.day_array', [
            'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
        ]);
    }
}
