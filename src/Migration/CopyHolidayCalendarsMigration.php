<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class CopyHolidayCalendarsMigration extends AbstractMigration
{
    private static string $holidayCalendarColumn = 'cal_holiday';

    private static string $calendarColumn = 'cal_calendar';

    private static string $strTable = 'tl_module';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getName(): string
    {
        return 'Copy holiday calendars to standard calendar field';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $t = self::$strTable;

        $schemaManager = $this->db->createSchemaManager();
        $cols = $schemaManager->listTableColumns($t);

        if (isset($cols[self::$holidayCalendarColumn])) {
            $arrResult = $this->db->prepare("SELECT id FROM $t WHERE ".self::$holidayCalendarColumn."<> ''")
                ->executeQuery()
                ->fetchAllAssociative()
            ;
            if (!empty($arrResult)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $t = self::$strTable;

        $arrResult = $this->db->prepare('SELECT id, '.self::$holidayCalendarColumn.', '.self::$calendarColumn." FROM $t")
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        foreach ($arrResult as $result) {
            $holidayCalendars = StringUtil::deserialize($result[self::$holidayCalendarColumn]);
            if (!empty($holidayCalendars) && \is_array($holidayCalendars)) {
                $calendars = StringUtil::deserialize($result[self::$calendarColumn], true);

                $arrSet = [self::$calendarColumn => serialize(array_merge($calendars, $holidayCalendars)), self::$holidayCalendarColumn => ''];
                $this->db->update($t, $arrSet, ['id' => $result['id']]);
            }
        }

        return $this->createResult(true);
    }
}
