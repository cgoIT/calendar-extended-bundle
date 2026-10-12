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

namespace Cgoit\CalendarExtendedBundle\Tests\EventListener;

use Cgoit\CalendarExtendedBundle\EventListener\DataContainer\CalendarEventsCallbacks;
use Cgoit\CalendarExtendedBundle\Exception\CalendarExtendedException;
use Cgoit\CalendarExtendedBundle\Service\RecurrenceCalculator;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the callbacks of the event DCA (saving, validation).
 */
final class CalendarEventsCallbacksTest extends TestCase
{
    private const array MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /**
     * @var array<string, mixed>
     */
    private array $globalsBackup;

    protected function setUp(): void
    {
        $this->globalsBackup = ['TL_CONFIG' => $GLOBALS['TL_CONFIG'] ?? null, 'TL_LANG' => $GLOBALS['TL_LANG'] ?? null];

        $GLOBALS['TL_CONFIG']['datimFormat'] = 'Y-m-d H:i';
        $GLOBALS['TL_CONFIG']['dateFormat'] = 'Y-m-d';
        $GLOBALS['TL_LANG']['MONTHS'] = self::MONTHS;
        $GLOBALS['TL_LANG']['tl_calendar_events']['checkRecurring'] = 'Only one option can be active for recurrences.';
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_CONFIG'] = $this->globalsBackup['TL_CONFIG'];
        $GLOBALS['TL_LANG'] = $this->globalsBackup['TL_LANG'];
    }

    public function testIgnoreEndTimeSetsEndTimeToEndDateWithStartTime(): void
    {
        // Event spans 2025-03-03 to 2025-03-05, starts at 09:00, the end time is ignored
        $event = $this->event([
            'id' => 7,
            'endDate' => strtotime('2025-03-05'),
            'ignoreEndTime' => 1,
        ]);

        $update = $this->save($event);

        $this->assertSame(strtotime('2025-03-05 09:00:00'), $update['endTime']);
    }

    public function testDefaultRepeatEachExtPreselectsFirstWeekdayOfEvent(): void
    {
        $callbacks = $this->createCallbacks();

        // Sunday 2025-03-02
        $value = $callbacks->defaultRepeatEachExt('', $this->createDataContainer(['startDate' => strtotime('2025-03-02')]));

        $this->assertSame(['value' => 'first', 'unit' => 'sunday'], unserialize($value));
    }

    public function testDefaultRepeatEachExtFallsBackToMonday(): void
    {
        $callbacks = $this->createCallbacks();

        $value = $callbacks->defaultRepeatEachExt(null, $this->createDataContainer([]));

        $this->assertSame(['value' => 'first', 'unit' => 'monday'], unserialize($value));
    }

    public function testDefaultRepeatEachExtKeepsExistingValue(): void
    {
        $callbacks = $this->createCallbacks();
        $existing = serialize(['value' => 'last', 'unit' => 'friday']);

        $this->assertSame($existing, $callbacks->defaultRepeatEachExt($existing, $this->createDataContainer([])));
    }

    public function testCheckRecurrenceTypesThrowsIfBothAreSubmitted(): void
    {
        $this->expectException(CalendarExtendedException::class);
        $this->expectExceptionMessage('Only one option can be active for recurrences.');

        $this->createCallbacks()->checkRecurrenceTypes(['recurring' => '1', 'recurringExt' => '1'], $this->createDataContainer([]));
    }

    public function testCheckRecurrenceTypesThrowsIfSubmittedValueConflictsWithTheRecord(): void
    {
        $this->expectException(CalendarExtendedException::class);

        $this->createCallbacks()->checkRecurrenceTypes(['recurring' => '1'], $this->createDataContainer(['recurringExt' => 1]));
    }

    public function testCheckRecurrenceTypesAllowsSwitchingTheRecurrenceType(): void
    {
        // The stored record still has the extended recurrence, but the form unchecks it
        $values = ['recurring' => '1', 'recurringExt' => ''];

        $this->assertSame($values, $this->createCallbacks()->checkRecurrenceTypes($values, $this->createDataContainer(['recurringExt' => 1])));
    }

    public function testCheckRecurrenceTypesAllowsUnrelatedChanges(): void
    {
        $values = ['title' => 'Concert'];

        $this->assertSame($values, $this->createCallbacks()->checkRecurrenceTypes($values, $this->createDataContainer(['recurring' => 1])));
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $values
     */
    #[DataProvider('overlapChecksWithoutQuery')]
    public function testCheckOverlappingSkipsRecordsThatAreNotChecked(int $id, array $record, array $values): void
    {
        $dc = $this->createMock(DataContainer::class);
        $dc
            ->method('__get')
            ->with('id')
            ->willReturn($id)
        ;

        $dc
            ->method('getCurrentRecord')
            ->willReturn($record)
        ;

        $this->assertSame($values, $this->createCallbacks()->checkOverlapping($values, $dc));
    }

    /**
     * @return iterable<string, array{int, array<string, mixed>, array<string, mixed>}>
     */
    public static function overlapChecksWithoutQuery(): iterable
    {
        yield 'no record id' => [0, ['startDate' => 1741000000], ['title' => 'Concert']];
        yield 'core recurrence' => [1, ['startDate' => 1741000000, 'recurring' => 1], ['title' => 'Concert']];
        yield 'extended recurrence submitted' => [1, ['startDate' => 1741000000], ['recurringExt' => '1']];
        yield 'no start date' => [1, [], ['title' => 'Concert']];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function createDataContainer(array $record): DataContainer
    {
        $dc = $this->createMock(DataContainer::class);
        $dc
            ->method('getCurrentRecord')
            ->willReturn($record)
        ;

        return $dc;
    }

    /**
     * Runs the onsubmit callback and returns the data written to the database.
     *
     * @return array<string, mixed>
     */
    private function save(\stdClass $event): array
    {
        $dc = $this->createMock(DataContainer::class);
        $dc
            ->method('__get')
            ->with('id')
            ->willReturn($event->id)
        ;

        $dc
            ->method('getCurrentRecord')
            ->willReturn((array) $event)
        ;

        $update = [];
        $db = $this->createMock(Connection::class);
        $db
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $data) use (&$update): int {
                    $update = $data;

                    return 1;
                },
            )
        ;

        $callbacks = $this->createCallbacks($db);

        $callbacks->handleRecurrencesAndExceptions($dc);

        return $update;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function event(array $overrides = []): \stdClass
    {
        // Monday 2025-03-03, 09:00 - 10:00
        return (object) array_merge(
            [
                'startDate' => strtotime('2025-03-03'),
                'endDate' => strtotime('2025-03-03'),
                'addTime' => 1,
                'startTime' => strtotime('2025-03-03 09:00'),
                'endTime' => strtotime('2025-03-03 10:00'),
                'recurring' => 0,
                'repeatEach' => '',
                'repeatEnd' => 0,
                'recurrences' => 0,
                'repeatWeekday' => '',
                'hideOnWeekend' => 0,
                'recurringExt' => 0,
                'repeatEachExt' => '',
                'repeatFixedDates' => '',
                'useExceptions' => 0,
                'ignoreEndTime' => 0,
                'repeatExceptions' => '',
                'repeatExceptionsInt' => '',
                'repeatExceptionsPer' => '',
                'allRecurrences' => '',
                'weekday' => 1,
            ],
            $overrides,
        );
    }

    private function createCallbacks(Connection|null $db = null): CalendarEventsCallbacks
    {
        return new CalendarEventsCallbacks($db ?? $this->createStub(Connection::class), new RecurrenceCalculator(365, 250));
    }
}
