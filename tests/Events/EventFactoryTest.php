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
use Cgoit\CalendarExtendedBundle\Tests\Support\Models;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

final class EventFactoryTest extends TestCase
{
    private const string SEPARATOR = ' - ';

    /**
     * @var array<string, mixed>
     */
    private array $globalsBackup;

    protected function setUp(): void
    {
        $this->globalsBackup = ['TL_LANG' => $GLOBALS['TL_LANG'] ?? null];

        $GLOBALS['TL_LANG']['DAYS'] = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $GLOBALS['TL_LANG']['MONTHS'] = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'] = self::SEPARATOR;
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG'] = $this->globalsBackup['TL_LANG'];
    }

    public function testNothingIsAddedWithoutPage(): void
    {
        $events = [];

        $this->factory()->create($this->event(), new EventContext(), strtotime('2025-03-03 09:00'), strtotime('2025-03-03 10:00'), $events, false);

        $this->assertSame([], $events);
    }

    public function testSingleDayEventWithTime(): void
    {
        $start = strtotime('2025-03-03 09:00');
        $end = strtotime('2025-03-03 10:30');
        $events = [];

        $this->factory()->create($this->event(), $this->context(), $start, $end, $events, false);

        $this->assertSame([20250303], array_keys($events));
        $this->assertSame([$start], array_keys($events['20250303']));

        $event = $events['20250303'][$start][0];
        $this->assertSame('03.03.2025', $event['date']);
        $this->assertSame('09:00 - 10:30', $event['time']);
        $this->assertSame('Monday', $event['day']);
        $this->assertSame('March', $event['month']);
        $this->assertSame(date('Y-m-d\TH:i:sP', $start), $event['datetime']);
        $this->assertSame($start, $event['begin']);
        $this->assertSame($end, $event['end']);
        $this->assertSame($end, $event['effectiveEndTime']);
        $this->assertSame(7, $event['parent']);
        $this->assertSame('Concert &amp; Party', $event['title']);
        $this->assertSame('Concert & Party', $event['link']);
        $this->assertSame('/concert', $event['href']);
        $this->assertSame('', $event['target']);
        $this->assertFalse($event['hasTeaser']);
        $this->assertInstanceOf(\Closure::class, $event['hasDetails']);
        $this->assertArrayNotHasKey('recurrences', $event);
    }

    public function testEventWithoutTimeHasOnlyTheDate(): void
    {
        $start = strtotime('2025-03-03 00:00');
        $events = [];

        $this->factory()->create($this->event(['addTime' => 0]), $this->context(), $start, strtotime('2025-03-03 23:59'), $events, false);

        $event = $events['20250303'][$start][0];
        $this->assertSame('', $event['time']);
        $this->assertSame('2025-03-03', $event['datetime']);
    }

    public function testEventWithoutEndTimeEndsAtTheEndOfTheDay(): void
    {
        $start = strtotime('2025-03-03 09:00');
        $events = [];

        // start and end are the same: open end
        $this->factory()->create($this->event(['endTime' => $start]), $this->context(), $start, $start, $events, false);

        $event = $events['20250303'][$start][0];
        $this->assertSame('09:00', $event['time']);
        $this->assertSame(strtotime('2025-03-03 23:59:59'), $event['effectiveEndTime']);
    }

    public function testMultiDayEventIsAddedToEachDay(): void
    {
        $start = strtotime('2025-03-03 09:00');
        $end = strtotime('2025-03-05 17:00');
        $events = [];

        $this->factory()->create($this->event(), $this->context(), $start, $end, $events, false);

        $this->assertSame([20250303, 20250304, 20250305], array_keys($events));
        $event = $events['20250303'][$start][0];
        $this->assertSame('03.03.2025 09:00 - 05.03.2025 17:00', $event['date']);
        $this->assertSame('', $event['day']);
        $this->assertSame('', $event['time']);
        // the following days are keyed by the start of the following days
        $this->assertSame([strtotime('2025-03-04 09:00')], array_keys($events['20250304']));
    }

    public function testNoSpanAddsTheEventOnlyOnTheFirstDay(): void
    {
        $events = [];

        $this->factory()->create($this->event(), $this->context(noSpan: true), strtotime('2025-03-03 09:00'), strtotime('2025-03-05 17:00'), $events, false);

        $this->assertSame([20250303], array_keys($events));
    }

    public function testRecurrencesAndReasonAreAdded(): void
    {
        $start = strtotime('2025-03-03 09:00');
        $events = [];

        $this->factory()->create($this->event(), $this->context(), $start, $start + 3600, $events, true, 4, 'Holiday');

        $event = $events['20250303'][$start][0];
        $this->assertSame(4, $event['recurrences']);
        $this->assertSame('Holiday', $event['reason']);
    }

    public function testClassContainsCssClassAndState(): void
    {
        $events = [];
        $past = strtotime('2020-03-03 09:00');
        $future = strtotime('+10 days 09:00');

        $factory = $this->factory();
        $event = $this->event(['cssClass' => 'special', 'featured' => '1']);
        $factory->create($event, $this->context(), $past, $past + 3600, $events, false);
        $factory->create($event, $this->context(), $future, $future + 3600, $events, false);
        $factory->create($event, $this->context(), strtotime('today 09:00'), strtotime('today 10:00'), $events, false);

        $classes = array_merge(...array_map(static fn (array $day): array => array_merge(...array_values($day)), array_values($events)));
        $this->assertSame([' special bygone featured', ' special upcoming featured', ' special current featured'], array_column($classes, 'class'));
    }

    public function testExternalLinkOpensInNewWindow(): void
    {
        $start = strtotime('2025-03-03 09:00');
        $events = [];

        $this->factory()->create($this->event(['source' => 'external', 'target' => '1', 'teaser' => '<p>Text</p>']), $this->context(), $start, $start + 3600, $events, false);

        $event = $events['20250303'][$start][0];
        $this->assertSame(' target="_blank" rel="noreferrer noopener"', $event['target']);
        $this->assertTrue($event['hasDetails']);
        $this->assertSame('', $event['details']);
        $this->assertTrue($event['hasTeaser']);
    }

    public function testHrefIsNullWithoutTargetPage(): void
    {
        $start = strtotime('2025-03-03 09:00');
        $events = [];
        $generator = $this->createStub(ContentUrlGenerator::class);
        $generator
            ->method('generate')
            ->willThrowException(new RouteNotFoundException())
        ;

        $this->factory($generator)->create($this->event(), $this->context(), $start, $start + 3600, $events, false);

        $this->assertNull($events['20250303'][$start][0]['href']);
    }

    private function factory(ContentUrlGenerator|null $generator = null): EventFactory
    {
        if (null === $generator) {
            $generator = $this->createStub(ContentUrlGenerator::class);
            $generator
                ->method('generate')
                ->willReturn('/concert')
            ;
        }

        $framework = $this->createStub(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturn($this->createStub(Adapter::class))
        ;

        return new EventFactory($generator, $framework);
    }

    private function context(bool $noSpan = false): EventContext
    {
        $page = Models::page(['dateFormat' => 'd.m.Y', 'timeFormat' => 'H:i', 'datimFormat' => 'd.m.Y H:i']);

        return new EventContext(noSpan: $noSpan, page: $page);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function event(array $overrides = []): CalendarEventsModel
    {
        return Models::event($overrides + [
            'id' => 3,
            'pid' => 7,
            'title' => 'Concert & Party',
            'addTime' => 1,
            'startDate' => strtotime('2025-03-03'),
            'startTime' => strtotime('2025-03-03 09:00'),
            'endDate' => '',
            'endTime' => strtotime('2025-03-03 10:30'),
            'ignoreEndTime' => 0,
            'cssClass' => '',
            'source' => 'default',
            'target' => '',
            'teaser' => '',
            'featured' => '',
            'recurring' => 0,
            'recurringExt' => 0,
        ]);
    }
}
