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

namespace Cgoit\CalendarExtendedBundle\Tests\Events;

use Cgoit\CalendarExtendedBundle\Events\EventContext;
use Cgoit\CalendarExtendedBundle\Events\EventFactory;
use Cgoit\CalendarExtendedBundle\Events\RecurringEventsFinder;
use Cgoit\CalendarExtendedBundle\Events\RecurringEventsLoader;
use Cgoit\CalendarExtendedBundle\Tests\Support\Models;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecurringEventsLoaderTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $langBackup;

    protected function setUp(): void
    {
        $this->langBackup = $GLOBALS['TL_LANG'] ?? [];

        $GLOBALS['TL_LANG']['DAYS'] = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $GLOBALS['TL_LANG']['MONTHS'] = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'] = ' - ';
        $GLOBALS['TL_LANG']['MSC']['cal_until'] = 'until %s';
        $GLOBALS['TL_LANG']['MSC']['and_label'] = 'and';
        $GLOBALS['TL_LANG']['tl_calendar_events']['cal_repeat_fixed_dates'] = 'on %s (%s, %s)';
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG'] = $this->langBackup;
    }

    #[DataProvider('loaders')]
    public function testTheQueryIsSkippedWithoutCalendars(string $method): void
    {
        $events = ['20250303' => [1741000000 => [['title' => 'Concert']]]];

        $result = (new RecurringEventsLoader($this->createStub(RecurringEventsFinder::class), new EventFactory($this->createStub(ContentUrlGenerator::class), $this->createStub(ContaoFramework::class))))->{$method}($events, new EventContext(start: 0, end: 1));

        $this->assertSame($events, $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function loaders(): iterable
    {
        yield 'extended recurrences' => ['addExtendedRecurrences'];
        yield 'fixed dates' => ['addFixedDates'];
    }

    public function testExtendedRecurrencesWithinThePeriodAreAdded(): void
    {
        $events = $this->loader(extended: [$this->event(allRecurrences: [
            ['int_start' => $this->time('2025-03-03'), 'int_end' => $this->time('2025-03-03', '10:00')],
            ['int_start' => $this->time('2025-04-07'), 'int_end' => $this->time('2025-04-07', '10:00')],
            ['int_start' => $this->time('2025-05-05'), 'int_end' => $this->time('2025-05-05', '10:00')],
        ])])->addExtendedRecurrences([], $this->context('2025-04-01', '2025-04-30'));

        $this->assertSame([20250407], array_keys($events));
        $this->assertSame('/event', $events[20250407][$this->time('2025-04-07')][0]['href']);
    }

    public function testExtendedRecurrenceAlreadyInTheListIsNotAddedTwice(): void
    {
        $start = $this->time('2025-04-07');
        $existing = [20250407 => [$start => [['id' => '3', 'title' => 'From the core']]]];

        $events = $this->loader(extended: [$this->event(allRecurrences: [['int_start' => $start, 'int_end' => $start + 3600]])])
            ->addExtendedRecurrences($existing, $this->context('2025-04-01', '2025-04-30'))
        ;

        $this->assertSame($existing, $events);
    }

    public function testExtendedRecurrenceOfAnotherEventAtTheSameTimeIsAdded(): void
    {
        $start = $this->time('2025-04-07');
        $existing = [20250407 => [$start => [['id' => '99', 'title' => 'Other']]]];

        $events = $this->loader(extended: [$this->event(allRecurrences: [['int_start' => $start, 'int_end' => $start + 3600]])])
            ->addExtendedRecurrences($existing, $this->context('2025-04-01', '2025-04-30'))
        ;

        $this->assertCount(2, $events[20250407][$start]);
    }

    public function testFixedDatesUseTheTimesOfTheEvent(): void
    {
        $events = $this->loader(fixed: [$this->event(fixedDates: [
            ['new_repeat' => $this->time('2025-04-07', '00:00'), 'new_start' => '', 'new_end' => '', 'reason' => 'Extra'],
            ['new_repeat' => $this->time('2025-04-14', '00:00'), 'new_start' => $this->time('2025-01-01', '14:00'), 'new_end' => $this->time('2025-01-01', '16:00'), 'reason' => ''],
            ['new_repeat' => $this->time('2025-06-02', '00:00'), 'new_start' => '', 'new_end' => '', 'reason' => 'Outside'],
        ])])->addFixedDates([], $this->context('2025-04-01', '2025-04-30'));

        $this->assertSame([20250407, 20250414], array_keys($events));

        $first = $events[20250407][$this->time('2025-04-07', '09:00')][0];
        $this->assertSame($this->time('2025-04-07', '10:30'), $first['end']);
        $this->assertSame('Extra', $first['reason']);
        // the number of all fixed dates, also the ones outside of the period
        $this->assertSame(3, $first['recurrences']);

        $second = $events[20250414][$this->time('2025-04-14', '14:00')][0];
        $this->assertSame($this->time('2025-04-14', '16:00'), $second['end']);
    }

    public function testFixedDatesWithIgnoredEndTimeEndAtTheStart(): void
    {
        $events = $this->loader(fixed: [$this->event(
            fixedDates: [['new_repeat' => $this->time('2025-04-07', '00:00'), 'new_start' => '', 'new_end' => '', 'reason' => '']],
            row: ['ignoreEndTime' => 1],
        )])->addFixedDates([], $this->context('2025-04-01', '2025-04-30'));

        $start = $this->time('2025-04-07', '09:00');
        $this->assertSame($start, $events[20250407][$start][0]['end']);
    }

    public function testFixedDatesOfAnEventWithoutTimeLastTheWholeDay(): void
    {
        $day = $this->time('2025-04-07', '00:00');
        $events = $this->loader(fixed: [$this->event(
            fixedDates: [['new_repeat' => $day, 'new_start' => '', 'new_end' => '', 'reason' => '']],
            row: ['addTime' => 0, 'startTime' => $this->time('2025-03-03', '00:00'), 'endTime' => $this->time('2025-03-03', '23:59')],
        )])->addFixedDates([], $this->context('2025-04-01', '2025-04-30'));

        $this->assertSame($day + 23 * 3600 + 59 * 60, $events[20250407][$day][0]['end']);
    }

    public function testFixedDatesOfAMultiDayEventKeepTheDuration(): void
    {
        // Friday to Sunday, 18:00 - 13:00
        $events = $this->loader(fixed: [$this->event(
            fixedDates: [
                ['new_repeat' => $this->time('2025-04-11', '00:00'), 'new_start' => '', 'new_end' => '', 'reason' => ''],
                ['new_repeat' => $this->time('2025-04-18', '00:00'), 'new_start' => $this->time('2025-01-01', '17:00'), 'new_end' => $this->time('2025-01-01', '12:00'), 'reason' => ''],
            ],
            row: ['startTime' => $this->time('2025-03-07', '18:00'), 'endTime' => $this->time('2025-03-09', '13:00'), 'endDate' => $this->time('2025-03-09', '00:00')],
        )])->addFixedDates([], $this->context('2025-04-01', '2025-04-30'));

        $this->assertSame($this->time('2025-04-13', '13:00'), $events[20250411][$this->time('2025-04-11', '18:00')][0]['end']);
        $this->assertSame($this->time('2025-04-20', '12:00'), $events[20250418][$this->time('2025-04-18', '17:00')][0]['end']);
    }

    public function testFixedDatesOfAMultiDayEventWithoutTimeKeepTheDuration(): void
    {
        $day = $this->time('2025-04-11', '00:00');
        $events = $this->loader(fixed: [$this->event(
            fixedDates: [['new_repeat' => $day, 'new_start' => '', 'new_end' => '', 'reason' => '']],
            row: ['addTime' => 0, 'startTime' => $this->time('2025-03-07', '00:00'), 'endTime' => $this->time('2025-03-09', '23:59:59'), 'endDate' => $this->time('2025-03-09', '00:00')],
        )])->addFixedDates([], $this->context('2025-04-01', '2025-04-30'));

        $this->assertSame($this->time('2025-04-13', '23:59:59'), $events[20250411][$day][0]['end']);
        $this->assertSame('11.04.2025 - 13.04.2025', $events[20250411][$day][0]['date']);
        // the occurrence is also added to the following days
        $this->assertSame([20250411, 20250412, 20250413], array_keys($events));
    }

    public function testFixedDatesOfAMultiDayEventKeepTheDurationAcrossTheChangeFromDaylightSavingTime(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        try {
            // Friday to Sunday, all day; the clocks change on Sunday 2025-10-26
            $day = $this->time('2025-10-24', '00:00');
            $events = $this->loader(fixed: [$this->event(
                fixedDates: [['new_repeat' => $day, 'new_start' => '', 'new_end' => '', 'reason' => '']],
                row: ['addTime' => 0, 'startTime' => $this->time('2025-03-07', '00:00'), 'endTime' => $this->time('2025-03-09', '23:59:59'), 'endDate' => $this->time('2025-03-09', '00:00')],
            )])->addFixedDates([], $this->context('2025-10-01', '2025-10-31'));

            $this->assertSame($this->time('2025-10-26', '23:59:59'), $events[20251024][$day][0]['end']);
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function testFixedDatesWithIgnoredEndTimeOfAMultiDayEventEndOnTheLastDay(): void
    {
        $events = $this->loader(fixed: [$this->event(
            fixedDates: [['new_repeat' => $this->time('2025-04-11', '00:00'), 'new_start' => '', 'new_end' => '', 'reason' => '']],
            row: ['ignoreEndTime' => 1, 'startTime' => $this->time('2025-03-07', '09:00'), 'endTime' => $this->time('2025-03-09', '09:00'), 'endDate' => $this->time('2025-03-09', '00:00')],
        )])->addFixedDates([], $this->context('2025-04-01', '2025-04-30'));

        $this->assertSame($this->time('2025-04-13', '09:00'), $events[20250411][$this->time('2025-04-11', '09:00')][0]['end']);
    }

    public function testRunningFixedDateThatStartedBeforeThePeriodIsAdded(): void
    {
        // Friday to Sunday, all day; the period starts on Saturday
        $day = $this->time('2025-04-11', '00:00');
        $events = $this->loader(fixed: [$this->event(
            fixedDates: [['new_repeat' => $day, 'new_start' => '', 'new_end' => '', 'reason' => '']],
            row: ['addTime' => 0, 'startTime' => $this->time('2025-03-07', '00:00'), 'endTime' => $this->time('2025-03-09', '23:59:59'), 'endDate' => $this->time('2025-03-09', '00:00')],
        )])->addFixedDates([], $this->context('2025-04-12', '2025-04-30'));

        $this->assertSame($this->time('2025-04-13', '23:59:59'), $events[20250411][$day][0]['end']);
    }

    public function testFixedDateAlreadyInTheListOnlyUpdatesTheNumberOfRecurrences(): void
    {
        $start = $this->time('2025-04-07', '09:00');
        $existing = [20250407 => [$start => [['id' => '3', 'title' => 'From the core']]]];

        $events = $this->loader(fixed: [$this->event(fixedDates: [
            ['new_repeat' => $this->time('2025-04-07', '00:00'), 'new_start' => '', 'new_end' => '', 'reason' => ''],
            ['new_repeat' => $this->time('2025-06-02', '00:00'), 'new_start' => '', 'new_end' => '', 'reason' => ''],
        ])])->addFixedDates($existing, $this->context('2025-04-01', '2025-04-30'));

        $this->assertCount(1, $events[20250407][$start]);
        $this->assertSame(2, $events[20250407][$start][0]['recurrences']);
    }

    /**
     * @param array<CalendarEventsModel> $extended
     * @param array<CalendarEventsModel> $fixed
     */
    private function loader(array $extended = [], array $fixed = []): RecurringEventsLoader
    {
        $finder = $this->createStub(RecurringEventsFinder::class);
        $finder
            ->method('findWithExtendedRecurrences')
            ->willReturn($extended)
        ;

        $finder
            ->method('findWithFixedDates')
            ->willReturn($fixed)
        ;

        $generator = $this->createStub(ContentUrlGenerator::class);
        $generator
            ->method('generate')
            ->willReturn('/event')
        ;

        $framework = $this->createStub(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturn($this->createStub(Adapter::class))
        ;

        return new RecurringEventsLoader($finder, new EventFactory($generator, $framework));
    }

    private function context(string $start, string $end): EventContext
    {
        $page = Models::page(['dateFormat' => 'd.m.Y', 'timeFormat' => 'H:i', 'datimFormat' => 'd.m.Y H:i']);

        return new EventContext(calendars: [7], start: $this->time($start, '00:00'), end: $this->time($end, '23:59'), page: $page);
    }

    /**
     * @param array<array<string, mixed>> $allRecurrences
     * @param array<array<string, mixed>> $fixedDates
     * @param array<string, mixed>        $row
     */
    private function event(array $allRecurrences = [], array $fixedDates = [], array $row = []): CalendarEventsModel
    {
        return Models::event($row + [
            'id' => '3',
            'pid' => 7,
            'title' => 'Event',
            'addTime' => 1,
            'startTime' => $this->time('2025-03-03', '09:00'),
            'endTime' => $this->time('2025-03-03', '10:30'),
            'endDate' => '',
            'ignoreEndTime' => 0,
            'cssClass' => '',
            'source' => 'default',
            'target' => '',
            'teaser' => '',
            'featured' => '',
            'recurringExt' => 0,
            'allRecurrences' => serialize($allRecurrences),
            'repeatFixedDates' => serialize($fixedDates),
        ]);
    }

    private function time(string $date, string $time = '09:00'): int
    {
        return (int) strtotime($date.' '.$time);
    }
}
