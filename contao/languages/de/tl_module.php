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

/*
 * Fields
 */
$GLOBALS['TL_LANG']['tl_module']['use_horizontal'] = ['Horizontale Darstellung', 'Monate werden horizontal dargestellt.'];
$GLOBALS['TL_LANG']['tl_module']['use_navigation'] = ['Navigation anzeigen', 'Wochennavigation wird angezeigt, wenn aktiviert.'];
$GLOBALS['TL_LANG']['tl_module']['showDate'] = ['Datum anzeigen', 'Tagesdatum wird angezeigt, wenn aktiviert.'];
$GLOBALS['TL_LANG']['tl_module']['showOnlyNext'] = ['Nur nächste Wiederholung', 'Es wird nur die nächste Wiederholung angezeigt (Nur bei Wiederholungen).'];
$GLOBALS['TL_LANG']['tl_module']['linkCurrent'] = ['Link "Aktuelles Datum" anzeigen', 'Link für das aktuelle Datum wird angezeigt, wenn aktiviert.'];
$GLOBALS['TL_LANG']['tl_module']['hideEmptyDays'] = ['Leere Tage nicht anzeigen', 'Wochentage ohne Events werden ausgeblendet.'];
$GLOBALS['TL_LANG']['tl_module']['hide_holiday'] = ['Ferien ausblenden', 'Ferien und Feiertage nicht anzeigen.'];
$GLOBALS['TL_LANG']['tl_module']['cal_times'] = ['Uhrzeiten anzeigen', 'Uhrzeiten werden rechts angezeigt, und Events gleicher Zeit auf gleicher Höhe angezeigt.'];
$GLOBALS['TL_LANG']['tl_module']['businessHours'] = ['Arbeitszeiten', 'Arbeitszeiten hervorheben.'];
$GLOBALS['TL_LANG']['tl_module']['businessDays'] = ['Arbeitstage', 'Tage, an denen die Geschäftszeiten gelten.'];
$GLOBALS['TL_LANG']['tl_module']['businessDayStart'] = ['Arbeitszeit von', 'Beginn der Arbeitszeiten.'];
$GLOBALS['TL_LANG']['tl_module']['businessDayEnd'] = ['Arbeitszeit bis', 'Ende der Arbeitszeiten.'];
$GLOBALS['TL_LANG']['tl_module']['weekNumbers'] = ['Kalenderwochen', 'Kaldenderwochen werden im Kalendaer angezeigt.'];

$GLOBALS['TL_LANG']['tl_module']['cal_times_range'] = ['Zeitfenster für den Stundenplan.', 'Zeigt die Zeiten links als Label im Stundeninterval an.'];
$GLOBALS['TL_LANG']['tl_module']['time_range_from'] = ['Zeit von', 'Startzeit für den Stundenplan.'];
$GLOBALS['TL_LANG']['tl_module']['time_range_to'] = ['Zeit bis', 'Endzeit für den Stundenplan.'];

$GLOBALS['TL_LANG']['tl_module']['cellheight'] = ['Zellenhöhe eines Events', 'Höhe der Zelle eines Events in px pro Stunde. Standard ist 1px pro Minute und damit 60px bei einem Interval von 1 Stunde.'];

$GLOBALS['TL_LANG']['tl_module']['filter_fields'] = ['Event Filterung', 'Felder auswählen, auf die im Frontend Template gefiltert werden kann.'];

$GLOBALS['TL_LANG']['tl_module']['filter_legend'] = 'Filter';

$GLOBALS['TL_LANG']['tl_module']['cal_fcFormat'] = ['Initiale Ansicht', 'Wählen Sie die initiale Ansicht für Ihren Kalender.'];
$GLOBALS['TL_LANG']['tl_module']['cal_fc_month'] = 'Monatsübersicht';
$GLOBALS['TL_LANG']['tl_module']['cal_fc_week'] = 'Wochenübersicht';
$GLOBALS['TL_LANG']['tl_module']['cal_fc_day'] = 'Tagesübersicht';
$GLOBALS['TL_LANG']['tl_module']['cal_fc_list'] = 'Listenansicht';

$GLOBALS['TL_LANG']['tl_module']['confirm_drop'] = 'Möchten Sie das Event wirklich verschieben?';
$GLOBALS['TL_LANG']['tl_module']['confirm_resize'] = 'Möchten Sie das Event wirklich ändern?';
$GLOBALS['TL_LANG']['tl_module']['fetch_error'] = 'Beim Laden der Daten ist ein Fehler aufgetreten!';
