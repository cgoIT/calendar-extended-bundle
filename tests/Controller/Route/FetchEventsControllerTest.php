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
use Cgoit\CalendarExtendedBundle\Tests\Support\Models;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Model;
use Contao\ModuleModel;
use Contao\PageModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

    public function testAnUnknownPageIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->invoke(null, Models::module(['type' => 'fullcalendar']));
    }

    public function testAnUnknownModuleIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->invoke($this->page(), null);
    }

    public function testAModuleOfAnotherTypeIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->invoke($this->page(), Models::module(['type' => 'eventlist']));
    }

    public function testAnInvisibleModuleIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->invoke($this->page(), Models::module(['type' => 'fullcalendar']), visible: false);
    }

    public function testAProtectedPageIsNotFoundWithoutMembership(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->once())
            ->method('isGranted')
            ->with(ContaoCorePermissions::MEMBER_IN_GROUPS, ['3'])
            ->willReturn(false)
        ;

        $this->expectException(NotFoundHttpException::class);

        $this->invoke($this->page(['protected' => '1', 'groups' => serialize(['3'])]), Models::module(['type' => 'fullcalendar']), security: $security);
    }

    public function testAModuleForGuestsIsNotFoundForMembers(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_MEMBER')
            ->willReturn(true)
        ;

        $this->expectException(NotFoundHttpException::class);

        $this->invoke($this->page(), Models::module(['type' => 'fullcalendar', 'guests' => '1']), security: $security);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'end before start' => ['2026-02-01', '2026-01-01'];
        yield 'too long' => ['2026-01-01', '2028-01-01'];
    }

    #[DataProvider('invalidRanges')]
    public function testAnInvalidTimeRangeIsABadRequest(string $start, string $end): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->invoke($this->page(), Models::module(['type' => 'fullcalendar']), ['start' => $start, 'end' => $end]);
    }

    /**
     * @param array<string, string> $post
     */
    private function invoke(PageModel|null $page, ModuleModel|null $module, array $post = [], bool $visible = true, Security|null $security = null): void
    {
        $signature = new FetchEventsSignature('secret');

        $pageAdapter = $this->createMock(Adapter::class);
        $pageAdapter
            ->method('__call')
            ->willReturn($page)
        ;

        $moduleAdapter = $this->createMock(Adapter::class);
        $moduleAdapter
            ->method('__call')
            ->willReturn($module)
        ;

        $controllerAdapter = $this->createMock(Adapter::class);
        $controllerAdapter
            ->method('__call')
            ->willReturn($visible)
        ;

        $framework = $this->createStub(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturnMap([
                [PageModel::class, $pageAdapter],
                [ModuleModel::class, $moduleAdapter],
                [Controller::class, $controllerAdapter],
            ])
        ;

        $controller = new FetchEventsController($framework, $this->createStub(EventsProvider::class), $security ?? $this->createStub(Security::class), $signature);

        $controller(Request::create('/fullcalendar/fetchEvents/1/2?'.http_build_query(['signature' => $signature->sign(1, 2)]), 'POST', $post), 1, 2);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function page(array $row = []): PageModel
    {
        // loadDetails() would query the database
        $page = new class() extends PageModel {
            public function __construct()
            {
            }

            public function loadDetails(): static
            {
                return $this;
            }
        };

        $data = new \ReflectionProperty(Model::class, 'arrData');
        $data->setValue($page, $row + ['protected' => '', 'groups' => null]);

        return $page;
    }
}
