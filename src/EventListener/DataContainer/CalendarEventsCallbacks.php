<?php

declare(strict_types=1);

use Cgoit\CalendarExtendedBundle\Models\CalendarEventsModelExt;
use Cgoit\CalendarExtendedBundle\Models\CalendarLeadsModel;
use Contao\ArrayUtil;
use Contao\Backend;
use Contao\BackendUser;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Date;
use Contao\FormModel;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

class CalendarEventsCallbacks extends Backend
{
    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
        $this->import(BackendUser::class, 'User');
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function checkOverlapping(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return false;
        }

        // Return if there event is recurring
        if ($dc->activeRecord->recurring || $dc->activeRecord->recurringExt) {
            return false;
        }

        // Set start date
        $intStart = $dc->activeRecord->startDate;
        $intEnd = $dc->activeRecord->startDate;

        $intStart = strtotime(date('d.m.Y', $intStart).' 00:00');

        // Set end date
        if (strlen((string) $dc->activeRecord->endDate)) {
            if ($dc->activeRecord->endDate > $dc->activeRecord->startDate) {
                $intEnd = $dc->activeRecord->endDate;
            } else {
                $intEnd = $dc->activeRecord->startDate;
            }
        }
        $intEnd = strtotime(date('d.m.Y', $intEnd).' 23:59');

        // Add time
        if ($dc->activeRecord->addTime) {
            $intStart = strtotime(date('d.m.Y', $intStart).' '.date('H:i:s', $dc->activeRecord->startTime));
            $intEnd = strtotime(date('d.m.Y', $intEnd).' '.date('H:i:s', $dc->activeRecord->endTime));
        }

        // Check if we have time overlapping events
        $uniqueEvents = CalendarModel::findById($dc->activeRecord->pid)->uniqueEvents ? true : false;

        if ($uniqueEvents) {
            // array for events
            $nonUniqueEvents = [];

            // find all events
            $objEvents = CalendarEventsModelExt::findCurrentByPid(
                (int) $dc->activeRecord->pid,
                (int) $dc->activeRecord->startTime,
                (int) $dc->activeRecord->endTime,
            );

            if (null !== $objEvents) {
                while ($objEvents->next()) {
                    // do not add the event with the current id
                    if ($objEvents->id === $dc->activeRecord->id) {
                        continue;
                    }

                    // findCurrentByPid also returns recurring events. therefor we have to check the times
                    if (
                        ($intStart > $objEvents->startTime && $intStart < $objEvents->endTime)
                        || ($intEnd > $objEvents->startTime && $intEnd < $objEvents->endTime)
                        || ($intStart < $objEvents->startTime && $intEnd > $objEvents->endTime)
                        || ($intStart === $objEvents->startTime && $intEnd === $objEvents->endTime)
                    ) {
                        $nonUniqueEvents[] = $objEvents->id;
                    }
                }

                if (count($nonUniqueEvents) > 0) {
                    Message::addError($GLOBALS['TL_LANG']['tl_calendar_events']['nonUniqueEvents'].' ('.implode(',', $nonUniqueEvents).')');
                    $this->redirect($this->addToUrl());
                }
            }
        }

        return true;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function adjustTime(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $maxCount = $GLOBALS['TL_CONFIG']['tl_calendar_events']['maxRepeatExceptions'] ?: 365;
        $maxELCount = 250;

        $arrSet['weekday'] = (int) date('w', $dc->activeRecord->startDate);
        $arrSet['startTime'] = (int) $dc->activeRecord->startDate;
        $arrSet['endTime'] = (int) $dc->activeRecord->startDate;

        // Set end date
        if (strlen((string) $dc->activeRecord->endDate)) {
            if ($dc->activeRecord->endDate > $dc->activeRecord->startDate) {
                $arrSet['endDate'] = (int) $dc->activeRecord->endDate;
                $arrSet['endTime'] = (int) $dc->activeRecord->endDate;
            } else {
                $arrSet['endDate'] = (int) $dc->activeRecord->startDate;
                $arrSet['endTime'] = (int) $dc->activeRecord->startDate;
            }
        }

        // Add time
        if ($dc->activeRecord->addTime) {
            $arrSet['startTime'] = strtotime(date('d.m.Y', $arrSet['startTime']).' '.date('H:i:s', $dc->activeRecord->startTime));

            if (!$dc->activeRecord->ignoreEndTime) {
                $arrSet['endTime'] = strtotime(date('d.m.Y', $arrSet['endTime']).' '.date('H:i:s', $dc->activeRecord->endTime));
            }
        }

        // Set endtime to starttime always...
        if ($dc->activeRecord->addTime && $dc->activeRecord->ignoreEndTime) {
            // $arrSet['endTime'] = strtotime(date('d.m.Y', $arrSet['endTime']) . ' 23:59:59');
            $arrSet['endTime'] = $arrSet['startTime'];
        } // Adjust end time of "all day" events
        elseif ((strlen((string) $dc->activeRecord->endDate) && $arrSet['endDate'] === $arrSet['endTime']) || $arrSet['startTime'] === $arrSet['endTime']) {
            $arrSet['endTime'] = strtotime('+ 1 day', $arrSet['endTime']) - 1;
        }

        $arrSet['repeatEnd'] = $arrSet['endTime'];

        // Array of possible repeatEnd dates...
        $maxRepeatEnd = [];
        $maxRepeatEnd[] = $arrSet['repeatEnd'];

        // Array of all recurrences
        $arrAllRecurrences = [];

        // Set the repeatEnd date
        $arrFixDates = [];
        $arrayFixedDates = StringUtil::deserialize($dc->activeRecord->repeatFixedDates) ?: null;

        if (null !== $arrayFixedDates) {
            usort(
                $arrayFixedDates,
                static function ($a, $b) {
                    $intTimeStampA = strtotime($a['new_repeat'].$a['new_start']);
                    $intTimeStampB = strtotime($b['new_repeat'].$b['new_start']);

                    return $intTimeStampA === $intTimeStampB;
                },
            );

            foreach ($arrayFixedDates as $fixedDate) {
                // Check if we have a date
                if (!strlen((string) $fixedDate['new_repeat'])) {
                    continue;
                }

                // Check the date
                try {
                    $newDate = new Date($fixedDate['new_repeat']);
                } catch (Exception) {
                    return false;
                }

                // $new_fix_date = strtotime($fixedDate['new_repeat']);
                $new_fix_date = $fixedDate['new_repeat'];

                // Check if we have a new start time new_fix_start_time =
                // strlen($fixedDate['new_start']) ? $fixedDate['new_start'] : date('H:i',
                // $arrSet['startTime']);
                $new_fix_start_time = strlen((string) $fixedDate['new_start']) ? date('H:i', $fixedDate['new_start']) : date('H:i', $arrSet['startTime']);
                // $new_fix_end_time = strlen($fixedDate['new_end']) ? $fixedDate['new_end'] :
                // date('H:i', $arrSet['endTime']);
                $new_fix_end_time = strlen((string) $fixedDate['new_end']) ? date('H:i', $fixedDate['new_end']) : date('H:i', $arrSet['endTime']);

                $new_fix_start_date = strtotime(date('d.m.Y', $new_fix_date).' '.date('H:i', strtotime($new_fix_start_time)));
                $new_fix_end_date = strtotime(date('d.m.Y', $new_fix_date).' '.date('H:i', strtotime($new_fix_end_time)));

                $arrFixDates[$new_fix_start_date] = date('d.m.Y H:i', $new_fix_start_date);
                $arrAllRecurrences[$new_fix_start_date] = [
                    'int_start' => $new_fix_start_date,
                    'int_end' => $new_fix_end_date,
                    'str_start' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $new_fix_start_date),
                    'str_end' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $new_fix_end_date),
                ];
                $maxRepeatEnd[] = $new_fix_end_date;
            }
            // PW: keep custom sorting arrSet['repeatFixedDates'] = $arrayFixedDates;
        } else {
            $arrSet['repeatFixedDates'] = null;
        }

        // changed default recurring
        if ($dc->activeRecord->recurring) {
            $arrRange = StringUtil::deserialize($dc->activeRecord->repeatEach);

            $arg = $arrRange['value'] * $dc->activeRecord->recurrences;
            $unit = $arrRange['unit'];

            $strtotime = '+ '.$arg.' '.$unit;
            $arrSet['repeatEnd'] = strtotime($strtotime, $arrSet['endTime']);

            // store the list of dates
            $next = $arrSet['startTime'];
            $nextEnd = $arrSet['endTime'];
            $count = $dc->activeRecord->recurrences;

            // array of the exception dates
            $arrDates = [];
            $arrDates[$next] = date('d.m.Y H:i', $next);

            // array of all recurrences
            $arrAllRecurrences[$next] = [
                'int_start' => $next,
                'int_end' => $nextEnd,
                'str_start' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $next),
                'str_end' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $nextEnd),
            ];

            if (0 === $count) {
                $arrSet['repeatEnd'] = 2145913200;
            }

            // last date of the recurrences
            $end = $arrSet['repeatEnd'];

            while ($next <= $end) {
                $timetoadd = '+ '.$arrRange['value'].' '.$unit;

                // Check if we are at the end
                if (!strtotime($timetoadd, $next)) {
                    break;
                }

                $strtotime = strtotime($timetoadd, $next);
                $next = $strtotime;
                //                $weekday = date('w', $next); check if we are at the end
                if ($next >= $end) {
                    break;
                }
                // TODO check what this is doing, $store is never read afterwards $value = (int)
                // $arrRange['value'];                $wdays =
                // is_array(StringUtil::deserialize($dc->activeRecord->repeatWeekday))         ?
                // StringUtil::deserialize($dc->activeRecord->repeatWeekday)         : false; if
                // ('days' === $unit && 1 === $value && $wdays) {   $wday = date('N', $next);
                // $store = in_array($wday, $wdays, true);     } $store = true;       if
                // ($dc->activeRecord->hideOnWeekend) {          if (0 === $weekday || 6 ===
                // $weekday) { $store = false;      }                }
                $arrDates[$next] = date('d.m.Y H:i', $next);
                // array of all recurrences
                $strtotime = strtotime($timetoadd, $nextEnd);
                $nextEnd = $strtotime;
                $arrAllRecurrences[$next] = [
                    'int_start' => $next,
                    'int_end' => $nextEnd,
                    'str_start' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $next),
                    'str_end' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $nextEnd),
                ];

                // check if have the configured max value
                if (count($arrDates) === $maxCount) {
                    break;
                }
            }
            $maxRepeatEnd[] = $arrSet['repeatEnd'];
        }

        // list of months we need
        $arrMonth = [1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april', 5 => 'may', 6 => 'june',
            7 => 'july', 8 => 'august', 9 => 'september', 10 => 'october', 11 => 'november', 12 => 'december',
        ];

        // extended version recurring
        if ($dc->activeRecord->recurringExt) {
            $arrRange = StringUtil::deserialize($dc->activeRecord->repeatEachExt);

            $arg = $arrRange['value'];
            $unit = $arrRange['unit'];

            // next month of the event
            $month = (int) date('n', $dc->activeRecord->startDate);
            // year of the event
            $year = (int) date('Y', $dc->activeRecord->startDate);
            // search date for the next event
            $next = (int) $arrSet['startTime'];
            $nextEnd = $arrSet['endTime'];

            // last month
            $count = (int) $dc->activeRecord->recurrences;

            // array of the exception dates
            $arrDates = [];
            $arrDates[$next] = date('d.m.Y H:i', $next);

            // array of all recurrences
            $arrAllRecurrences[$next] = [
                'int_start' => $next,
                'int_end' => $nextEnd,
                'str_start' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $next),
                'str_end' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $nextEnd),
            ];

            if ($count > 0) {
                for ($i = 0; $i < $count; ++$i) {
                    ++$month;

                    if (0 === $month % 13) {
                        $month = 1;
                        ++$year;
                    }

                    $timetoadd = $arg.' '.$unit.' of '.$arrMonth[$month].' '.$year;

                    if (!strtotime($timetoadd, $next)) {
                        break;
                    }

                    $strtotime = strtotime($timetoadd, $next);
                    $next = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $arrSet['startTime']));
                    $arrDates[$next] = date('d.m.Y H:i', $next);

                    // array of all recurrences
                    $strtotime = strtotime($timetoadd, $nextEnd);
                    $nextEnd = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $arrSet['endTime']));
                    $arrAllRecurrences[$next] = [
                        'int_start' => $next,
                        'int_end' => $nextEnd,
                        'str_start' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $next),
                        'str_end' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $nextEnd),
                    ];

                    // check if have the configured max value
                    if (count($arrDates) === $maxCount) {
                        break;
                    }
                }
                $arrSet['repeatEnd'] = $next;
            } else {
                // 2038.01.01
                $arrSet['repeatEnd'] = 2145913200;
                $end = $arrSet['repeatEnd'];

                while ($next <= $end) {
                    $timetoadd = $arg.' '.$unit.' of '.$arrMonth[$month].' '.$year;

                    if (!strtotime($timetoadd, $next)) {
                        break;
                    }

                    $strtotime = strtotime($timetoadd, $next);
                    $next = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $arrSet['startTime']));
                    $arrDates[$next] = date('d.m.Y H:i', $next);

                    ++$month;

                    if (0 === $month % 13) {
                        $month = 1;
                        ++$year;
                    }

                    // check if have the configured max value
                    if (count($arrDates) === $maxCount) {
                        break;
                    }
                }
            }

            $maxRepeatEnd[] = $arrSet['repeatEnd'];
        }
        unset($next);

        // the last repeatEnd Date
        if (count($maxRepeatEnd) > 1) {
            $arrSet['repeatEnd'] = max($maxRepeatEnd);
        }
        $currentEndDate = $arrSet['repeatEnd'];

        if ($dc->activeRecord->useExceptions) {
            // list of the exception
            $exceptionRows = [];

            // ... then we check them by interval...
            if ($dc->activeRecord->repeatExceptionsInt) {
                // weekday
                $unit = $GLOBALS['TL_CONFIG']['tl_calendar_events']['weekdays'][$dc->activeRecord->weekday];

                // exception rules
                $rows = StringUtil::deserialize($dc->activeRecord->repeatExceptionsInt);

                // run thru all dates
                foreach ($rows as $row) {
                    if (!$row['exception']) {
                        continue;
                    }

                    // now we have to find all dates matching the exception rules...
                    $arg = $row['exception'];

                    $searchNext = $arrSet['startTime'];
                    $searchEnd = $arrSet['repeatEnd'];
                    $month = (int) date('n', $searchNext);
                    $year = (int) date('Y', $searchNext);

                    while ($searchNext <= $searchEnd) {
                        $strDateToFind = $arg.' '.$unit.' of '.$arrMonth[$month].' '.$year;
                        $strDateToFind = strtotime($strDateToFind);
                        $searchNext = strtotime(date('d.m.Y', $strDateToFind).' '.date('H:i', $arrSet['startTime']));

                        if ($searchNext < $arrSet['startTime']) {
                            ++$month;

                            if (0 === $month % 13) {
                                $month = 1;
                                ++$year;
                            }
                            continue;
                        }

                        $row['new_start'] = $row['new_start'] ?: date('H:i', $dc->activeRecord->startTime); // '00:00';
                        $row['new_end'] = $row['new_end'] ?: date('H:i', $dc->activeRecord->endTime); // '23:59';
                        // Set endtime to starttime always...
                        if ($dc->activeRecord->ignoreEndTime) {
                            $row['new_end'] = '';
                        }

                        $row['exception'] = $searchNext;
                        $row['exception_date'] = date('d.m.Y H:i', $searchNext);

                        if (count($exceptionRows) < $maxELCount) {
                            $exceptionRows[$searchNext] = $row;
                        }

                        ++$month;

                        if (0 === $month % 13) {
                            $month = 1;
                            ++$year;
                        }
                    }
                }
            }

            // ... and last but not least by range
            if ($dc->activeRecord->repeatExceptionsPer) {
                // exception rules
                $rows = StringUtil::deserialize($dc->activeRecord->repeatExceptionsPer);

                // all recurrences...
                $repeatDates = StringUtil::deserialize($dc->activeRecord->repeatDates);

                // run thru all dates
                foreach ($rows as $row) {
                    if (!$row['exception']) {
                        continue;
                    }

                    $row['new_start'] = $row['new_start'] ?: date('H:i', $dc->activeRecord->startTime); // '00:00';
                    // Set endtime to starttime always...
                    if ($dc->activeRecord->ignoreEndTime) {
                        $row['new_end'] = '';
                    } else {
                        $row['new_end'] = $row['new_end'] ?: date('H:i', $dc->activeRecord->endTime); // '23:59';
                    }

                    // now we have to find all dates matching the exception rules...
                    $dateFrom = strtotime(date('Y-m-d', $row['exception']).' '.$row['new_start']);
                    $dateTo = strtotime(date('Y-m-d', $row['exceptionTo'] ?: $row['exception']).' '.$row['new_end']);
                    unset($row['exceptionTo']);

                    foreach (array_keys($repeatDates) as $k) {
                        if ($k >= $dateFrom && $k <= $dateTo) {
                            $row['exception'] = $k;
                            $row['exception_date'] = date('d.m.Y H:i', $k);

                            if (count($exceptionRows) < $maxELCount) {
                                $exceptionRows[$k] = $row;
                            }
                        }
                    }
                }
            }

            // first we check the exceptions by date...
            if ($dc->activeRecord->repeatExceptions) {
                $rows = StringUtil::deserialize($dc->activeRecord->repeatExceptions);

                // set repeatEnd my be we have an exception move that is later then the repeatEnd
                foreach ($rows as $row) {
                    if (!$row['exception']) {
                        continue;
                    }

                    $row['new_start'] = $row['new_start'] ?: date('H:i', $dc->activeRecord->startTime); // '00:00';

                    if (!$dc->activeRecord->ignoreEndTime) {
                        $row['new_end'] = $row['new_end'] ?: date('H:i', $dc->activeRecord->endTime); // '23:59';
                    }
                    $row['exception_date'] = date('d.m.Y H:i', $row['exception']);

                    $dateToFind = strtotime(date('d.m.Y', $row['exception']).' '.date('H:i', $dc->activeRecord->startTime));
                    $dateToSave = strtotime(date('d.m.Y', $row['exception']).' '.$row['new_start']);
                    $dateToSaveEnd = strtotime(date('d.m.Y', $row['exception']).' '.$row['new_end']);

                    // Set endtime to starttime always...
                    if ($dc->activeRecord->ignoreEndTime) {
                        $row['new_end'] = '';
                    }

                    if ('move' === $row['action']) {
                        $newDate = strtotime((string) $row['new_exception'], $row['exception']);

                        if ($newDate > $currentEndDate) {
                            $arrSet['repeatEnd'] = $newDate;
                            $maxRepeatEnd[] = $arrSet['repeatEnd'];
                        }

                        // Find the date and replace it
                        if (array_key_exists($dateToFind, $arrDates)) {
                            $arrDates[$dateToFind] = date('d.m.Y H:i', $dateToSave);
                        }

                        // Find the date and replace it
                        if (array_key_exists($dateToFind, $arrAllRecurrences)) {
                            $arrAllRecurrences[$dateToFind] = [
                                'int_start' => $dateToSave,
                                'int_end' => $dateToSaveEnd,
                                'str_start' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $dateToSave),
                                'str_end' => Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $dateToSaveEnd),
                                'moveReason' => $row['reason'] ?: '',
                            ];
                        }
                    }

                    if (count($exceptionRows) < $maxELCount) {
                        $exceptionRows[$row['exception']] = $row;
                    }
                }
            }

            if (count($exceptionRows) > 1) {
                ksort($exceptionRows);
            }
            $arrSet['exceptionList'] = count($exceptionRows) > 0 ? serialize($exceptionRows) : null;
        }

        if (count($maxRepeatEnd) > 1) {
            $arrSet['repeatEnd'] = max($maxRepeatEnd);
        }
        $arrAllDates = $arrDates + $arrFixDates;
        ksort($arrAllDates);
        // Set the array of dates
        $arrSet['repeatDates'] = $arrAllDates;
        ksort($arrAllRecurrences);
        // sort $arrAllRecurrences
        $arrSet['allRecurrences'] = $arrAllRecurrences;

        // Execute the update sql
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($dc->id);
        unset($maxRepeatEnd);
    }

    /**
     * Just check that only one option is active for recurring events.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.recurring.save')]
    public function checkRecurring(mixed $value, DataContainer $dc)
    {
        if ($value) {
            if ($dc->activeRecord->recurring && $dc->activeRecord->recurringExt) {
                throw new Exception($GLOBALS['TL_LANG']['tl_calendar_events']['checkRecurring']);
            }
        }

        return $value;
    }

    /**
     * Just check if any kind of recurring is in use.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.useExceptions.save')]
    public function checkExceptions(mixed $value, DataContainer $dc)
    {
        if ($value) {
            if (!$dc->activeRecord->recurring && !$dc->activeRecord->recurringExt) {
                throw new Exception($GLOBALS['TL_LANG']['tl_calendar_events']['checkExceptions']);
            }
        }

        return $value;
    }

    /**
     * @return string
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.regperson.load')]
    public function getMaxPerson(mixed $value, DataContainer $dc): mixed
    {
        $values = StringUtil::deserialize($value);

        if (!is_array($values)) {
            $values = [];
            $values[0]['mini'] = 0;
            $values[0]['maxi'] = 0;
            $values[0]['curr'] = 0;
            $values[0]['free'] = 0;

            return $values;
        }

        $eid = (int) $dc->activeRecord->id;
        $fid = (int) $dc->activeRecord->regform;
        $regCount = CalendarLeadsModel::regCountByFormEvent($fid, $eid);

        $values[0]['curr'] = (int) $regCount;
        $values[0]['mini'] = $values[0]['mini'] ? (int) $values[0]['mini'] : 0;
        $values[0]['maxi'] = $values[0]['maxi'] ? (int) $values[0]['maxi'] : 0;
        $useMaxi = $values[0]['maxi'] > 0 ? true : false;
        $values[0]['free'] = $useMaxi ? $values[0]['maxi'] - $values[0]['curr'] : 0;

        return serialize($values);
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.regform.options')]
    public function listRegForms(DataContainer|null $dc): mixed
    {
        if ($this->User->isAdmin) {
            $objForms = FormModel::findAll();
        } else {
            $objForms = FormModel::findMultipleByIds($this->User->forms);
        }

        $return = [];

        if (null !== $objForms) {
            while ($objForms->next()) {
                $return[$objForms->id] = $objForms->title;
            }
        }

        return $return;
    }

    /**
     * @return array
     */
    public function setMaxPerson(DataContainer $dc)
    {
        return [
            'mini' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['mini'],
                'default' => '0',
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:60px'],
            ],
            'maxi' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['maxi'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:60px'],
            ],
            'curr' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['curr'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:60px', 'disabled' => 'true'],
            ],
            'free' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['free'],
                'exclude' => true,
                'inputType' => 'text',
                'eval' => ['style' => 'width:60px', 'disabled' => 'true'],
            ],
        ];
    }

    /**
     * listMultiExceptions().
     *
     * Read the list of exception dates from the db to fill the select list
     */
    public function listMultiExceptions($var1)
    {
        $columnFields = null;
        $activeRecord = $var1->activeRecord;

        // arrays for the select fields
        $arrSource1 = [];
        $arrSource2 = [];
        $arrSource3 = [];
        $arrSource4 = [];

        if (Input::get('id')) {
            // Probably an AJAX request where activeRecord is not available
            if (null === $activeRecord) {
                $activeRecord = $this->db
                    ->prepare("SELECT * FROM {$var1->strTable} WHERE id=?")
                    ->limit(1)
                    ->execute(Input::get('id'))
                ;
            }

            if ($activeRecord->repeatDates) {
                $arrDates = StringUtil::deserialize($activeRecord->repeatDates);

                if (is_array($arrDates)) {
                    if ('repeatExceptions' === $var1->id) {
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
        if ('repeatExceptions' === $var1->id) {
            $firstField = [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['exception'],
                'exclude' => true,
                'inputType' => 'select',
                'options' => $arrSource1,
                'eval' => ['style' => 'width:120px', 'includeBlankOption' => true],
            ];
        } // exceptions by interval
        elseif ('repeatExceptionsInt' === $var1->id) {
            $firstField = [
                'label' => $GLOBALS['TL_LANG']['tl_calendar_events']['exceptionInt'].$GLOBALS['TL_LANG']['DAYS'][$activeRecord->weekday],
                'exclude' => true,
                'inputType' => 'select',
                'options' => ['first', 'second', 'third', 'fourth', 'fifth', 'last'],
                'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
                'eval' => ['style' => 'width:120px', 'includeBlankOption' => true],
            ];
        } // exceptions by time period
        elseif ('repeatExceptionsPer' === $var1->id) {
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
        ArrayUtil::arrayInsert($columnFields, 0, ['exception' => $firstField]);

        return $columnFields;
    }

    /**
     * listFixedDates().
     */
    public function listFixedDates($var1)
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
