<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\Tests\Support;

use Contao\CalendarEventsModel;
use Contao\Model;
use Contao\PageModel;

/**
 * Creates models from a row without the container, which the constructor of the Contao models needs.
 */
final class Models
{
    /**
     * @param array<string, mixed> $row
     */
    public static function event(array $row): CalendarEventsModel
    {
        $model = (new \ReflectionClass(TestCalendarEventsModel::class))->newInstanceWithoutConstructor();

        return self::fill($model, $row);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function page(array $row): PageModel
    {
        return self::fill((new \ReflectionClass(PageModel::class))->newInstanceWithoutConstructor(), $row);
    }

    /**
     * @template T of Model
     *
     * @param T                    $model
     * @param array<string, mixed> $row
     *
     * @return T
     */
    private static function fill(Model $model, array $row): Model
    {
        $data = new \ReflectionProperty(Model::class, 'arrData');
        $data->setValue($model, $row);

        return $model;
    }
}
