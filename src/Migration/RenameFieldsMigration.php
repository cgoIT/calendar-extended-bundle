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

namespace Cgoit\CalendarExtendedBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class RenameFieldsMigration extends AbstractMigration
{
    /**
     * @var array<mixed>
     */
    private static array $arrColumns = [
        'show_holiday' => 'hide_holiday',
        'cellhight' => 'cellheight',
    ];

    private static string $strTable = 'tl_module';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getName(): string
    {
        $sName = implode(', ', array_keys(self::$arrColumns));

        return 'Rename fields '.$sName;
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->db->createSchemaManager();

        $cols = $schemaManager->listTableColumns(self::$strTable);
        $t = self::$strTable;

        foreach (self::$arrColumns as $oldCol => $newCol) {
            if (isset($cols[$oldCol], $cols[$newCol])) {
                $arrResult = $this->db->prepare("SELECT id FROM $t WHERE $t.$oldCol <> $t.$newCol")
                    ->executeQuery()
                    ->fetchAllAssociative()
                ;
                if (!empty($arrResult)) {
                    return true;
                }
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

        foreach (self::$arrColumns as $oldCol => $newCol) {
            $arrResult = $this->db->prepare("SELECT id, $oldCol FROM $t")
                ->executeQuery()
                ->fetchAllAssociative()
            ;

            foreach ($arrResult as $result) {
                $val = $result[$oldCol];

                $arrSet = [$newCol => $val];
                $this->db->update($t, $arrSet, ['id' => $result['id']]);
            }
        }

        return $this->createResult(true);
    }
}
