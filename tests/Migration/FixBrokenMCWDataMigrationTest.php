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

use Cgoit\CalendarExtendedBundle\Migration\FixBrokenMCWDataMigration;

final class FixBrokenMCWDataMigrationTest extends MigrationTestCase
{
    public function testDoesNotRunWithoutBrokenValues(): void
    {
        $this->createTable();
        $this->insert('tl_calendar_events', ['id' => 1, 'repeatFixedDates' => serialize([['new_repeat' => '2025-01-01']]), 'repeatExceptions' => '', 'repeatExceptionsInt' => null, 'repeatExceptionsPer' => '']);

        $this->assertFalse((new FixBrokenMCWDataMigration($this->db))->shouldRun());
    }

    public function testRunsForValuesThatAreNotAnArray(): void
    {
        $this->createTable();
        $this->insert('tl_calendar_events', ['id' => 1, 'repeatFixedDates' => 'broken', 'repeatExceptions' => '', 'repeatExceptionsInt' => '', 'repeatExceptionsPer' => '']);

        $this->assertTrue((new FixBrokenMCWDataMigration($this->db))->shouldRun());
    }

    public function testRunsForEmptyArrays(): void
    {
        $this->createTable();
        $this->insert('tl_calendar_events', ['id' => 1, 'repeatFixedDates' => '', 'repeatExceptions' => serialize([]), 'repeatExceptionsInt' => '', 'repeatExceptionsPer' => '']);

        $this->assertTrue((new FixBrokenMCWDataMigration($this->db))->shouldRun());
    }

    public function testDoesNotRunWithoutTheColumns(): void
    {
        $this->db->executeStatement('CREATE TABLE tl_calendar_events (id INTEGER PRIMARY KEY, title TEXT)');

        $this->assertFalse((new FixBrokenMCWDataMigration($this->db))->shouldRun());
    }

    public function testResetsBrokenValuesAndKeepsValidOnes(): void
    {
        $valid = serialize([['exception' => '1741000000', 'action' => 'hide']]);

        $this->createTable();
        $this->insert('tl_calendar_events', ['id' => 1, 'repeatFixedDates' => 'broken', 'repeatExceptions' => $valid, 'repeatExceptionsInt' => serialize([]), 'repeatExceptionsPer' => '']);

        $migration = new FixBrokenMCWDataMigration($this->db);

        $this->assertTrue($migration->run()->isSuccessful());

        $row = $this->fetchRow('tl_calendar_events', 1);
        $this->assertNull($row['repeatFixedDates']);
        $this->assertSame($valid, $row['repeatExceptions']);
        $this->assertNull($row['repeatExceptionsInt']);
        $this->assertFalse($migration->shouldRun());
    }

    public function testHasAName(): void
    {
        $this->assertNotSame('', (new FixBrokenMCWDataMigration($this->db))->getName());
    }

    private function createTable(): void
    {
        $this->db->executeStatement('CREATE TABLE tl_calendar_events (id INTEGER PRIMARY KEY, repeatFixedDates TEXT, repeatExceptions TEXT, repeatExceptionsInt TEXT, repeatExceptionsPer TEXT)');
    }
}
