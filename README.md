# Calendar Extended Bundle

[![Latest Version on Packagist](http://img.shields.io/packagist/v/cgoit/calendar-extended-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/calendar-extended-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FcgoIT%2Fcalendar-extended-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cgoit/calendar-extended-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/calendar-extended-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/cgoit/calendar-extended-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/calendar-extended-bundle)

## Inhaltsverzeichnis

- [Kurzüberblick](#kurzüberblick)
- [Installation](#installation)
- [Update auf Version 3](#update-auf-version-3)
- [Schnellstart](#schnellstart)
- [Wiederholungen](#wiederholungen)
- [Ausnahmen](#ausnahmen)
- [Wiederholungen werden vorberechnet](#wiederholungen-werden-vorberechnet)
- [Kalender-Optionen](#kalender-optionen)
- [Ferien- und Feiertagskalender](#ferien--und-feiertagskalender)
- [Zusätzliche Felder am Event](#zusätzliche-felder-am-event)
- [Frontend-Module](#frontend-module)
- [Erweiterungen der Core-Module](#erweiterungen-der-core-module)
- [Der Event-Reader und der Parameter `times`](#der-event-reader-und-der-parameter-times)
- [Templates anpassen](#templates-anpassen)
- [Konfiguration](#konfiguration)
- [iCal-Import](#ical-import)
- [Mitwirken](#mitwirken)
- [Lizenz](#lizenz)

## Kurzüberblick

Das **Calendar Extended Bundle** erweitert den Kalender von Contao (`contao/calendar-bundle`) um Funktionen, die der
Core nicht mitbringt:

- **Erweiterte Wiederholungen**: monatlich nach Muster („jeder erste Montag im Monat“), feste Einzeltermine und
  Einschränkung auf bestimmte Wochentage
- **Ausnahmen**: einzelne Termine verschieben oder ausblenden, Termine nach Intervall oder Zeitraum ausschließen
- **Ferien- und Feiertagskalender**: Events anderer Kalender werden an freien Tagen ausgeblendet
- **Farben je Kalender** (Vorder-/Hintergrund mit Deckkraft)
- **Drei zusätzliche Frontend-Module**: Stundenplan (Wochenansicht), Jahresübersicht und FullCalendar
- **Zusätzliche Event-Felder** für Veranstaltungsort und Kontakt

Die Erweiterungen greifen auch in die Core-Module `eventlist`, `calendar` und `eventreader`. Bestehende Seiten
profitieren also ohne Umbau von den neuen Wiederholungen und Ausnahmen.

## Installation

Das Bundle kann über den **Contao Manager** oder per **Composer** installiert werden:

```bash
composer require cgoit/calendar-extended-bundle
```

Anschließend müssen die Datenbankänderungen übernommen werden, entweder über den Contao Manager oder auf der
Kommandozeile:

```bash
bin/console contao:migrate
```

Voraussetzungen:

| Komponente | Version |
|------------|---------|
| PHP | 8.3 oder neuer |
| Contao | 5.3 oder neuer |
| Symfony | 6.4 oder 7.x |

## Update auf Version 3

Version 3 setzt PHP 8.3 und Contao 5.3 voraus. Gegenüber früheren Versionen haben sich einige Dinge geändert:

| Thema | Beschreibung |
|-------|--------------|
| Contao 4 | Wird nicht mehr unterstützt. |
| FullCalendar | Aktualisiert auf Version 7. Es besteht keine Abhängigkeit mehr zu jQuery. |
| Templates | Die Ansichten sind Twig-Templates (`cal_*.html.twig`). Eigene `*.html5`-Templates müssen angepasst werden. |
| Wiederholungen | Die Berechnung hat sich geändert (siehe [Wiederholungen werden vorberechnet](#wiederholungen-werden-vorberechnet)). |

Nach dem Update:

1. `bin/console contao:migrate` ausführen. Die Migrationen benennen Felder um, übernehmen Ferienkalender in das neue
   Format und reparieren defekte Daten der Mehrspalten-Felder.
2. Events mit Wiederholungen oder Ausnahmen im Backend erneut speichern, damit die Vorberechnung nach den neuen Regeln
   erfolgt.
3. Die wichtigsten Kalenderseiten prüfen.

### Hinweis für eigene Templates

Die CSS-Klasse für verschobene Termine (`moved`) steht im Event-Array in `class` (mit führendem Leerzeichen, wie es die
Templates verwenden) statt in der rohen Spalte `cssClass`. Eigene Templates, die `cssClass` ausgewertet haben, müssen
auf `class` umgestellt werden.

## Schnellstart

1. Im Backend unter *Inhalte → Events* einen Kalender anlegen (oder einen bestehenden öffnen) und ein Event erstellen.
2. Im Event unter *Wiederholung* bzw. *Event wiederholen (erweitert)* die gewünschte Wiederholung einstellen und
   speichern.
3. Ein Frontend-Modul anlegen, z. B. **Jahresübersicht**, **Stundenplan** oder **Fullcalendar**, und die Kalender
   auswählen.
4. Das Modul auf einer Seite einbinden.

Die Core-Module *Eventliste* und *Kalender* zeigen die erweiterten Wiederholungen ebenfalls an, ohne dass etwas
umgestellt werden muss.

## Wiederholungen

Ein Event kann auf drei Arten wiederholt werden. Die Core-Wiederholung und die erweiterte Wiederholung schließen
sich gegenseitig aus: Sind beide aktiv, bricht das Speichern mit einer Fehlermeldung ab.

### Core-Wiederholung

Die Wiederholung von Contao (*Event wiederholen*, Intervall und Anzahl der Wiederholungen) bleibt erhalten und wird
um zwei Optionen ergänzt:

| Feld | Beschreibung |
|------|--------------|
| **Wochentag** (`repeatWeekday`) | Nur an bestimmten Wochentagen wiederholen. Steht nur bei der Einheit „Tage“ zur Verfügung. Ohne Auswahl gelten alle Tage. |
| **Nur an Werktagen** (`hideOnWeekend`) | Das Event wird an Wochenenden nicht angezeigt. |

### Erweiterte Wiederholung

Mit **Event wiederholen (erweitert)** (`recurringExt`) werden Events monatlich nach einem Muster wiederholt, etwa

- jeder erste Montag im Monat,
- jeder zweite Donnerstag im Monat,
- jeder letzte Freitag im Monat.

Das Muster wird über das Backend-Widget **Erweitertes Intervall** (`repeatEachExt`) eingegeben. Zusätzlich gibt es
wie beim Core die Anzahl der Wiederholungen (`recurrences`) und das berechnete Ende (`repeatEnd`).

> 💡 **Hinweis**
>
> Bei „jeder fünfte Wochentag“ gibt es in Monaten ohne fünften Wochentag keinen Termin. Die Anzahl der Wiederholungen
> zählt Termine, nicht Monate: „3 Wiederholungen“ ergibt immer drei zusätzliche Termine.

### Feste Termine

Unter **Unregelmäßige Wiederholungen** (`repeatFixedDates`) wird eine Liste einzelner Termine gepflegt. Jeder Eintrag
besteht aus Datum, optional Start- und Endzeit sowie einem Grund. Damit lassen sich Termine abbilden, die sich nicht
über ein Muster beschreiben lassen.

### Monatsende

Bei monatlicher und jährlicher Wiederholung an Tagen, die nicht jeder Monat hat (29., 30., 31. und 29. Februar),
wird der Tag aus dem Startdatum des Events berechnet und in kürzeren Monaten der letzte Tag des Monats verwendet.
Ein Event, das am 31. Januar beginnt und monatlich wiederholt wird, findet am 31. Januar, 28. Februar, 31. März,
30. April usw. statt. (Der Core von Contao verschiebt den Termin dagegen schrittweise: 31. Januar, 3. März, 3. April …)

## Ausnahmen

Mit **Ausnahmen definieren** (`useExceptions`) lassen sich einzelne Termine einer Serie ändern oder ausschließen.
Es gibt drei Arten von Ausnahmen, die kombiniert werden können:

| Feld | Beschreibung |
|------|--------------|
| **Ausnahmen nach Datum** (`repeatExceptions`) | Für einen konkreten Termin: **verschieben** (um ± Tage, mit neuer Uhrzeit, CSS-Klasse und Grund) oder **ausblenden**. |
| **Ausnahmen nach Intervall** (`repeatExceptionsInt`) | Nach Muster, z. B. „jeden ersten <Wochentag des Events> im Monat“ entfällt. |
| **Ausnahmen nach Zeitraum** (`repeatExceptionsPer`) | Alle Termine zwischen zwei Daten entfallen, z. B. während der Sommerferien. |

Verschobene Termine erhalten im Frontend die CSS-Klasse `moved`. Der Grund einer Verschiebung steht im
Template unter `moveReason`, der Grund fester Termine unter `reason`.

Die Auswahlmöglichkeiten (Anzahl der Ausnahmen, Bereich der Verschiebung in Tagen, wählbare Uhrzeiten) lassen sich
über die [Konfiguration](#konfiguration) anpassen.

## Wiederholungen werden vorberechnet

Die Termine einer Serie werden **beim Speichern des Events im Backend** berechnet und in Hilfsspalten abgelegt, nicht
erst beim Anzeigen:

| Spalte | Inhalt |
|--------|--------|
| `allRecurrences` | Alle berechneten Termine (Start und Ende als Zeitstempel und als Text) |
| `exceptionList` | Ausnahmen je Termin |
| `repeatEnd` | Datum der letzten Wiederholung |
| `weekday` | Wochentag des Events |

Das Frontend liest nur diese Spalten. Daraus folgt:

- **Nach einem Update**, das die Berechnung ändert, behalten bestehende Events ihre alten Termine, bis sie im Backend
  geöffnet und erneut gespeichert werden. Die Release-Hinweise nennen solche Änderungen.
- **Events, die nicht über das Backend-Formular entstehen**, haben keine Vorberechnung. Das betrifft z. B. Importe
  oder Events, die per Skript angelegt werden. Beim [iCal-Import](#ical-import) übernimmt das Bundle die Berechnung
  automatisch, bei eigenen Importen muss der Service `Cgoit\CalendarExtendedBundle\Service\RecurrenceCalculator`
  aufgerufen werden.
- Die Anzahl der Wiederholungen ist durch `max_repeat_count` begrenzt (Standard 365, siehe
  [Konfiguration](#konfiguration)). Core-Serien, die darüber hinaus laufen, werden nach dieser Grenze nicht mehr
  fortgesetzt.

## Kalender-Optionen

In den Einstellungen eines Kalenders (*Inhalte → Events → Kalender bearbeiten*) stehen zusätzliche Felder bereit:

| Feld | Beschreibung |
|------|--------------|
| **Eindeutige Events** (`uniqueEvents`) | Beim Speichern wird geprüft, dass sich Events ohne Wiederholung zeitlich nicht überschneiden. |
| **Ferienkalender** (`isHolidayCal`) | Der Kalender enthält Ferien und/oder Feiertage (siehe [unten](#ferien--und-feiertagskalender)). |
| **Vordergrund und Deckkraft** (`fg_color`) | Textfarbe der Events des Kalenders (Hex-Wert und optional Deckkraft in Prozent). |
| **Hintergrund und Deckkraft** (`bg_color`) | Hintergrundfarbe der Events des Kalenders. |

Die Farben stehen den Events in den Templates als `bgstyle` und `fgstyle` (fertige `style`-Fragmente) zur Verfügung.

## Ferien- und Feiertagskalender

Ein Kalender mit aktivierter Option **Ferienkalender** dient dazu, freie Tage zu markieren. An Tagen, an denen ein
Event eines Ferienkalenders stattfindet, werden **Events anderer Kalender ausgeblendet**.

Zwei Schalter steuern Ausnahmen:

| Schalter | Wo | Wirkung |
|----------|----|---------|
| **Event immer anzeigen** (`showOnFreeDay`) | am Event | Das Event wird auch an freien Tagen angezeigt, sofern der Kalender des Events das erlaubt. |
| **Ferien ausblenden** (`hide_holiday`) | im Frontend-Modul | Die Ferien-Events selbst werden nicht angezeigt (nur die Wirkung auf andere Events bleibt). |

## Zusätzliche Felder am Event

| Feld | Beschreibung |
|------|--------------|
| `location_name`, `location_str`, `location_plz`, `location_ort` | Name, Straße, PLZ und Ort der Veranstaltung |
| `location_link` | Link auf den Veranstaltungsort |
| `location_contact`, `location_mail` | Kontaktperson und E-Mail-Adresse |
| `ignoreEndTime` | Die Endzeit wird immer auf die Startzeit gesetzt. |
| `showOnFreeDay`, `hideOnWeekend` | siehe [Ferienkalender](#ferien--und-feiertagskalender) und [Core-Wiederholung](#core-wiederholung) |

## Frontend-Module

Das Bundle bringt drei Frontend-Module der Kategorie *Events* mit. Alle drei laden ihre Events über denselben
Service wie die Core-Module und unterstützen Wiederholungen, Ausnahmen, Ferienkalender und Kalenderfarben.
Das Template der Ansicht (`cal_*`) kann im Modul über **Kalender-Template** (`cal_ctemplate`) gewählt werden.

### Stundenplan (`timetable`)

Wochenansicht mit Zeitraster. Das Modul bietet unter anderem:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Datum anzeigen** | Das Tagesdatum wird angezeigt. |
| **Leere Tage nicht anzeigen** | Wochentage ohne Events werden ausgeblendet. |
| **Navigation anzeigen** | Wochennavigation. |
| **Link „Aktuelles Datum“ anzeigen** | Sprung zur aktuellen Woche. |
| **Uhrzeiten anzeigen** | Uhrzeiten werden angezeigt und Events zur selben Zeit auf gleicher Höhe dargestellt. |
| **Zeitfenster für den Stundenplan** | Zeitfenster (von/bis), das angezeigt wird. |
| **Zellenhöhe eines Events** | Höhe in px pro Stunde. Standard ist 1 px pro Minute, also 60 px je Stunde. |

Die Woche wird über die URL-Parameter `month`, `week` und `day` gewählt.

### Jahresübersicht (`yearview`)

Alle Monate eines Jahres auf einer Seite. Einstellungen:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Horizontale Darstellung** | Die Monate werden nebeneinander statt untereinander dargestellt. |
| **Navigation anzeigen** | Navigation zwischen den Jahren. |
| **Link „Aktuelles Datum“ anzeigen** | Sprung zum aktuellen Jahr. |

Das Jahr wird über den URL-Parameter `year` gewählt.

### Fullcalendar (`fullcalendar`)

Interaktiver Kalender auf Basis von [FullCalendar](https://fullcalendar.io/). Die Events werden per AJAX nachgeladen,
wenn der Besucher den angezeigten Zeitraum wechselt. Einstellungen:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Initiale Ansicht** | Monats-, Wochen-, Tages- oder Listenansicht. |
| **Kalenderwochen** | Kalenderwochen werden angezeigt. |
| **Arbeitszeiten** | Arbeitstage und Arbeitszeit (von/bis) werden hervorgehoben. |
| **Wochenstart** | Erster Wochentag. |
| Option `cal_ignoreDynamic` | Die URL-Parameter `month`, `week` und `day` werden nicht ausgewertet. |

> 💡 **Hinweis**
>
> Die URL zum Nachladen der Events enthält eine Signatur, die das Modul an die Seite bindet. Ohne gültige Signatur
> antwortet die Route mit 404. Die Anfrage ist zudem durch das Request-Token geschützt.

> ℹ️ **Hinweis zu fremden Hooks**
>
> Die eigenen Module laden ihre Events über die Legacy-Methode `Events::getAllEvents()` des Cores (funktioniert in
> allen unterstützten Contao-Versionen). `getAllEvents`-Hooks anderer Erweiterungen greifen deshalb auch hier,
> `findCalendarBoundaries`-Hooks dagegen nicht.

#### Assets

JavaScript und CSS von FullCalendar liegen fertig gebaut im Bundle (`public/`) und werden über Contao-Assets
eingebunden. Auf dem Server wird weder Node noch ein Build benötigt. Die CSS-Klassen von FullCalendar 7 sind gehasht
(`fc-classic-…`). Eigene Styles sollten sich daher nicht auf diese Klassen verlassen, sondern die CSS-Variablen von
FullCalendar nutzen.

## Erweiterungen der Core-Module

Der `getAllEvents`-Hook des Bundles wirkt auf **alle** Kalender-Module, auch auf die Core-Module `eventlist` und
`calendar`. Er

- filtert nach Wochentagen (`repeatWeekday`, `hideOnWeekend`),
- ergänzt erweiterte Wiederholungen und feste Termine,
- setzt Kalenderfarben (`bgstyle`, `fgstyle`),
- wendet Ausnahmen an (verschieben/ausblenden),
- berücksichtigt Ferienkalender (`hide_holiday`),
- reduziert auf die jeweils nächste Wiederholung (`showOnlyNext`),
- ergänzt Filterdaten (`filter_data`),
- hängt die URL-Parameter `?day=…&times=start,end` an die Links der Termine (nicht, wenn im Modul
  die Option `cal_ignoreDynamic` (Datumsparameter ignorieren) gesetzt ist).

In den Modulen **Eventliste**, **Kalender**, **Stundenplan** und **Jahresübersicht** erscheinen dafür zusätzliche
Felder:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Ferien ausblenden** (`hide_holiday`) | Ferien und Feiertage werden nicht angezeigt. |
| **Nur nächste Wiederholung** (`showOnlyNext`) | Von einer Serie wird nur der nächste Termin angezeigt. |
| **Event Filterung** (`filter_fields`) | Felder, auf die im Frontend-Template gefiltert werden kann. |

### Filterfelder

Die gewählten Felder (Standard: `title`, `location_name`, `location_str`, `location_plz`) werden je Event als
JSON im Schlüssel `filter_data` bereitgestellt, z. B. als `data-`Attribut. Ein Template-Skript kann damit Events im
Browser filtern, ohne die Seite neu zu laden. Welche Felder zur Auswahl stehen, legt die
[Konfiguration](#konfiguration) fest.

## Der Event-Reader und der Parameter `times`

Links auf Termine einer Serie enthalten die URL-Parameter `day` und `times`. Der Core-`eventreader` wertet `times`
aus und zeigt damit Datum und Uhrzeit des angeklickten Termins an statt der Daten des ersten Termins der Serie.
`day` ist nur Teil der Link-URL und wird vom Reader nicht ausgewertet. Zusätzlich ersetzt das
Bundle die Template-Funktionen `getUpcomingDates()`, `getPastDates()` und `getSchemaOrgData()`, sodass sie zu den
Wiederholungen und Ausnahmen passen.

Der Parameter `times` wird gegen die Vorberechnung (`allRecurrences`), den Termin selbst und verschobene Termine
geprüft. Verweist ein Link auf einen Termin außerhalb davon (z. B. bei Importen ohne Vorberechnung oder bei
Core-Serien jenseits von `max_repeat_count`), zeigt der Reader den nächsten Termin.

## Templates anpassen

Alle Templates sind Twig-Templates und lassen sich wie üblich in Contao im Projekt überschreiben, indem eine Datei
mit gleichem Namen unter `templates/` abgelegt wird.

### Frontend-Module

| Template | Beschreibung |
|----------|--------------|
| `frontend_module/timetable.html.twig` | Rahmen des Stundenplans |
| `frontend_module/yearview.html.twig` | Rahmen der Jahresübersicht |
| `frontend_module/fullcalendar.html.twig` | Rahmen des FullCalendar |

Die Rahmen binden CSS/JS ein und rendern das im Modul gewählte `cal_*`-Template.

### Ansichten (wählbar im Modul)

| Template | Beschreibung |
|----------|--------------|
| `cal_timetable.html.twig` | Wochenraster des Stundenplans |
| `cal_yearview.html.twig` | Monatsraster der Jahresübersicht |
| `cal_fc_default.html.twig` | Container und Konfiguration (`data-calendar-config`) des FullCalendar |
| `_cal_navigation.html.twig` | Navigation (Wochen/Jahre), von mehreren Ansichten genutzt |

Eigene Varianten werden mit dem Präfix `cal_` benannt (z. B. `cal_timetable_custom.html.twig`) und stehen dann im Feld
**Kalender-Template** des Moduls zur Auswahl.

> 💡 **Hinweis**
>
> Bei Timetable und Yearview stehen Farben und Positionen im `style`-Attribut. Sie werden mit dem Twig-Filter
> `csp_unsafe_inline_style` ausgegeben, damit sie mit einer Content Security Policy funktionieren. Verwende diesen
> Filter auch in eigenen Templates.

### Variablen eines Events

Die folgenden Schlüssel stehen zusätzlich zu den Core-Schlüsseln im Event-Array zur Verfügung. Sie und die
Datenbankfeldnamen sind öffentliche API für Projekt-Templates und ändern sich nur mit einem Hinweis auf einen
Breaking Change.

| Schlüssel | Beschreibung |
|-----------|--------------|
| `bgstyle`, `fgstyle` | `style`-Fragmente für Hintergrund und Vordergrund aus den Kalender-Optionen |
| `bgcolor`, `fgcolor` | Die Kalenderfarben (leer, wenn keine gesetzt sind) |
| `calendar_title` | Titel des Kalenders |
| `class` | CSS-Klassen des Events (u. a. `bygone`, `upcoming`, `current`, `featured`, `moved`); beginnt mit einem Leerzeichen |
| `filter_data` | JSON mit den Werten der Filterfelder |
| `moveReason` | Grund, wenn der Termin verschoben wurde |
| `reason` | Grund eines festen Termins |
| `recurring` | Text zur Wiederholung („Wiederholt sich alle …“) |
| `until` | Text zum Ende der Wiederholung |

### Variablen im Event-Reader

Im `eventreader` stehen zusätzlich die angepassten Funktionen bereit:

| Variable | Beschreibung |
|----------|--------------|
| `getUpcomingDates($limit)` | Die nächsten Termine der Serie, passend zu Wiederholungen und Ausnahmen |
| `getPastDates($limit)` | Vergangene Termine der Serie |
| `getSchemaOrgData()` | Schema.org-Daten des angezeigten Termins |
| `recurring`, `until` | wie oben |

## Konfiguration

Die Obergrenzen und Auswahlmöglichkeiten lassen sich in der `config/config.yaml` des Projekts anpassen. Die
Standardwerte:

```yaml
# Default configuration for extension with alias: "cgoit_calendar_extended"
cgoit_calendar_extended:

    # The maximum number an event is repeated. Default: 365.
    max_repeat_count:     365
    exceptions:

        # The maximum number of repeat exceptions for an event. Default: 250.
        max_count:            250

        # The range of days for the move date option. 14 means from -14 days to 14 days. Default: 7.
        move_days:            7
        move_times:

            # The start time for the move time option. Default: 00:00.
            from:                 '00:00'

            # The end time for the move time option. Default: 23:59.
            to:                   '23:59'

            # The interval in minutes for the move time option. Default: 15.
            interval:             15

    # Define which fields of an event should be available as filter. Default: ['title', 'location_name', 'location_str', 'location_plz'].
    filter_fields:

        # Defaults:
        - title
        - location_name
        - location_str
        - location_plz
```

| Schlüssel | Beschreibung |
|-----------|--------------|
| `max_repeat_count` | Maximale Anzahl der Wiederholungen eines Events |
| `exceptions.max_count` | Maximale Anzahl der Ausnahmen je Event |
| `exceptions.move_days` | Bereich der Verschiebung in Tagen (`14` bedeutet −14 bis +14 Tage) |
| `exceptions.move_times.from` / `to` | Frühester und spätester wählbarer Zeitpunkt beim Verschieben (Format `HH:MM`) |
| `exceptions.move_times.interval` | Abstand der wählbaren Zeiten in Minuten |
| `filter_fields` | Felder des Events, die im Modul als Filter angeboten werden |

Die aktuelle Konfiguration inklusive Kommentaren zeigt:

```bash
bin/console config:dump-reference cgoit_calendar_extended
```

## iCal-Import

Zusammen mit [`cgoit/contao-calendar-ical-bundle`](https://github.com/cgoIT/contao-calendar-ical-bundle) können Events
aus iCal-Quellen (z. B. Google Kalender, Outlook, Ferienkalender) importiert werden. Das ist eine **optionale**
Abhängigkeit und wird nicht automatisch installiert:

```bash
composer require cgoit/contao-calendar-ical-bundle
```

Importierte Events entstehen nicht über das Backend-Formular und hätten damit keine Vorberechnung. Das Bundle
berechnet deshalb nach jedem importierten Event Wiederholungen und Ausnahmen mit demselben Service nach, der auch
beim Speichern im Backend verwendet wird. Importierte Serien erscheinen dadurch korrekt in den Ansichten dieses
Bundles und in den Core-Modulen.

Ein importierter Kalender mit Ferien oder Feiertagen kann als **Ferienkalender** markiert werden (siehe
[Ferien- und Feiertagskalender](#ferien--und-feiertagskalender)).

## Mitwirken

Fehler und Verbesserungsvorschläge bitte als [Issue auf GitHub](https://github.com/cgoIT/calendar-extended-bundle/issues)
melden. Pull Requests sind willkommen. Commit-Nachrichten folgen den
[Conventional Commits](https://www.conventionalcommits.org/de/v1.0.0/), da daraus Changelog und Versionen erzeugt
werden.

Für die Entwicklung:

```bash
composer install
composer run tests      # PHPUnit
composer run coverage   # Testabdeckung (benötigt PCOV), HTML-Report in build/coverage
composer run phpstan    # statische Analyse
composer run all        # ECS, twig-cs-fixer, Rector (ändert Dateien)
```

Die Assets (FullCalendar, SCSS) werden mit `yarn install && yarn build` gebaut. Das Ergebnis in `public/` wird
mit eingecheckt.

## Lizenz

Das Bundle steht unter der [LGPL-3.0-or-later](LICENSE).
