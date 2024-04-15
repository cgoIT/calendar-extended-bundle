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

namespace Cgoit\CalendarExtendedBundle\Controller\Module;

use Cgoit\CalendarExtendedBundle\Classes\EventsExt;
use Cgoit\CalendarExtendedBundle\Models\CalendarEventsModelExt;
use Cgoit\CalendarExtendedBundle\Models\CalendarLeadsModel;
use Contao\BackendTemplate;
use Contao\Calendar;
use Contao\CalendarModel;
use Contao\Comments;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FilesModel;
use Contao\Form;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;

/**
 * Front end module "event reader".
 */
class ModuleEventReader extends EventsExt
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_event';

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.mb_strtoupper((string) $GLOBALS['TL_LANG']['FMD']['eventreader'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && Config::get('useAutoItem') && isset($_GET['auto_item'])) {
            Input::setGet('events', Input::get('auto_item'));
        }

        // Do not index or cache the page if no event has been specified
        if (!Input::get('events')) {
            /** @var PageModel $objPage */
            global $objPage;

            $objPage->noSearch = true;
            $objPage->cache = 0;

            return '';
        }

        $cals = $this->cal_holiday
      ? array_merge(StringUtil::deserialize($this->cal_calendar), StringUtil::deserialize($this->cal_holiday))
      : StringUtil::deserialize($this->cal_calendar);
        $this->cal_calendar = $this->sortOutProtected($cals);

        // Do not index or cache the page if there are no calendars
        if (empty($this->cal_calendar)) {
            /** @var PageModel $objPage */
            global $objPage;

            $objPage->noSearch = true;
            $objPage->cache = 0;

            return '';
        }

        return parent::generate();
    }

    /**
     * Generate the module.
     */
    protected function compile(): void
    {
        /** @var PageModel $objPage */
        global $objPage;

        $this->Template->event = '';

        if ($this->overviewPage) {
            $this->Template->referer = PageModel::findById($this->overviewPage)->getFrontendUrl();
            $this->Template->back = $this->customLabel ?: $GLOBALS['TL_LANG']['MSC']['eventOverview'];
        } else {
            trigger_deprecation('contao/calendar-bundle', '4.13', 'If you do not select an overview page in the event reader module, the "go back" link will no longer be shown in Contao 5.0.');

            $this->Template->referer = 'javascript:history.go(-1)';
            $this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];
        }

        // Get the current event
        $objEvent = CalendarEventsModelExt::findPublishedByParentAndIdOrAlias(Input::get('events'), $this->cal_calendar);

        // The event does not exist (see #33)
        if (null === $objEvent) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        // Add author info
        $objEvent->author_name = $objEvent->getRelated('author')->name ?: null;
        $objEvent->author_mail = $objEvent->getRelated('author')->email ?: null;

        // Overwrite the page title (see #2853 and #4955)
        if ('' !== $objEvent->title) {
            $objPage->pageTitle = strip_tags((string) StringUtil::stripInsertTags($objEvent->title));
        }

        // Overwrite the page description
        if ('' !== $objEvent->teaser) {
            $objPage->description = $this->prepareMetaDescription($objEvent->teaser);
        }

        $intStartTime = $objEvent->startTime;
        $intEndTime = $objEvent->endTime;
        $span = Calendar::calculateSpan($intStartTime, $intEndTime);

        // Save original times...
        $orgStartTime = $objEvent->startTime;
        $orgEndTime = $objEvent->endTime;

        // Do not show dates in the past if the event is recurring (see #923)
        if ($objEvent->recurring) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEach);

            while ($intStartTime < time() && $intEndTime < $objEvent->repeatEnd) {
                $intStartTime = strtotime('+'.$arrRange['value'].' '.$arrRange['unit'], $intStartTime);
                $intEndTime = strtotime('+'.$arrRange['value'].' '.$arrRange['unit'], $intEndTime);
            }
        }

        // Do not show dates in the past if the event is recurringExt
        if ($objEvent->recurringExt) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEachExt);

            // list of months we need
            $arrMonth = [
                1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april', 5 => 'may', 6 => 'june',
                7 => 'july', 8 => 'august', 9 => 'september', 10 => 'october', 11 => 'november', 12 => 'december',
            ];

            // month and year of the start date
            $month = date('n', $intStartTime);
            $year = date('Y', $intEndTime);

            while ($intStartTime < time() && $intEndTime < $objEvent->repeatEnd) {
                // find the next date
                $nextValueStr = $arrRange['value'].' '.$arrRange['unit'].' of '.$arrMonth[$month].' '.$year;
                $nextValueDate = strtotime($nextValueStr, $intStartTime);
                // add time to the new date
                $intStartTime = strtotime(date('Y-m-d', $nextValueDate).' ModuleEventReader.php'.date('H:i:s', $intStartTime));
                $intEndTime = strtotime(date('Y-m-d', $nextValueDate).' ModuleEventReader.php'.date('H:i:s', $intEndTime));

                ++$month;

                if (0 === $month % 13) {
                    $month = 1;
                    ++$year;
                }
            }
        }

        // Do not show dates in the past if the event is recurring irregular
        if (null !== $objEvent->repeatFixedDates) {
            $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);

            // Check if there are valid data in the array...
            if (\is_array($arrFixedDates) && \strlen((string) $arrFixedDates[0]['new_repeat'])) {
                foreach ($arrFixedDates as $fixedDate) {
                    $nextValueDate = $fixedDate['new_repeat'] ? strtotime((string) $fixedDate['new_repeat']) : $intStartTime;

                    if (\strlen((string) $fixedDate['new_start'])) {
                        $nextStartTime = strtotime(date('Y-m-d', $nextValueDate).' ModuleEventReader.php'.date('H:i:s', strtotime((string) $fixedDate['new_start'])));
                        $nextValueDate = $nextStartTime;
                    } else {
                        $nextStartTime = strtotime(date('Y-m-d', $nextValueDate).' ModuleEventReader.php'.date('H:i:s', $intStartTime));
                    }

                    if (\strlen((string) $fixedDate['new_end'])) {
                        $nextEndTime = strtotime(date('Y-m-d', $nextValueDate).' ModuleEventReader.php'.date('H:i:s', strtotime((string) $fixedDate['new_end'])));
                    } else {
                        $nextEndTime = strtotime(date('Y-m-d', $nextValueDate).' ModuleEventReader.php'.date('H:i:s', $intEndTime));
                    }

                    if ($nextValueDate > time() && $nextEndTime <= $objEvent->repeatEnd) {
                        $intStartTime = $nextStartTime;
                        $intEndTime = $nextEndTime;
                        break;
                    }
                }
            }
        }

        // Replace the date an time with the correct ones from the recurring event
        if (Input::get('times')) {
            [$intStartTime, $intEndTime] = explode(',', (string) Input::get('times'));
        }

        $strDate = Date::parse(Config::get('dateFormat'), $intStartTime);

        if ($span > 0) {
            $strDate = Date::parse(Config::get('dateFormat'), $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->dateFormat, $intEndTime);
        }

        $strTime = '';

        if ($objEvent->addTime) {
            if ($span > 0) {
                $strDate = Date::parse(Config::get('datimFormat'), $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->datimFormat, $intEndTime);
            } elseif ($intStartTime === $intEndTime) {
                $strTime = Date::parse(Config::get('timeFormat'), $intStartTime);
            } else {
                $strTime = Date::parse(Config::get('timeFormat'), $intStartTime).$GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'].Date::parse($objPage->timeFormat, $intEndTime);
            }
        }

        // Fix date if we have to ignore the time
        if (1 === (int) $objEvent->ignoreEndTime) {
            // $strDate = Date::parse($objPage->datimFormat, $objEvent->startTime) .
            // $GLOBALS['TL_LANG']['MSC']['cal_timeSeparator'] .
            // Date::parse($objPage->dateFormat, $objEvent->endTime); $strTime = null;
            $strDate = Date::parse(Config::get('dateFormat'), $objEvent->startTime);
            $objEvent->endTime = '';
            $objEvent->time = '';
        }

        $until = '';
        $recurring = '';

        // Recurring event
        if ($objEvent->recurring) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEach);

            if (\is_array($arrRange) && isset($arrRange['unit'], $arrRange['value'])) {
                $strKey = 'cal_'.$arrRange['unit'];
                $recurring = sprintf($GLOBALS['TL_LANG']['MSC'][$strKey], $arrRange['value']);

                if ($objEvent->recurrences > 0) {
                    $until = sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));
                }
            }
        }

        // Recurring eventExt
        if ($objEvent->recurringExt) {
            $arrRange = StringUtil::deserialize($objEvent->repeatEachExt);
            $strKey = 'cal_'.$arrRange['value'];
            $strVal = $GLOBALS['TL_LANG']['DAYS'][$GLOBALS['TL_LANG']['DAYS'][$arrRange['unit']]];
            $recurring = sprintf($GLOBALS['TL_LANG']['MSC'][$strKey], $strVal);

            if ($objEvent->recurrences > 0) {
                $until = sprintf($GLOBALS['TL_LANG']['MSC']['cal_until'], Date::parse($objPage->dateFormat, $objEvent->repeatEnd));
            }
        }

        // moveReason fix...
        $moveReason = null;

        // get moveReason from exceptions
        if ($objEvent->useExceptions) {
            $exceptions = StringUtil::deserialize($objEvent->exceptionList);

            if ($exceptions) {
                foreach ($exceptions as $fixedDate) {
                    // look for the reason only if we have a move action
                    if ('move' === $fixedDate['action']) {
                        // value to add to the old date
                        $addToDate = $fixedDate['new_exception'];
                        $newDate = strtotime((string) $addToDate, $fixedDate['exception']);

                        if (date('Ymd', $newDate) === date('Ymd', $intStartTime)) {
                            $moveReason = $fixedDate['reason'] ?: null;
                        }
                    }
                }
            }
        }

        // get moveReason from fixed dates if exists...
        if (null !== $objEvent->repeatFixedDates) {
            $arrFixedDates = StringUtil::deserialize($objEvent->repeatFixedDates);

            if (\is_array($arrFixedDates)) {
                foreach ($arrFixedDates as $fixedDate) {
                    if (date('Ymd', strtotime((string) $fixedDate['new_repeat'])) === date('Ymd', $intStartTime)) {
                        $moveReason = $fixedDate['reason'] ?: null;
                    }
                }
            }
        }

        // check the repeat values
        $unit = '';

        if ($objEvent->recurring) {
            $arrRepeat = StringUtil::deserialize($objEvent->repeatEach) ?: null;
            $unit = $arrRepeat['unit'];
        }

        if ($objEvent->recurringExt) {
            $arrRepeat = StringUtil::deserialize($objEvent->repeatEachExt) ?: null;
            $unit = $arrRepeat['unit'];
        }

        // get the configured weekdays if any
        $useWeekdays = ($weekdays = StringUtil::deserialize($objEvent->repeatWeekday)) ? true : false;

        // Set the next date
        $nextDate = null;

        if ($objEvent->repeatDates) {
            $arrNext = StringUtil::deserialize($objEvent->repeatDates);

            if (\is_array($arrNext)) {
                foreach ($arrNext as $k => $nextDate) {
                    if (strtotime((string) $nextDate) > time()) {
                        // check if we have the correct weekday
                        if ($useWeekdays && 'days' === $unit) {
                            if (!\in_array(date('w', $k), $weekdays, true)) {
                                continue;
                            }
                        }
                        $nextDate = Date::parse($objPage->datimFormat, $k);
                        break;
                    }
                }
            }
            $event['nextDate'] = $nextDate;
        }

        if ($objEvent->allRecurrences) {
            $objEvent->allRecurrences = StringUtil::deserialize($objEvent->allRecurrences);
        }

        /** @var FrontendTemplate|object $objTemplate */
        $objTemplate = new FrontendTemplate($this->cal_template ?: 'event_full');
        $objTemplate->setData($objEvent->row());

        $objTemplate->date = $strDate;
        $objTemplate->time = $strTime;
        $objTemplate->datetime = $objEvent->addTime ? date('Y-m-d\TH:i:sP', $intStartTime) : date('Y-m-d', $intStartTime);
        $objTemplate->begin = $intStartTime;
        $objTemplate->end = $intEndTime;
        $objTemplate->class = '' !== $objEvent->cssClass ? ' '.$objEvent->cssClass : '';
        $objTemplate->recurring = $recurring;
        $objTemplate->until = $until;
        $objTemplate->locationLabel = $GLOBALS['TL_LANG']['MSC']['location'];
        $objTemplate->calendar = $objEvent->getRelated('pid');
        $objTemplate->details = '';
        $objTemplate->hasDetails = false;
        $objTemplate->hasTeaser = false;

        $objTemplate->nextDate = $nextDate;
        $objTemplate->moveReason = $moveReason ?: null;

        // Formular für Anmeldung, wenn contao-leads installiert ist...
        $objTemplate->regform = null;

        // Event und Formular ID
        $eid = (int) $objEvent->id;
        $fid = (int) $objEvent->regform;

        // Prüfen, ob sich ein angemeldeter Benutzer schon registriert hat
        $showToUser = true;

        $security = System::getContainer()->get('@security.helper');

        if (($user = $security->getUser()) instanceof FrontendUser) {
            $email = $user->email;
            $showToUser = CalendarLeadsModel::regCheckByFormEventMail($fid, $eid, $email);
        }

        if (class_exists('leads\leads') && $objEvent->useRegistration && $showToUser) {
            // ... und im Event ein Formular ausgewählt wurde
            if ($objEvent->regform) {
                $values = StringUtil::deserialize($objEvent->regperson);

                // Anmeldungen ermittlen und anzeigen
                $regCount = CalendarLeadsModel::regCountByFormEvent($fid, $eid);

                // Werte setzen
                $values[0]['curr'] = (int) $regCount;
                $values[0]['mini'] = (int) $values[0]['mini'];
                $values[0]['maxi'] = (int) $values[0]['maxi'];

                $useMaxi = 0 === $values[0]['maxi'] ? false : true;

                $values[0]['free'] = $useMaxi ? $values[0]['maxi'] - $values[0]['curr'] : 0;
                $values[0]['info'] = $GLOBALS['TL_LANG']['MSC']['reginfo'];

                // Prüfen, ob ein Anmeldeschluss gesetzt ist
                $showForm = true;

                if ($objEvent->regstartdate) {
                    // und ob dieser erreicht ist...
                    $showForm = $objEvent->regstartdate > time() ? true : false;
                }

                if (!$showForm) {
                    // wenn ja, dann entsprechende Meldung ausgeben
                    $values[0]['info'] = $GLOBALS['TL_LANG']['MSC']['regdone'];
                } else {
                    // Formular auf null setzen
                    $objTemplate->regform = null;

                    // Maximale Anzahl noch nicht erreicht. Dann Formluar setzen
                    if (($useMaxi && $values[0]['free'] > 0) || (!$useMaxi && 0 === $values[0]['free'])) {
                        $regform = Form::getForm((int) $objEvent->regform);

                        // Wenn bestätigt werden soll, dann published auf 0, sonst direkt auf 1
                        $published = $objEvent->regconfirm ? 0 : 1;

                        // Einsetzen der aktuell Event ID, damit diese mit dem Formular gespeichert wird.
                        $regform = str_replace('input type="number" name="count" ', 'input type="number" name="count" max="'.$values[0]['free'].'"', $regform);
                        $regform = str_replace('value="eventid"', 'value="'.$objEvent->id.'"', $regform);
                        $regform = str_replace('value="eventtitle"', 'value="'.StringUtil::specialchars($objEvent->title).'"', $regform);
                        $regform = str_replace('value="eventstart"', 'value="'.Date::parse($objPage->datimFormat, $intStartTime).'"', $regform);
                        $regform = str_replace('value="eventend"', 'value="'.Date::parse($objPage->datimFormat, $intEndTime).'"', $regform);
                        $regform = str_replace('value="location_contact"', 'value="'.StringUtil::specialchars($objEvent->location_contact).'"', $regform);
                        $regform = str_replace('value="location_mail"', 'value="'.$objEvent->location_mail.'"', $regform);
                        $regform = str_replace('value="published"', 'value="'.$published.'"', $regform);
                        $objTemplate->regform = $regform;
                    }

                    // Maximale Anzahl erreicht.
                    if ($useMaxi && 0 === $values[0]['free']) {
                        $values[0]['info'] = $GLOBALS['TL_LANG']['MSC']['regmaxi'];
                    }

                    // Info darüber, ob die minimal Anzahl erreicht ist.
                    if ($values[0]['mini'] > 0 && $values[0]['curr'] < $values[0]['mini']) {
                        $values[0]['info'] = $GLOBALS['TL_LANG']['MSC']['regmini'];
                    }
                }

                // Reg Info's für die Ausgabe
                $objTemplate->reginfo = $values[0];

                unset($values);
            }
        }

        // Restore event times...
        $objEvent->startTime = $orgStartTime;
        $objEvent->endTime = $orgEndTime;

        // Clean the RTE output
        if ('' !== $objEvent->teaser) {
            $objTemplate->hasTeaser = true;

            if ('xhtml' === $objPage->outputFormat) {
                $objTemplate->teaser = StringUtil::toXhtml($objEvent->teaser);
            } else {
                $objTemplate->teaser = StringUtil::toHtml5($objEvent->teaser);
            }

            $objTemplate->teaser = StringUtil::encodeEmail($objTemplate->teaser);
        }

        // Display the "read more" button for external/article links
        if ('default' !== $objEvent->source) {
            $objTemplate->details = true;
            $objTemplate->hasDetails = true;
        } // Compile the event text
        else {
            $id = $objEvent->id;

            $objTemplate->details = function () use ($id) {
                $strDetails = '';
                $objElement = ContentModel::findPublishedByPidAndTable($id, 'tl_calendar_events');

                if (null !== $objElement) {
                    while ($objElement->next()) {
                        $strDetails .= $this->getContentElement($objElement->current());
                    }
                }

                return $strDetails;
            };

            $objTemplate->hasDetails = static fn () => ContentModel::countPublishedByPidAndTable($id, 'tl_calendar_events') > 0;
        }

        $objTemplate->addImage = false;

        // Add an image
        if ($objEvent->addImage && '' !== $objEvent->singleSRC) {
            $objModel = FilesModel::findByUuid($objEvent->singleSRC);

            if (null === $objModel) {
                if (!Validator::isUuid($objEvent->singleSRC)) {
                    $objTemplate->text = '<p class="error">'.$GLOBALS['TL_LANG']['ERR']['version2format'].'</p>';
                }
            } elseif (is_file(System::getContainer()->getParameter('kernel.project_dir').'/'.$objModel->path)) {
                // Do not override the field now that we have a model registry (see #6303)
                $arrEvent = $objEvent->row();

                // Override the default image size
                if ('' !== $this->imgSize) {
                    $size = StringUtil::deserialize($this->imgSize);

                    if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2]) || ($size[2][0] ?? null) === '_') {
                        $arrEvent['size'] = $this->imgSize;
                    }
                }

                $arrEvent['singleSRC'] = $objModel->path;
                $this->addImageToTemplate($objTemplate, $arrEvent);
            }
        }

        $objTemplate->enclosure = [];

        // Add enclosures
        if ($objEvent->addEnclosure) {
            $this->addEnclosuresToTemplate($objTemplate, $objEvent->row());
        }

        // schema.org information
        if (method_exists(Events::class, 'getSchemaOrgData')) {
            $objTemplate->getSchemaOrgData = static function () use ($objTemplate, $objEvent): array {
                $jsonLd = Events::getSchemaOrgData($objEvent);

                if ($objTemplate->addImage && $objTemplate->figure) {
                    $jsonLd['image'] = $objTemplate->figure->getSchemaOrgData();
                }

                return $jsonLd;
            };
        }

        $this->Template->event = $objTemplate->parse();

        // Tag the event (see #2137)
        if (System::getContainer()->has('fos_http_cache.http.symfony_response_tagger')) {
            $responseTagger = System::getContainer()->get('fos_http_cache.http.symfony_response_tagger');
            $responseTagger->addTags(['contao.db.tl_calendar_events.'.$objEvent->id]);
        }

        // HOOK: comments extension required
        $bundles = System::getContainer()->getParameter('kernel.bundles');

        // HOOK: comments extension required
        if ($objEvent->noComments || !isset($bundles['ContaoCommentsBundle'])) {
            $this->Template->allowComments = false;

            return;
        }

        /** @var CalendarModel $objCalendar */
        $objCalendar = $objEvent->getRelated('pid');
        $this->Template->allowComments = $objCalendar->allowComments;

        // Comments are not allowed
        if (!$objCalendar->allowComments) {
            return;
        }

        // Adjust the comments headline level
        $intHl = min((int) str_replace('h', '', (string) $this->hl), 5);
        $this->Template->hlc = 'h'.($intHl + 1);

        $this->import(Comments::class, 'Comments'); // @phpstan-ignore-line
        $arrNotifies = [];

        // Notify the system administrator
        if ('notify_author' !== $objCalendar->notify) {
            $arrNotifies[] = $GLOBALS['TL_ADMIN_EMAIL'];
        }

        // Notify the author
        if ('notify_admin' !== $objCalendar->notify) {
            if (($objAuthor = $objEvent->getRelated('author')) !== null && '' !== $objAuthor->email) {
                $arrNotifies[] = $objAuthor->email;
            }
        }

        $objConfig = new \stdClass();

        $objConfig->perPage = $objCalendar->perPage;
        $objConfig->order = $objCalendar->sortOrder;
        $objConfig->template = $this->com_template;
        $objConfig->requireLogin = $objCalendar->requireLogin;
        $objConfig->disableCaptcha = $objCalendar->disableCaptcha;
        $objConfig->bbcode = $objCalendar->bbcode;
        $objConfig->moderate = $objCalendar->moderate;

        $this->Comments->addCommentsToTemplate($this->Template, $objConfig, 'tl_calendar_events', $objEvent->id, $arrNotifies);
    }
}
