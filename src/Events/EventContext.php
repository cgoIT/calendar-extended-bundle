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

namespace Cgoit\CalendarExtendedBundle\Events;

use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;

/**
 * The request for the events (period, calendars) and the settings of the module the events are loaded for.
 */
final readonly class EventContext
{
    /**
     * @param array<int>         $calendars        IDs of the calendars
     * @param array<string>|null $filterFields     fields to add as "filter_data", null if disabled
     * @param array<mixed>       $calendarConfig   titles and colors per calendar (see Utils::getCalendarConfig())
     * @param array<int>         $holidayCalendars IDs of all holiday calendars
     */
    public function __construct(
        public array $calendars = [],
        public int $start = 0,
        public int $end = 0,
        public bool $noSpan = false,
        public bool $hideRunning = false,
        public bool $showOnlyNext = false,
        public bool $hideHoliday = false,
        public bool $ignoreDynamic = false,
        public array|null $filterFields = null,
        public array $calendarConfig = [],
        public array $holidayCalendars = [],
        public PageModel|null $page = null,
    ) {
    }

    /**
     * @param array<int>   $calendars
     * @param array<mixed> $calendarConfig
     * @param array<int>   $holidayCalendars
     */
    public static function fromModule(Module|ModuleModel $module, array $calendars, int $start, int $end, array $calendarConfig, array $holidayCalendars, PageModel|null $page): self
    {
        return new self(
            calendars: $calendars,
            start: $start,
            end: $end,
            noSpan: (bool) $module->cal_noSpan,
            hideRunning: (bool) $module->cal_hideRunning,
            showOnlyNext: (bool) $module->showOnlyNext,
            hideHoliday: (bool) $module->hide_holiday,
            ignoreDynamic: (bool) $module->cal_ignoreDynamic,
            filterFields: empty($module->filter_fields) ? null : array_map(\strval(...), StringUtil::deserialize($module->filter_fields, true)),
            calendarConfig: $calendarConfig,
            holidayCalendars: $holidayCalendars,
            page: $page,
        );
    }
}
