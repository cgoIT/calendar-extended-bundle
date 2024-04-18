<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\calendar-extended-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) Kester Mielke
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     Kester Mielke
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\CalendarExtendedBundle\EventListener\DataContainer;

use Contao\ArrayUtil;
use Contao\Backend;
use Contao\Date;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use MenAtWork\MultiColumnWizardBundle\Contao\Widgets\MultiColumnWizard;

class CalendarEventsMCWCallbacks extends Backend
{
    /**
     * listMultiExceptions().
     *
     * Read the list of exception dates from the db to fill the select list
     *
     * @return array<mixed>
     */
    public function listMultiExceptions(MultiColumnWizard $mcw): array
    {
        $activeRecord = $mcw->activeRecord;

        // arrays for the select fields
        $arrSource1 = [];
        $arrSource2 = [];
        $arrSource3 = [];
        $arrSource4 = [];

        if (Input::get('id')) {
            // Probably an AJAX request where activeRecord is not available
            if (null === $activeRecord) {
                $db = System::getContainer()->get('database_connection');
                $activeRecord = $db
                    ->prepare("SELECT * FROM {$mcw->dataContainer->table} WHERE id=?")
                    ->executeQuery(Input::get('id'))
                ;
            }

            if ($activeRecord->repeatDates) {
                $arrDates = StringUtil::deserialize($activeRecord->repeatDates);

                if (\is_array($arrDates)) {
                    if ('repeatExceptions' === $mcw->id) {
                        // fill array for option date
                        foreach (array_keys($arrDates) as $k) {
                            $date = Date::parse($GLOBALS['TL_CONFIG']['dateFormat'], $k);
                            $arrSource1[$k] = $date;
                        }
                    }

                    // fill array for option action
                    $arrSource2['move'] = $GLOBALS['TL_LANG']['tl_calendar_events']['move'];
                    $arrSource2['hide'] = $GLOBALS['TL_LANG']['tl_calendar_events']['hide'];
                }
            }

            // fill array for option new date
            $moveDays = (int) $GLOBALS['TL_CONFIG']['tl_calendar_events']['moveDays'] ?: 7;
            $start = $moveDays * -1;
            $end = $moveDays * 2;

            for ($i = 0; $i <= $end; ++$i) {
                $arrSource3[$start.' days'] = $start.' '.$GLOBALS['TL_LANG']['tl_calendar_events']['days'];
                ++$start;
            }

            [$start, $end, $interval] = explode('|', (string) $GLOBALS['TL_CONFIG']['tl_calendar_events']['moveTimes']);

            // fill array for option new time
            $start = strtotime($start);
            $end = strtotime($end);

            while ($start <= $end) {
                $newTime = Date::parse($GLOBALS['TL_CONFIG']['timeFormat'], $start);
                $arrSource4[$newTime] = $newTime;
                $start = strtotime('+ '.$interval.' minutes', $start);
            }
        }

        $columnFields = [
            'new_start' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['new_start'],
                'exclude' => true,
                'inputType' => 'select',
                'options' => $arrSource4,
                'eval' => ['style' => 'width:60px', 'includeBlankOption' => true],
            ],
            'new_end' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['new_end'],
                'exclude' => true,
                'inputType' => 'select',
                'options' => $arrSource4,
                'eval' => ['style' => 'width:60px', 'includeBlankOption' => true],
            ],
            'action' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['action'],
                'exclude' => true,
                'inputType' => 'select',
                'options' => $arrSource2,
                'eval' => ['style' => 'width:80px', 'includeBlankOption' => true],
            ],
            'new_exception' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['new_exception'],
                'exclude' => true,
                'inputType' => 'select',
                'options' => $arrSource3,
                'eval' => ['style' => 'width:80px', 'includeBlankOption' => true],
            ],
            'cssclass' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['cssclass'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:50px'],
            ],
            'reason' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['reason'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:150px'],
            ],
        ];

        // normal exceptions by date
        if ('repeatExceptions' === $mcw->id) {
            $firstField = [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['exception'],
                'exclude' => true,
                'inputType' => 'select',
                'options' => $arrSource1,
                'eval' => ['style' => 'width:120px', 'includeBlankOption' => true],
            ];
        } // exceptions by interval
        elseif ('repeatExceptionsInt' === $mcw->id) {
            $firstField = [
                'label' => $GLOBALS['TL_LANG']['tl_calendar_events']['exceptionInt'].$GLOBALS['TL_LANG']['DAYS'][$activeRecord->weekday],
                'exclude' => true,
                'inputType' => 'select',
                'options' => ['first', 'second', 'third', 'fourth', 'fifth', 'last'],
                'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
                'eval' => ['style' => 'width:120px', 'includeBlankOption' => true],
            ];
        } // exceptions by time period
        elseif ('repeatExceptionsPer' === $mcw->id) {
            $firstField = [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['exceptionFr'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'date', 'doNotCopy' => true, 'style' => 'width:100px', 'datepicker' => true, 'tl_class' => 'wizard'],
            ];
            $secondField = [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['exceptionTo'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'date', 'doNotCopy' => true, 'style' => 'width:100px', 'datepicker' => true, 'tl_class' => 'wizard'],
            ];
            $columnFields['reason'] = [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['reason'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:80px'],
            ];

            // add the field to the columnFields array
            ArrayUtil::arrayInsert($columnFields, 0, ['exceptionTo' => $secondField]);
        }

        // add the field to the columnFields array
        if (!empty($firstField)) {
            ArrayUtil::arrayInsert($columnFields, 0, ['exception' => $firstField]);
        }

        return $columnFields;
    }

    /**
     * listFixedDates().
     *
     * @return array<mixed>
     */
    public function listFixedDates(MultiColumnWizard $mcw): array
    {
        return [
            'new_repeat' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['exception'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => true, 'style' => 'width:100px', 'tl_class' => 'wizard'],
            ],
            'new_start' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['new_start'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'time', 'datepicker' => true, 'doNotCopy' => true, 'style' => 'width:40px'],
            ],
            'new_end' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['new_end'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['rgxp' => 'time', 'datepicker' => true, 'doNotCopy' => true, 'style' => 'width:40px'],
            ],
            'reason' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['reason'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['doNotCopy' => true, 'style' => 'width:350px'],
            ],
        ];
    }
}
