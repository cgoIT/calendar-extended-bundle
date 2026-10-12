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

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signs the URL that loads the events of a FullCalendar module.
 *
 * The signature binds the module to the page it is rendered on. So the events of a module on a protected page
 * cannot be requested with the ID of another (public) page, the signature is only part of the markup of the page
 * the module is shown on.
 */
class FetchEventsSignature
{
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
    ) {
    }

    public function sign(int $pageId, int $moduleId): string
    {
        return hash_hmac('sha256', 'fetchEvents:'.$pageId.':'.$moduleId, $this->secret);
    }

    public function verify(int $pageId, int $moduleId, string $signature): bool
    {
        return '' !== $signature && hash_equals($this->sign($pageId, $moduleId), $signature);
    }
}
