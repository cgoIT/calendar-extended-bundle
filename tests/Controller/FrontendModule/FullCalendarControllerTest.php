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

namespace Cgoit\CalendarExtendedBundle\Tests\Controller\FrontendModule;

use Cgoit\CalendarExtendedBundle\Controller\FrontendModule\FullCalendarController;
use Cgoit\CalendarExtendedBundle\Service\FetchEventsSignature;
use Contao\CoreBundle\Exception\PageNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;

final class FullCalendarControllerTest extends CalendarViewTestCase
{
    private const MODULE = ['id' => '5', 'cal_startDay' => '1'];

    public function testWithoutCalendarsNothingIsRendered(): void
    {
        [$response, $data] = $this->render($this->controller([]), $this->page(), self::MODULE);

        $this->assertSame('', $response->getContent());
        $this->assertArrayNotHasKey('calendar', $data);
    }

    public function testWithoutPageNothingIsRendered(): void
    {
        [$response] = $this->render($this->controller([1]), null, self::MODULE);

        $this->assertSame('', $response->getContent());
    }

    public function testTheConfigContainsTheSignedUrl(): void
    {
        [, $data] = $this->render($this->controller([1]), $this->page(), self::MODULE);

        $this->assertSame('@Contao/cal_fc_default.html.twig', $data['calendar_template']);
        $this->assertSame(5, $data['calendar']['moduleId']);

        $config = $data['calendar']['config'];
        $this->assertSame('/fetch/1/5?signature='.(new FetchEventsSignature('secret'))->sign(1, 5), $config['url']);
        $this->assertSame('token', $config['requestToken']);
        $this->assertSame(1, $config['firstDay']);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string, non-empty-string}>
     */
    public static function views(): iterable
    {
        yield 'month' => ['cal_fc_month', [], 'dayGridMonth', date('Y-m').'-'];
        yield 'week' => ['cal_fc_week', [], 'timeGridWeek', date('Y-m-d', strtotime('monday this week'))];
        yield 'day' => ['cal_fc_day', [], 'timeGridDay', date('Y-m-d')];
        yield 'list' => ['cal_fc_list', [], 'listMonth', date('Y-m-d')];
        yield 'unknown format' => ['', [], 'timeGridWeek', date('Y-m-d')];
        yield 'month by url' => ['cal_fc_week', ['month' => '202605'], 'dayGridMonth', '2026-05-01'];
        yield 'week by url' => ['cal_fc_month', ['week' => '202620'], 'timeGridWeek', '2026-05-11'];
        yield 'day by url' => ['cal_fc_month', ['day' => '20260513'], 'dayGridMonth', '2026-05-13'];
    }

    /**
     * @param array<string, string> $query
     * @param non-empty-string      $datePrefix
     */
    #[DataProvider('views')]
    public function testTheInitialViewAndDateFollowTheFormatAndTheUrl(string $format, array $query, string $view, string $datePrefix): void
    {
        [, $data] = $this->render($this->controller([1]), $this->page(), ['cal_fcFormat' => $format] + self::MODULE, $query);

        $this->assertSame($view, $data['calendar']['config']['initialView']);
        $this->assertStringStartsWith($datePrefix, $data['calendar']['config']['initialDate']);
    }

    public function testTheDynamicFormatCanBeIgnored(): void
    {
        [, $data] = $this->render($this->controller([1]), $this->page(), ['cal_fcFormat' => 'cal_fc_list', 'cal_ignoreDynamic' => '1'] + self::MODULE, ['month' => '202605']);

        $this->assertSame('listMonth', $data['calendar']['config']['initialView']);
        $this->assertStringStartsWith(date('Y-m-d'), $data['calendar']['config']['initialDate']);
    }

    public function testTheBusinessHoursAreOnlyPassedIfEnabled(): void
    {
        [, $data] = $this->render($this->controller([1]), $this->page(), self::MODULE);
        $this->assertNull($data['calendar']['config']['businessHours']);

        [, $data] = $this->render(
            $this->controller([1]),
            $this->page(),
            ['businessHours' => '1', 'businessDays' => serialize(['1', '2']), 'businessDayStart' => (string) (8 * 3600), 'businessDayEnd' => (string) (17 * 3600)] + self::MODULE,
        );
        $this->assertSame(['daysOfWeek' => [1, 2], 'startTime' => '08:00', 'endTime' => '17:00'], $data['calendar']['config']['businessHours']);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidDates(): iterable
    {
        yield 'month' => [['month' => '202613']];
        yield 'day' => [['day' => '20261340']];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('invalidDates')]
    public function testAnInvalidDateIsNotFound(array $query): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->render($this->controller([1]), $this->page(), ['cal_fcFormat' => 'cal_fc_month'] + self::MODULE, $query);
    }

    /**
     * @param array<int> $calendars
     */
    private function controller(array $calendars): FullCalendarController
    {
        return new FullCalendarController($this->provider($calendars), new FetchEventsSignature('secret'));
    }
}
