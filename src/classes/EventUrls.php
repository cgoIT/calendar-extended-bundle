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

/**
 * Namespace.
 */

namespace Cgoit\CalendarExtendedBundle\Classes;

use Contao\ModuleModel;

/**
 * Class EventExt.
 *
 * @copyright  Kester Mielke 2010-2013
 */
class EventUrls
{
    /**
     * @param array<mixed> $arrEvents
     * @param array<mixed> $arrCalendars
     */
    public function modifyEventUrl(array $arrEvents, array $arrCalendars, int $intStart, int $intEnd, ModuleModel $objModule): mixed
    {
        if (1 === (int) $objModule->ignore_urlparameter) {
            return $arrEvents;
        }

        foreach ($arrEvents as $k1 => $days) {
            foreach ($days as $k2 => $day) {
                foreach ($day as $k3 => $event) {
                    $eventUrl = '?day='
                        .date('Ymd', $event['startTime'])
                        .'&amp;times='.$event['startTime']
                        .','.$event['endTime'];
                    $arrEvents[$k1][$k2][$k3]['href'] .= $eventUrl;
                }
            }
        }

        return $arrEvents;
    }
}
