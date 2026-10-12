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

namespace Cgoit\CalendarExtendedBundle\Service;

use Contao\Events;
use Contao\ModuleModel;

/**
 * Stands in for the legacy module when the events of a module of this bundle are collected.
 *
 * The legacy method Events::getAllEvents() works for all supported Contao versions: it collects the events, passes
 * the module to the "getAllEvents" hooks and, where available, delegates to the events generator of the calendar
 * bundle. The module is only a container for the settings of the model.
 *
 * @internal
 */
final class EventsModule extends Events
{
    public function __construct(ModuleModel $module)
    {
        // The parent constructor needs a module model that is not available in every context
        $this->arrData = $module->row();
    }

    /**
     * @param array<int> $calendars
     *
     * @return array<mixed> The events grouped by day and time
     */
    public function findEvents(array $calendars, int $start, int $end, bool|null $featured): array
    {
        return $this->getAllEvents($calendars, $start, $end, $featured);
    }

    protected function compile(): void
    {
    }
}
