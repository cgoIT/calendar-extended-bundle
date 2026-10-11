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

namespace Cgoit\CalendarExtendedBundle\Tests\Controller\Route;

use Cgoit\CalendarExtendedBundle\Controller\Route\FetchEventsController;
use Cgoit\CalendarExtendedBundle\Service\EventsProvider;
use Cgoit\CalendarExtendedBundle\Service\FetchEventsSignature;
use Contao\CoreBundle\Framework\ContaoFramework;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FetchEventsControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidSignatures(): iterable
    {
        yield 'missing signature' => [[]];
        yield 'wrong signature' => [['signature' => 'abc']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('invalidSignatures')]
    public function testRequestsWithoutAValidSignatureAreNotFound(array $query): void
    {
        // The framework must not be touched before the signature is verified
        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->expects($this->never())
            ->method('initialize')
        ;

        $controller = new FetchEventsController(
            $framework,
            $this->createStub(EventsProvider::class),
            $this->createStub(Security::class),
            new FetchEventsSignature('secret'),
        );

        $this->expectException(NotFoundHttpException::class);

        $controller(Request::create('/fullcalendar/fetchEvents/1/2', 'POST', $query), 1, 2);
    }

    public function testASignatureOfAnotherPageIsNotFound(): void
    {
        $signature = new FetchEventsSignature('secret');
        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->expects($this->never())
            ->method('initialize')
        ;

        $controller = new FetchEventsController($framework, $this->createStub(EventsProvider::class), $this->createStub(Security::class), $signature);

        $this->expectException(NotFoundHttpException::class);

        // The signature of the module on the protected page 5 is used with the public page 1
        $controller(Request::create('/fullcalendar/fetchEvents/1/2', 'POST', ['signature' => $signature->sign(5, 2)]), 1, 2);
    }
}
