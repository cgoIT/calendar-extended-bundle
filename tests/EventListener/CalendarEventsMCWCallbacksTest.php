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

use Cgoit\CalendarExtendedBundle\EventListener\DataContainer\CalendarEventsMCWCallbacks;
use Contao\Input;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use MenAtWork\MultiColumnWizardBundle\Contao\Widgets\MultiColumnWizard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the columns of the MultiColumnWizard fields of the event DCA.
 */
final class CalendarEventsMCWCallbacksTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $globalsBackup;

    protected function setUp(): void
    {
        $this->globalsBackup = ['TL_CONFIG' => $GLOBALS['TL_CONFIG'] ?? null, 'TL_LANG' => $GLOBALS['TL_LANG'] ?? null];

        $GLOBALS['TL_CONFIG']['dateFormat'] = 'Y-m-d';
        $GLOBALS['TL_CONFIG']['timeFormat'] = 'H:i';
        $GLOBALS['TL_LANG']['DAYS'] = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $GLOBALS['TL_LANG']['tl_calendar_events'] = ['move' => 'move', 'hide' => 'hide', 'days' => 'days', 'exceptionInt' => 'Every '];

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $container = new ContainerBuilder();
        $container->set('request_stack', $requestStack);
        System::setContainer($container);

        Input::setGet('id', null);
    }

    protected function tearDown(): void
    {
        Input::setGet('id', null);

        // Remove the container again, other tests expect none
        (new \ReflectionProperty(System::class, 'objContainer'))->setValue(null, null);

        $GLOBALS['TL_CONFIG'] = $this->globalsBackup['TL_CONFIG'];
        $GLOBALS['TL_LANG'] = $this->globalsBackup['TL_LANG'];
    }

    public function testListFixedDatesReturnsTheColumns(): void
    {
        $columns = $this->createCallbacks()->listFixedDates($this->createWidget('repeatFixedDates'));

        $this->assertSame(['new_repeat', 'new_start', 'new_end', 'reason'], array_keys($columns));
        $this->assertSame('date', $columns['new_repeat']['eval']['rgxp']);
        $this->assertSame('time', $columns['new_start']['eval']['rgxp']);
    }

    public function testExceptionsWithoutRecordHaveNoOptions(): void
    {
        $columns = $this->createCallbacks()->listMultiExceptions($this->createWidget('repeatExceptions'));

        $this->assertSame(['exception', 'new_start', 'new_end', 'action', 'new_exception', 'cssclass', 'reason'], array_keys($columns));
        $this->assertSame([], $columns['exception']['options']);
        $this->assertSame([], $columns['new_start']['options']);
    }

    public function testExceptionsByDateOfferTheCalculatedDates(): void
    {
        Input::setGet('id', '7');
        $first = strtotime('2025-03-03 09:00');
        $second = strtotime('2025-03-10 09:00');

        $columns = $this->createCallbacks()->listMultiExceptions($this->createWidget('repeatExceptions', (object) ['allRecurrences' => serialize([$first => [], $second => []])]));

        $this->assertSame([$first => '2025-03-03', $second => '2025-03-10'], $columns['exception']['options']);
        $this->assertSame(['move' => 'move', 'hide' => 'hide'], $columns['action']['options']);
    }

    public function testMoveOptionsAreBuiltFromTheConfiguration(): void
    {
        Input::setGet('id', '7');

        $columns = $this->createCallbacks(2, ['from' => '08:00', 'to' => '09:00', 'interval' => 30])
            ->listMultiExceptions($this->createWidget('repeatExceptions', (object) ['allRecurrences' => serialize([1741000000 => []])]))
        ;

        $this->assertSame(['08:00' => '08:00', '08:30' => '08:30', '09:00' => '09:00'], $columns['new_start']['options']);
        $this->assertSame(['-2 days', '-1 days', '0 days', '1 days', '2 days'], array_keys($columns['new_exception']['options']));
        $this->assertSame('-2 days', array_key_first($columns['new_exception']['options']));
    }

    public function testExceptionsByIntervalOfferThePositions(): void
    {
        Input::setGet('id', '7');

        $columns = $this->createCallbacks()->listMultiExceptions($this->createWidget('repeatExceptionsInt', (object) ['weekday' => 1, 'allRecurrences' => '']));

        $this->assertSame(['exception', 'new_start', 'new_end', 'action', 'new_exception', 'cssclass', 'reason'], array_keys($columns));
        $this->assertSame(['first', 'second', 'third', 'fourth', 'fifth', 'last'], $columns['exception']['options']);
        $this->assertSame('Every Monday', $columns['exception']['label']);
    }

    public function testExceptionsByPeriodHaveAStartAndAnEndDate(): void
    {
        Input::setGet('id', '7');

        $columns = $this->createCallbacks()->listMultiExceptions($this->createWidget('repeatExceptionsPer', (object) ['allRecurrences' => '']));

        $this->assertSame(['exception', 'exceptionTo', 'new_start', 'new_end', 'action', 'new_exception', 'cssclass', 'reason'], array_keys($columns));
        $this->assertSame('date', $columns['exception']['eval']['rgxp']);
        $this->assertSame('date', $columns['exceptionTo']['eval']['rgxp']);
    }

    public function testTheRecordIsReadFromTheDatabaseWithoutActiveRecord(): void
    {
        Input::setGet('id', '7');
        $first = strtotime('2025-03-03 09:00');

        $result = $this->createStub(Result::class);
        $result
            ->method('fetchAssociative')
            ->willReturn(['allRecurrences' => serialize([$first => []])])
        ;

        $db = $this->createMock(Connection::class);
        $db
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT tl_calendar_events.* FROM tl_calendar_events WHERE id=?', ['7'])
            ->willReturn($result)
        ;

        $columns = $this->createCallbacks(7, null, $db)->listMultiExceptions($this->createWidget('repeatExceptions'));

        $this->assertSame([$first => '2025-03-03'], $columns['exception']['options']);
    }

    /**
     * @param array<string, mixed>|null $moveTimes
     */
    private function createCallbacks(int $moveDays = 7, array|null $moveTimes = null, Connection|null $db = null): CalendarEventsMCWCallbacks
    {
        return new CalendarEventsMCWCallbacks($db ?? $this->createStub(Connection::class), $moveDays, $moveTimes ?? ['from' => '00:00', 'to' => '23:59', 'interval' => 15]);
    }

    private function createWidget(string $id, object|null $activeRecord = null): MultiColumnWizard
    {
        $widget = (new \ReflectionClass(MultiColumnWizard::class))->newInstanceWithoutConstructor();
        $widget->id = $id;
        // @phpstan-ignore assign.propertyType
        $widget->dataContainer = (object) ['table' => 'tl_calendar_events', 'activeRecord' => $activeRecord];

        return $widget;
    }
}
