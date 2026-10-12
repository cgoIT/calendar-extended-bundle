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

namespace Cgoit\CalendarExtendedBundle\Tests\Migration;

use Cgoit\CalendarExtendedBundle\Migration\CopyHolidayCalendarsMigration;

final class CopyHolidayCalendarsMigrationTest extends MigrationTestCase
{
    public function testDoesNotRunWithoutTheHolidayColumn(): void
    {
        $this->db->executeStatement('CREATE TABLE tl_module (id INTEGER PRIMARY KEY, cal_calendar TEXT)');

        $this->assertFalse((new CopyHolidayCalendarsMigration($this->db))->shouldRun());
    }

    public function testDoesNotRunIfNoHolidayCalendarIsSet(): void
    {
        $this->createTable();
        $this->insert('tl_module', ['id' => 1, 'cal_holiday' => '', 'cal_calendar' => serialize(['1'])]);

        $this->assertFalse((new CopyHolidayCalendarsMigration($this->db))->shouldRun());
    }

    public function testMergesTheHolidayCalendarsIntoTheCalendars(): void
    {
        $this->createTable();
        $this->insert('tl_module', ['id' => 1, 'cal_holiday' => serialize(['5', '6']), 'cal_calendar' => serialize(['1', '2'])]);
        $this->insert('tl_module', ['id' => 2, 'cal_holiday' => '', 'cal_calendar' => serialize(['3'])]);

        $migration = new CopyHolidayCalendarsMigration($this->db);

        $this->assertTrue($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());

        $merged = $this->fetchRow('tl_module', 1);
        $this->assertSame(['1', '2', '5', '6'], unserialize($merged['cal_calendar']));
        $this->assertSame('', $merged['cal_holiday']);
        $this->assertSame(['3'], unserialize($this->fetchRow('tl_module', 2)['cal_calendar']));
        $this->assertFalse($migration->shouldRun());
    }

    public function testHandlesAModuleWithoutCalendars(): void
    {
        $this->createTable();
        $this->insert('tl_module', ['id' => 1, 'cal_holiday' => serialize(['5']), 'cal_calendar' => null]);

        (new CopyHolidayCalendarsMigration($this->db))->run();

        $this->assertSame(['5'], unserialize($this->fetchRow('tl_module', 1)['cal_calendar']));
    }

    public function testHasAName(): void
    {
        $this->assertNotSame('', (new CopyHolidayCalendarsMigration($this->db))->getName());
    }

    private function createTable(): void
    {
        $this->db->executeStatement('CREATE TABLE tl_module (id INTEGER PRIMARY KEY, cal_holiday TEXT, cal_calendar TEXT)');
    }
}
