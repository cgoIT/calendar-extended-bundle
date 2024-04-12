<?php

/*
 * This file is part of CalendarExtendedBundle.
 *
 * Copyright (c) 2009-2018 Kester Mielke
 *
 * @license LGPL-3.0+
 */

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

namespace Cgoit\CalendarExtendedBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the Contao calendar bundle.
 */
class CalendarExtendedBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
