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

namespace Cgoit\CalendarExtendedBundle\Tests\Service;

use Cgoit\CalendarExtendedBundle\Service\FetchEventsSignature;
use PHPUnit\Framework\TestCase;

final class FetchEventsSignatureTest extends TestCase
{
    public function testAValidSignatureIsAccepted(): void
    {
        $signer = new FetchEventsSignature('secret');

        $this->assertTrue($signer->verify(12, 34, $signer->sign(12, 34)));
    }

    public function testTheSignatureIsBoundToPageAndModule(): void
    {
        $signer = new FetchEventsSignature('secret');
        $signature = $signer->sign(12, 34);

        $this->assertFalse($signer->verify(13, 34, $signature));
        $this->assertFalse($signer->verify(12, 35, $signature));
        $this->assertFalse($signer->verify(34, 12, $signature));
    }

    public function testASignatureOfAnotherSecretIsRejected(): void
    {
        $this->assertFalse((new FetchEventsSignature('secret'))->verify(12, 34, (new FetchEventsSignature('other'))->sign(12, 34)));
    }

    public function testAnEmptyOrWrongSignatureIsRejected(): void
    {
        $signer = new FetchEventsSignature('secret');

        $this->assertFalse($signer->verify(12, 34, ''));
        $this->assertFalse($signer->verify(12, 34, 'abc'));
    }
}
