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
use Symfony\Component\HttpFoundation\Response;
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

    public function testWithoutCalendarsNoEventsAreReturned(): void
    {
        $response = $this->invokeWithEvents([], [], []);

        $this->assertSame([], $this->decode($response));
    }

    public function testTheEventsAreMappedAndSortedByDay(): void
    {
        $late = $this->event(2, '2026-06-03 10:00');
        $early = $this->event(1, '2026-06-02 10:00');

        $ids = array_column($this->decode($this->invokeWithEvents([1], [], ['20260603' => [1780480800 => [$late]], '20260602' => [1780394400 => [$early]]])), 'id');
        $this->assertSame([1, 2], $ids);

        $ids = array_column($this->decode($this->invokeWithEvents([1], ['cal_order' => 'descending'], ['20260602' => [1 => [$early]], '20260603' => [1 => [$late]]])), 'id');
        $this->assertSame([2, 1], $ids);
    }

    public function testEventsOutsideTheRangeAreSkipped(): void
    {
        $inside = $this->event(1, '2026-06-02 10:00');
        $before = $this->event(2, '2026-05-01 10:00');
        $after = $this->event(3, '2026-07-01 10:00');

        $response = $this->invokeWithEvents([1], [], ['a' => [1 => [$inside, $before, $after]]]);

        $this->assertSame([1], array_column($this->decode($response), 'id'));
    }

    public function testARecurringEventIsKeptIfItsSeriesReachesIntoTheRange(): void
    {
        // The occurrence itself ends before the range, but the series (repeatEnd) does not
        $event = array_merge($this->event(1, '2026-05-01 10:00'), ['repeatEnd' => strtotime('2026-12-31')]);

        $this->assertCount(1, $this->decode($this->invokeWithEvents([1], [], ['a' => [1 => [$event]]])));
    }

    public function testStartedEventsCanBeHidden(): void
    {
        $started = $this->event(1, '2026-06-02 10:00');
        $started['begin'] = time() - 3600;
        $started['end'] = time() + 3600;
        $started['startTime'] = $started['begin'];
        $started['endTime'] = $started['end'];
        $upcoming = $this->event(2, '2026-06-02 10:00');
        $upcoming['begin'] = time() + 7200;
        $upcoming['end'] = time() + 10800;

        $events = ['a' => [1 => [$started, $upcoming]]];
        $start = date('Y-m-d', time() - 86400);
        $end = date('Y-m-d', time() + 86400);

        $this->assertCount(2, $this->decode($this->invokeWithEvents([1], [], $events, $start, $end)));
        $this->assertSame([2], array_column($this->decode($this->invokeWithEvents([1], ['hide_started' => '1'], $events, $start, $end)), 'id'));
    }

    public function testAMultiDayEventIsReturnedOnce(): void
    {
        $event = $this->event(1, '2026-06-02 10:00', '2026-06-04 12:00');

        $response = $this->invokeWithEvents([1], [], ['20260602' => [1 => [$event]], '20260603' => [1 => [$event]], '20260604' => [1 => [$event]]]);

        $this->assertCount(1, $this->decode($response));
    }

    public function testTheSameOccurrenceIsReturnedOnce(): void
    {
        $event = $this->event(1, '2026-06-02 10:00');

        $response = $this->invokeWithEvents([1], [], ['a' => [1 => [$event, $event]], 'b' => [1 => [$event]]]);

        $this->assertCount(1, $this->decode($response));
    }

    public function testTheOccurrencesOfARecurringEventAreAllReturned(): void
    {
        $first = array_merge($this->event(1, '2026-06-02 10:00'), ['recurring' => '1']);
        $second = array_merge($this->event(1, '2026-06-09 10:00'), ['recurring' => '1']);

        $response = $this->invokeWithEvents([1], [], ['a' => [1 => [$first]], 'b' => [1 => [$second]]]);

        $this->assertSame(['2026-06-02T10:00:00', '2026-06-09T10:00:00'], array_column($this->decode($response), 'start'));
    }

    public function testThePageIsAvailableToTheHooksWhileTheEventsAreLoadedOnly(): void
    {
        $GLOBALS['objPage'] = 'previous';
        $page = null;

        $provider = $this->createStub(EventsProvider::class);
        $provider
            ->method('getAccessibleCalendars')
            ->willReturn([1])
        ;
        $provider
            ->method('getAllEvents')
            ->willReturnCallback(
                static function () use (&$page): array {
                    $page = $GLOBALS['objPage'];

                    return [];
                },
            )
        ;

        $this->invoke($this->page(), Models::module(['type' => 'fullcalendar']), ['start' => '2026-06-01', 'end' => '2026-07-01'], provider: $provider);

        $this->assertInstanceOf(PageModel::class, $page);
        $this->assertSame('previous', $GLOBALS['objPage']);

        unset($GLOBALS['objPage']);
    }

    /**
     * @param array<int>           $calendars
     * @param array<string, mixed> $module
     * @param array<mixed>         $events
     */
    private function invokeWithEvents(array $calendars, array $module, array $events, string $start = '2026-06-01', string $end = '2026-07-01'): Response
    {
        $provider = $this->createStub(EventsProvider::class);
        $provider
            ->method('getAccessibleCalendars')
            ->willReturn($calendars)
        ;

        $provider
            ->method('getAllEvents')
            ->willReturn($events)
        ;

        return $this->invoke($this->page(), Models::module($module + ['type' => 'fullcalendar']), ['start' => $start, 'end' => $end], provider: $provider);
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(int $id, string $start, string|null $end = null): array
    {
        $begin = (int) strtotime($start);
        $finish = (int) strtotime($end ?? $start.' +2 hours');

        return [
            'id' => $id,
            'pid' => 1,
            'title' => 'Event '.$id,
            'teaser' => '',
            'href' => '/event/'.$id,
            'addTime' => '1',
            'startDate' => $begin,
            'endDate' => $finish,
            'startTime' => $begin,
            'endTime' => $finish,
            'begin' => $begin,
            'end' => $finish,
            'repeatEnd' => 0,
        ];
    }

    /**
     * @param array<string, string> $post
     */
    private function invoke(PageModel|null $page, ModuleModel|null $module, array $post = [], bool $visible = true, Security|null $security = null, EventsProvider|null $provider = null): Response
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

        $controller = new FetchEventsController($framework, $provider ?? $this->createStub(EventsProvider::class), $security ?? $this->createStub(Security::class), $signature);

        return $controller(Request::create('/fullcalendar/fetchEvents/1/2?'.http_build_query(['signature' => $signature->sign(1, 2)]), 'POST', $post), 1, 2);
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
