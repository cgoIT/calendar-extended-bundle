<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the Contao calendar bundle.
 */
class CgoitCalendarExtendedBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
