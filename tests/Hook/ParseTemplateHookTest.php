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

namespace Cgoit\CalendarExtendedBundle\Tests\Hook;

use Cgoit\CalendarExtendedBundle\Hook\ParseTemplateHook;
use Cgoit\CalendarExtendedBundle\Tests\Support\Models;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Twig\FragmentTemplate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final class ParseTemplateHookTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $languageBackup;

    private ContentUrlGenerator&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->languageBackup = $GLOBALS['TL_LANG'] ?? [];

        $GLOBALS['TL_LANG']['MSC'] = [
            'cal_timeSeparator' => ' - ',
            'cal_single_days' => 'daily',
            'cal_repeat' => '%s|%s|%s|%s',
        ];

        $this->urlGenerator = $this->createMock(ContentUrlGenerator::class);
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_LANG'] = $this->languageBackup;
    }

    public function testTheNextOccurrenceIsShown(): void
    {
        $data = $this->adjust(null);

        $this->assertSame($this->time('2026-06-04 09:00'), $data['begin']);
        $this->assertSame($this->time('2026-06-04 10:00'), $data['end']);
        $this->assertSame('2026-06-04', $data['date']);
        $this->assertSame('09:00 - 10:00', $data['time']);
        $this->assertSame('2026-06-04T09:00:00+00:00', $data['datetime']);
        $this->assertSame('daily||2026-06-04T09:00:00+00:00|2026-06-04 09:00 - 10:00', $data['recurring']);
    }

    public function testTheRequestedOccurrenceIsShown(): void
    {
        $data = $this->adjust($this->time('2026-06-02 09:00').','.$this->time('2026-06-02 10:00'));

        $this->assertSame($this->time('2026-06-02 09:00'), $data['begin']);
        $this->assertSame('2026-06-02', $data['date']);
        // The class of the core ("upcoming") is replaced by the state of the shown occurrence
        $this->assertSame(' bygone', $data['class']);
        // The text about the repetition always starts at the next occurrence
        $this->assertStringContainsString('2026-06-04', (string) $data['recurring']);
    }

    public function testARequestedTimeThatIsNoOccurrenceIsIgnored(): void
    {
        $data = $this->adjust($this->time('2026-06-02 11:00').','.$this->time('2026-06-02 12:00'));

        $this->assertSame($this->time('2026-06-04 09:00'), $data['begin']);
        $this->assertSame(' upcoming', $data['class']);
    }

    public function testTheEventItselfIsShownIfThereIsNoUpcomingOccurrence(): void
    {
        $data = $this->adjust(null, $this->time('2026-07-01 12:00'));

        $this->assertSame($this->time('2026-06-01 09:00'), $data['begin']);
        $this->assertSame(' bygone', $data['class']);
    }

    public function testTheUpcomingAndPastDatesFollowTheShownOccurrence(): void
    {
        $data = $this->adjust($this->time('2026-06-03 09:00').','.$this->time('2026-06-03 10:00'));

        $upcoming = $data['getUpcomingDates'](5);
        $this->assertSame([$this->time('2026-06-04 09:00'), $this->time('2026-06-05 09:00')], array_column($upcoming, 'begin'));
        $this->assertSame('2026-06-04', $upcoming[0]['date']);
        $this->assertSame('09:00 - 10:00', $upcoming[0]['time']);

        $this->assertSame([$this->time('2026-06-01 09:00'), $this->time('2026-06-02 09:00')], array_column($data['getPastDates'](5), 'begin'));
        $this->assertCount(1, $data['getUpcomingDates'](1));
        $this->assertSame([], $data['getUpcomingDates'](0));
    }

    public function testTheSchemaOrgDataGetsTheUrlAndTheStartOfTheShownOccurrence(): void
    {
        $start = $this->time('2026-06-02 09:00');
        $end = $this->time('2026-06-02 10:00');

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->isInstanceOf(CalendarEventsModel::class), ['day' => '20260602', 'times' => $start.','.$end])
            ->willReturn('/events/concert.html?day=20260602')
        ;

        $data = $this->adjust($start.','.$end);

        $this->assertSame(
            ['@type' => 'Event', 'url' => '/events/concert.html?day=20260602', 'startDate' => '2026-06-02T09:00:00+00:00'],
            $data['getSchemaOrgData'](),
        );
    }

    public function testTemplatesWithoutTheReaderFunctionsAreNotChanged(): void
    {
        $template = new FragmentTemplate('event_list', static fn (): Response => new Response());
        $template->set('id', 1);
        $template->set('class', ' upcoming');

        $hook = new ParseTemplateHook(new RequestStack(), $this->urlGenerator);
        $hook($template);

        $this->assertSame(['id' => 1, 'class' => ' upcoming'], $template->getData());
    }

    /**
     * @return array<string, mixed> the data of the template
     */
    private function adjust(string|null $times, int|null $now = null): array
    {
        $template = new FragmentTemplate('event_full', static fn (): Response => new Response());
        $template->set('id', 1);
        $template->set('class', ' upcoming');
        $template->set('getUpcomingDates', static fn (): array => []);
        $template->set('getPastDates', static fn (): array => []);
        $template->set('getSchemaOrgData', static fn (): array => ['@type' => 'Event']);

        $recurrences = [];

        foreach (range(1, 5) as $day) {
            $start = $this->time('2026-06-0'.$day.' 09:00');
            $recurrences[$start] = ['int_start' => $start, 'int_end' => $start + 3600, 'str_start' => '', 'str_end' => ''];
        }

        $event = Models::event([
            'id' => '1',
            'addTime' => '1',
            'startTime' => (string) $this->time('2026-06-01 09:00'),
            'endTime' => (string) $this->time('2026-06-01 10:00'),
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'days', 'value' => 1]),
            'recurrences' => '0',
            'allRecurrences' => serialize($recurrences),
        ]);

        $hook = new ParseTemplateHook(new RequestStack(), $this->urlGenerator);
        $hook->adjustTemplate($template, $event, Models::page(['dateFormat' => 'Y-m-d', 'timeFormat' => 'H:i', 'datimFormat' => 'Y-m-d H:i']), $times, $now ?? $this->time('2026-06-03 12:00'));

        return $template->getData();
    }

    private function time(string $date): int
    {
        return (int) strtotime($date);
    }
}
