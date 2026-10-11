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

/**
 * An event that does not need the database to resolve its calendar.
 */
final class TestCalendarEventsModel extends CalendarEventsModel
{
    /**
     * @param array<string, mixed> $arrOptions
     */
    public function getRelated($strKey, array $arrOptions = []): mixed
    {
        return null;
    }
}
