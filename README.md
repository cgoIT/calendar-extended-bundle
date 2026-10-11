[![Latest Version on Packagist](http://img.shields.io/packagist/v/cgoit/calendar-extended-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/calendar-extended-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FcgoIT%2Fcalendar-extended-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cgoit/calendar-extended-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/calendar-extended-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/cgoit/calendar-extended-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/calendar-extended-bundle)

Contao 5 Calendar Extension
===============================

This bundle adds more calendar functionality to Contao.
- better repeaters
- event exceptions
- vacations


Installation
------------

Run the following command in your project directory:

```bash
composer require cgoit/calendar-extended-bundle
```


IMPORTANT NOTICE
----------------

Starting with version 2 of this bundle some features are not supported any more.

| Feature                          | Description                                                                                                                                            |
|----------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------|
| Event Registration               | This bundle does *NOT* support event registrations any more. You can use other bundles like `inspiredminds/contao-event-registration` for this purpose |
| Frontend editing in FullCalendar | This feature is *NOT* supported any more                                                                                                               |
| FullCalendar Upgrade             | FullCalendar was upgraded to version 6.1. So no more dependency to JQuery exists.                                                                      |


Upgrade from version 1.x
------------------------

If you want to upgrade from version 1 run the `contao:migrate` script. In the first step do all the database updates *without* any deletes. This ensures that all migrations can run after the first round of migration. If you are really sure that version 2 is working for you, you can run all the deletes via the `contao:migrate` script.

Contao 5 support
----------------

Starting with version 2 of this bundle only Contao 5.3 and newer is supported. Many things have been refactored in this version, many classes have been split or moved and the complete approach of handling things has changed. Therefore, the chance that something does not yet work 100% is quite high. Contao 4 is no longer supported by version 2; Contao 4 users have to stay on version 1.


Recurrences are pre-calculated
------------------------------

The dates of a recurring event are calculated when the event is saved in the back end, not when it is shown. After an update of this bundle that changes how dates are calculated, existing events keep their old dates until you open and save them again.

Two things changed in the calculation (events have to be saved again to get the new dates):

- **Monthly and yearly repetition on a day that not every month has** (29th, 30th, 31st, 29 February): the day is now calculated from the start date of the event and the last day of a shorter month is used. An event starting on 31 January and repeating every month now takes place on 31 January, 28 February, 31 March, 30 April, ... Before, the date drifted: 31 January, 3 March, 3 April, 3 May, ... (the core calendar of Contao still behaves like that).
- **Extended repetition with "fifth" weekday** (e.g. "every fifth Monday of the month"): months without a fifth Monday have no date. Before, the first weekday of the following month was used instead. The number of repetitions now counts dates, not months, so "3 repetitions" always results in three additional dates.


Configuration
-------------

As usual you can configure some stuff via config.yml. The default configuration is as follows:

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

        # Examples:
        # - title
        # - location_name
        # - location_str
        # - location_plz
```
