# Changelog

## [3.0.2](https://github.com/cgoIT/calendar-extended-bundle/compare/v3.0.1...v3.0.2) (2026-10-13)


### Bug Fixes

* read the current page from the request to support Contao 5.3 ([2c668d0](https://github.com/cgoIT/calendar-extended-bundle/commit/2c668d0b70528f8704c3adb0c61e90da0cb104ce))
* read the current page from the request to support Contao 5.3 ([e3ce948](https://github.com/cgoIT/calendar-extended-bundle/commit/e3ce94898facbfbfc84cfdcaf9f08124fe7af306))
* satisfy the code style and static analysis checks ([8fd237f](https://github.com/cgoIT/calendar-extended-bundle/commit/8fd237ffd97881ad55749ad7b75a5e5d62f1283f))
* tag the response in the tests with the service of Contao 5.3 as well ([93a83b6](https://github.com/cgoIT/calendar-extended-bundle/commit/93a83b68df730e113bd5d41da469bfebeb470b70))

## [3.0.1](https://github.com/cgoIT/calendar-extended-bundle/compare/v3.0.0...v3.0.1) (2026-10-13)


### Bug Fixes

* collect the events of the frontend modules with Events::getAllEv… ([7aad031](https://github.com/cgoIT/calendar-extended-bundle/commit/7aad03138c5c9367823b1852a4dc369433999ad6))
* collect the events of the frontend modules with Events::getAllEvents ([5868fb9](https://github.com/cgoIT/calendar-extended-bundle/commit/5868fb924a2880749e095e324c51f1bad629d765))

## [3.0.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.9.1...v3.0.0) (2026-10-12)


### ⚠ BREAKING CHANGES

* FullCalendar 7 replaces version 6 (different markup, theme classic), Font Awesome 4.7 is removed and the event JSON delivers the icon as "repeat" or "calendar". Custom templates that use the old files under public/ or the global FullCalendar object have to be adapted.
* the Controller\Module\Module* classes are removed. The module templates are frontend_module/timetable|yearview|fullcalendar, the selected cal_* template must be a twig template. Third-party getAllEvents and findCalendarBoundaries hooks no longer run for these modules. Adds a dependency on symfony/security-bundle.
* cal_fc_default receives a single `config` array instead of the individual variables (url, locale, businessHours, ...); locale and businessHours are no longer JSON strings. Custom .html5 templates of the FullCalendar module must be adapted. The yearview cells provide colspan/rowspan and classes instead of an `attr` string.
* the unused field tl_calendar.allowEvents was removed. The behaviour it described is controlled by the event option showOnFreeDay.
* the unused model classes CalendarEventsModelExt and CalendarLeadsModel were removed.
* Contao 4.13 is no longer supported. The bundle now requires contao/core-bundle and contao/calendar-bundle ^5.3 and Symfony ^6.4 || ^7.0. Contao 4 users have to stay on version 1.

### Features

* drop Contao 4.13 support ([07b0834](https://github.com/cgoIT/calendar-extended-bundle/commit/07b083471e18f000ec6de657d9ff662c099f4571))


### Bug Fixes

* add moved css class to the event class instead of the raw column ([b6eb213](https://github.com/cgoIT/calendar-extended-bundle/commit/b6eb21302b8d2313fb1274c8a907b4b462ffc7a8))
* address the findings of the review of the refactoring ([eca834b](https://github.com/cgoIT/calendar-extended-bundle/commit/eca834b2b3c181720b692b84d668a5da3e93f630))
* append day and times parameters to event urls correctly ([80721ea](https://github.com/cgoIT/calendar-extended-bundle/commit/80721ea3394b33d11e1dc3bb4d588dc9d9d191a2))
* apply interval exceptions to events starting on sunday ([aaf9c80](https://github.com/cgoIT/calendar-extended-bundle/commit/aaf9c8059b6d941ea7f78a447b0d7b7a2fba7cb4))
* calculate moved event times independent of the page date format ([fa1a6de](https://github.com/cgoIT/calendar-extended-bundle/commit/fa1a6deb9cea827aeafef08863f3fb5834f21e1e))
* calculate repeat end relative to the event end instead of now ([e8acb18](https://github.com/cgoIT/calendar-extended-bundle/commit/e8acb184aab3ad47308ad7219ebc19ec33085e55))
* cast calendar ids and skip queries without calendars ([a1f58b9](https://github.com/cgoIT/calendar-extended-bundle/commit/a1f58b9d5069317738cb436a64673a348a2b9679))
* cast recurrences before comparing it in RecurrenceCalculator ([472d6e0](https://github.com/cgoIT/calendar-extended-bundle/commit/472d6e0708e90604f877cba36302bebfdc920645))
* cast the times of events and the time range to int in the timetable ([1c3f8a2](https://github.com/cgoIT/calendar-extended-bundle/commit/1c3f8a25724ee158036ce2d889fe696f3362c000))
* check access and validate range in FetchEventsController ([f2e5f1c](https://github.com/cgoIT/calendar-extended-bundle/commit/f2e5f1c30dd05af259501725d97a12d8f47de9a2))
* check exclusive recurrence types on the merged record ([5aed3f6](https://github.com/cgoIT/calendar-extended-bundle/commit/5aed3f67cbea6ab96c04026c67358dcb920d8f12))
* check inherited page protection in FetchEventsController and cover range checks with tests ([6e4a23d](https://github.com/cgoIT/calendar-extended-bundle/commit/6e4a23db4c3ad233c4274ac7f515f2bebb4a9603))
* compare calendar ids as integers for holiday handling ([5c57432](https://github.com/cgoIT/calendar-extended-bundle/commit/5c57432f9c4b1a4f42e09173bb9659e7d74599ff))
* compare weekday filter of standard recurrence as integers ([f2681b4](https://github.com/cgoIT/calendar-extended-bundle/commit/f2681b4f8bcc23938e02879d3704bd704d4cb2c9))
* detect fixed dates as recurring events in fullcalendar ([0353a35](https://github.com/cgoIT/calendar-extended-bundle/commit/0353a35bd329f4da24e41240bf6ff8445a7a6bcd))
* escape template values as JS literals in fullcalendar template ([1c75c90](https://github.com/cgoIT/calendar-extended-bundle/commit/1c75c90310194c4b46be3a8e7aa9ed8d19addd73))
* fall back to start date for extended recurrence without end date ([99cb6e0](https://github.com/cgoIT/calendar-extended-bundle/commit/99cb6e0d34c27a78cfe2072418c9280ab2f4d2b7))
* guard against null models and missing page in frontend hooks ([5d20e0c](https://github.com/cgoIT/calendar-extended-bundle/commit/5d20e0ccfda1d7fec20d8229aaf98360f178dfea))
* hide events of other calendars on the whole holiday day ([c6e5e0d](https://github.com/cgoIT/calendar-extended-bundle/commit/c6e5e0d6390f5b3547532893cc27353c0832d384))
* include sundays in weekday filter of core recurrence ([bdab3b5](https://github.com/cgoIT/calendar-extended-bundle/commit/bdab3b5dce428b6f0a0b54728e30e1399be5a0d2))
* keep the end day of moved events with new times ([a1d4985](https://github.com/cgoIT/calendar-extended-bundle/commit/a1d4985352962357e7a616b427787aad3d334fa7))
* map locale for fullcalendar and use unique element ids ([b0ba540](https://github.com/cgoIT/calendar-extended-bundle/commit/b0ba540395d1d8601e115e7988ebd74f074c1c20))
* output plain text title and teaser in fullcalendar events ([c14c71c](https://github.com/cgoIT/calendar-extended-bundle/commit/c14c71cc13e9e68814edcd12a4b5a9c288f2dcf3))
* pass plain calendar colors to fullcalendar ([7c23430](https://github.com/cgoIT/calendar-extended-bundle/commit/7c234301cfc70c2e1fbbc3558293fa9091e22116))
* preselect first weekday of the event as default for extended recurrence ([526451d](https://github.com/cgoIT/calendar-extended-bundle/commit/526451d2770779e8027714ce41561ffc8ec4ed6c))
* restrict custom css class of moved events to plain class names ([9bc754b](https://github.com/cgoIT/calendar-extended-bundle/commit/9bc754b36979a6bec9b90657679157a676dd93f7))
* review findings ([a0c2a4b](https://github.com/cgoIT/calendar-extended-bundle/commit/a0c2a4b90807c2d9625c84a94a18acf096ca3c54))
* review findings, Twig/fragment controller migration and German README (3.0.0) ([f643794](https://github.com/cgoIT/calendar-extended-bundle/commit/f64379435fb9f300c972819d6949db2830ca737c))
* sign the url that loads the events of a FullCalendar module ([05af9bd](https://github.com/cgoIT/calendar-extended-bundle/commit/05af9bdb0aa32823030925c3752818b882a1c295))
* skip months without a fifth weekday and clamp monthly recurrences to the last day of the month ([c2ef2ea](https://github.com/cgoIT/calendar-extended-bundle/commit/c2ef2ea3a0260b342d2476b3c3bdc0793bd3435f))
* store all pre-calculated values of an imported event ([ad2d7a6](https://github.com/cgoIT/calendar-extended-bundle/commit/ad2d7a62ac5b6acfe4bc66e39e3d19656e69c0dd))
* throw validation errors instead of redirecting in save callbacks ([d9158cd](https://github.com/cgoIT/calendar-extended-bundle/commit/d9158cd2720aeecee3b0597dab8b41695db96b1a))
* use correct seconds format for end time with ignoreEndTime ([5b6b52e](https://github.com/cgoIT/calendar-extended-bundle/commit/5b6b52eb10ef31eaf17509ce30a6f43c74bef2cd))
* use freshly calculated recurrences for time range exceptions ([b071afb](https://github.com/cgoIT/calendar-extended-bundle/commit/b071afb31a3e2f5a7a605c0c9694c4a9e92d86a8))
* use singular recurrence text and featured class with string values ([17aa73c](https://github.com/cgoIT/calendar-extended-bundle/commit/17aa73c7b5859762256c7b788d91cbbee82adb09))
* use symfony routing attribute instead of removed annotation class ([3ef8d98](https://github.com/cgoIT/calendar-extended-bundle/commit/3ef8d9822f56279b5b66c6c045026a417fc435e2))
* use the week count of the previous year for the previous week link of the timetable ([38268f5](https://github.com/cgoIT/calendar-extended-bundle/commit/38268f519930efe47789ba35f6f03ce58ae7bdeb))
* validate move_times configuration to prevent endless loop ([72b9c88](https://github.com/cgoIT/calendar-extended-bundle/commit/72b9c887f7fc182a5b5d89b5cab43d86f7024bda))
* validate overlapping events before saving ([b8f052d](https://github.com/cgoIT/calendar-extended-bundle/commit/b8f052d986607c2d8a249e82a8dc46cc436c0543))
* validate that move_times start is not later than end ([f106144](https://github.com/cgoIT/calendar-extended-bundle/commit/f1061442f7e49eafd2e6dad519496a3c5b9dbede))
* validate the times parameter of the event reader ([1eedb0c](https://github.com/cgoIT/calendar-extended-bundle/commit/1eedb0c218888019900d47e8c1d19a6ecf9c3711))


### Miscellaneous Chores

* ignore local claude notes ([bfaad3f](https://github.com/cgoIT/calendar-extended-bundle/commit/bfaad3fd4ecda62f7d4d5209553d15210883e7b5))
* remove fullcalendar examples and readme from webroot ([c0070ce](https://github.com/cgoIT/calendar-extended-bundle/commit/c0070cead41804af34929505136514bd3907801e))


### Code Refactoring

* convert frontend modules to fragment controllers ([eda8422](https://github.com/cgoIT/calendar-extended-bundle/commit/eda8422817c4bd0f1df0502d8ce0bf48decafb00))
* migrate frontend templates to twig ([d941aac](https://github.com/cgoIT/calendar-extended-bundle/commit/d941aacbe6df691b859617a2c07847da737cfb32))
* remove unused CalendarEventsModelExt and CalendarLeadsModel ([306492d](https://github.com/cgoIT/calendar-extended-bundle/commit/306492d4a2b9e377db9ae9d680b5d2b4ffb50571))
* remove unused tl_calendar.allowEvents field ([99c40d4](https://github.com/cgoIT/calendar-extended-bundle/commit/99c40d4d3ea412851f441ce129621aff89a467a3))


### Build System

* integrate fullcalendar 7 and icons via encore ([6976d25](https://github.com/cgoIT/calendar-extended-bundle/commit/6976d25d6a912748e65bd862a590872c75c4beab))

## [2.9.1](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.9.0...v2.9.1) (2026-05-18)


### Bug Fixes

* fix initial list view for fullcalendar ([c3221f4](https://github.com/cgoIT/calendar-extended-bundle/commit/c3221f46b8e872000fdf141be56c67c636d48488))
* Fix TypeError when processing exceptions with null repeatEnd ([#55](https://github.com/cgoIT/calendar-extended-bundle/issues/55)) ([4760ded](https://github.com/cgoIT/calendar-extended-bundle/commit/4760dedaec87cc6a2a564a7fa4a958ebdef4ca26))

## [2.9.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.8.0...v2.9.0) (2026-05-15)


### Features

* upgrade fullcalendar to version 6.1.20 ([ef4270b](https://github.com/cgoIT/calendar-extended-bundle/commit/ef4270b6070cfe046ed4c881ef85e2e2c36584f4))


### Bug Fixes

* fix deprecation warnings in year view ([4008e43](https://github.com/cgoIT/calendar-extended-bundle/commit/4008e43cbd499148d105b58f37dc752eddf25535))
* fix display of events spanning multiple days without time in fullcalendar view ([4c63cae](https://github.com/cgoIT/calendar-extended-bundle/commit/4c63cae48b788d08e9fb94e6829518122ceed041))
* fix display of hours in time table view ([9bbc933](https://github.com/cgoIT/calendar-extended-bundle/commit/9bbc933cbf54b51537b6eb0a22be50304770d47b))

## [2.8.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.7.1...v2.8.0) (2026-04-14)


### Features

* use AfterImportItemListener to calculate recurrence array after importing events via contao-calendar-ical-bundle ([980d05f](https://github.com/cgoIT/calendar-extended-bundle/commit/980d05f403f9c7d9c9b63efbe191a4ead8c1bc18))


### Bug Fixes

* always load listener.yml as otherwise symfony is complaining ([0362f8d](https://github.com/cgoIT/calendar-extended-bundle/commit/0362f8dcbf34608f9720b3fbbb9028f59abea10c))

## [2.7.1](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.7.0...v2.7.1) (2026-04-07)


### Bug Fixes

* correct end date/time for recurring events spanning multiple days ([8e9aa75](https://github.com/cgoIT/calendar-extended-bundle/commit/8e9aa75a0468b522f200139d2f7a3ab3dae5b94e))
* correct handling for `hideRunning` and `showOnlyNextRecurrence` ([374d75c](https://github.com/cgoIT/calendar-extended-bundle/commit/374d75c82cb144851e32c23d25b112341e6b8c13))
* minimum fix for cal_timetable template ([bc8803a](https://github.com/cgoIT/calendar-extended-bundle/commit/bc8803a8bd7ca0893bf5e625ca911d14d19cac5c))


### Miscellaneous Chores

* fix lint errors ([1300c33](https://github.com/cgoIT/calendar-extended-bundle/commit/1300c33b34888fc1b5de8628d3e2d2c0873093c3))
* integrate twig-cs-fixer ([c9c687c](https://github.com/cgoIT/calendar-extended-bundle/commit/c9c687c85918a3d11fe52c4b4e6de3ffb3d8241c))
* remove commented code ([e08e9cc](https://github.com/cgoIT/calendar-extended-bundle/commit/e08e9cc0a02e004a8f0d70e33cd7106cd8b67da7))

## [2.7.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.6.0...v2.7.0) (2026-03-27)


### Features

* add reason for fixed date recurring events ([88c2e1c](https://github.com/cgoIT/calendar-extended-bundle/commit/88c2e1cec72ab13a16a87105ec651d185e580467))


### Bug Fixes

* add location_plz to palette again ([c736de8](https://github.com/cgoIT/calendar-extended-bundle/commit/c736de87bd691c6767d8a2ecc21b6ce888fae4d4))

## [2.6.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.5.6...v2.6.0) (2026-02-09)


### Features

* add filter_data to events in frontend template ([b7634e6](https://github.com/cgoIT/calendar-extended-bundle/commit/b7634e63501676437d0539a97ad02e5447620290))


### Miscellaneous Chores

* update file header ([3896abb](https://github.com/cgoIT/calendar-extended-bundle/commit/3896abb367991bbae0b44cd888c4bae3810a29ab))
* update file header ([f6cce7a](https://github.com/cgoIT/calendar-extended-bundle/commit/f6cce7a2b0697e1a818c1f6e8ec60cf5245e0bc7))

## [2.5.6](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.5.5...v2.5.6) (2026-02-01)


### Bug Fixes

* fix small bug in RenameFieldsMigration.php ([ff098c2](https://github.com/cgoIT/calendar-extended-bundle/commit/ff098c289ef09b6b81374c96f9437fc7b916e41b)), closes [#42](https://github.com/cgoIT/calendar-extended-bundle/issues/42)

## [2.5.5](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.5.4...v2.5.5) (2026-01-18)


### Bug Fixes

* make constructor final public in ParseFrontendTemplateHook.php ([245f2ea](https://github.com/cgoIT/calendar-extended-bundle/commit/245f2ea42fefc9b1214f837d5c019b7568472336))

## [2.5.4](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.5.3...v2.5.4) (2026-01-05)


### Bug Fixes

* do not add wrong date/time in repeatFixedDates on event creation ([c2b4c62](https://github.com/cgoIT/calendar-extended-bundle/commit/c2b4c62aec4471507031120660b449467cab0d67))

## [2.5.3](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.5.2...v2.5.3) (2026-01-02)


### Bug Fixes

* create empty inputs for fixed dates repeat ([8b26500](https://github.com/cgoIT/calendar-extended-bundle/commit/8b265007d71923dcb95da8858fea45c153a381c4))


### Miscellaneous Chores

* remove all script alias ([c450a0a](https://github.com/cgoIT/calendar-extended-bundle/commit/c450a0ac308cea4a36962b22b921c348b6d17a92))
* run build-tools with php 8.3 ([a3aeb9b](https://github.com/cgoIT/calendar-extended-bundle/commit/a3aeb9b69f859fefdb72638eb48bde9632582d4c))
* run composer tools ([1cfb94c](https://github.com/cgoIT/calendar-extended-bundle/commit/1cfb94c86d7b628410161cff337f4efadb4d95b6))

## [2.5.2](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.5.1...v2.5.2) (2025-09-26)


### Bug Fixes

* fix loading of custom routes ([1ee50d6](https://github.com/cgoIT/calendar-extended-bundle/commit/1ee50d69edf1c2197f1862e89e040379ac6eb190))

## [2.5.1](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.5.0...v2.5.1) (2025-08-19)


### Miscellaneous Chores

* update version contraints ([6ca8517](https://github.com/cgoIT/calendar-extended-bundle/commit/6ca85171b432fc4f857357efe05035ddf4abab87))

## [2.5.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.12...v2.5.0) (2025-07-21)


### Features

* add icon to series events in calendar events list view in backend ([cf78042](https://github.com/cgoIT/calendar-extended-bundle/commit/cf7804242268beecc7a5f46e0646d65ed61d09b4))

## [2.4.12](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.11...v2.4.12) (2025-03-13)


### Miscellaneous Chores

* fix build-tools errors ([758dd4f](https://github.com/cgoIT/calendar-extended-bundle/commit/758dd4fded3725aaef551383e3afd492d74da898))

## [2.4.11](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.10...v2.4.11) (2025-02-11)


### Miscellaneous Chores

* fix copyright ([b0e30b6](https://github.com/cgoIT/calendar-extended-bundle/commit/b0e30b64a838f277a7119b45764f39510285f2ae))

## [2.4.10](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.9...v2.4.10) (2024-12-03)


### Bug Fixes

* use Doctrine Schema Representation for db columns ([93b3faa](https://github.com/cgoIT/calendar-extended-bundle/commit/93b3faa3b443f1c7f67a231b3ada6e0999e39522))

## [2.4.9](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.8...v2.4.9) (2024-11-29)


### Bug Fixes

* correctly handle recurrences on extended recurrences ([919fbc1](https://github.com/cgoIT/calendar-extended-bundle/commit/919fbc1b834095b66f0c7ad4cefd1e2b335ce0c6))
* ksort arrEvents to have all days in ascending order ([6096dd9](https://github.com/cgoIT/calendar-extended-bundle/commit/6096dd961c323b2e54a282e771ebcabaacc7d12f))


### Miscellaneous Chores

* add missing dependencies to composer.json ([7682619](https://github.com/cgoIT/calendar-extended-bundle/commit/768261911285abea341bdaa6a3ce018047c5db4c))

## [2.4.8](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.7...v2.4.8) (2024-10-18)


### Bug Fixes

* handle URL generation based on Contao version ([7cc75bb](https://github.com/cgoIT/calendar-extended-bundle/commit/7cc75bb38329021eac9eeb14090307edf85e99b5))


### Miscellaneous Chores

* fix ecs errors ([a3155f0](https://github.com/cgoIT/calendar-extended-bundle/commit/a3155f0ec7808ea1ff29015ed32c1b4b0977e92b))

## [2.4.7](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.6...v2.4.7) (2024-10-12)


### Bug Fixes

* refactor code for clarity and add condition for weekday ([c0c0527](https://github.com/cgoIT/calendar-extended-bundle/commit/c0c05274b062c179cc38c5fe48df71d06ef1355e))


### Miscellaneous Chores

* remove unused import in CalendarEventsCallbacks.php ([3593c5d](https://github.com/cgoIT/calendar-extended-bundle/commit/3593c5d17d0db15fbbed8b173301ba81865dc35a))

## [2.4.6](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.5...v2.4.6) (2024-09-04)


### Bug Fixes

* set correct start date and url in schema.org data ([1a7b3aa](https://github.com/cgoIT/calendar-extended-bundle/commit/1a7b3aa309f1f3e3ffac96cd35d458c0c20352e3))


### Miscellaneous Chores

* fix ecs bugs ([1dddd47](https://github.com/cgoIT/calendar-extended-bundle/commit/1dddd4713d75013ec91d3ed91a827fb358589a2a))

## [2.4.5](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.4...v2.4.5) (2024-07-25)


### Bug Fixes

* add event title as title attribute for links in fullcalendar view ([cccf06f](https://github.com/cgoIT/calendar-extended-bundle/commit/cccf06f67a773f35497ad79dbd323f94d6a8bbe2)), closes [#15](https://github.com/cgoIT/calendar-extended-bundle/issues/15)

## [2.4.4](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.3...v2.4.4) (2024-07-18)


### Bug Fixes

* event weekdays for recurrences should only be checked for standard recurrences ([f57ca17](https://github.com/cgoIT/calendar-extended-bundle/commit/f57ca17e5ad707bbdd0b2abef9ea06b291563868)), closes [#15](https://github.com/cgoIT/calendar-extended-bundle/issues/15)

## [2.4.3](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.2...v2.4.3) (2024-07-15)


### Bug Fixes

* fix error "date(): Argument [#2](https://github.com/cgoIT/calendar-extended-bundle/issues/2) ($timestamp) must be of type ?int, string given" ([4a69df7](https://github.com/cgoIT/calendar-extended-bundle/commit/4a69df7c0340508e28c07303accede9f3320b584)), closes [#19](https://github.com/cgoIT/calendar-extended-bundle/issues/19)

## [2.4.2](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.1...v2.4.2) (2024-07-12)


### Bug Fixes

* fix the handling of repeated event regarding begin, recurring and until. Needs a resave for all the events which uses "fixed dates repeats" ([2ab653d](https://github.com/cgoIT/calendar-extended-bundle/commit/2ab653dc0c76d09aca63e9a4782d018e18023fbc)), closes [#17](https://github.com/cgoIT/calendar-extended-bundle/issues/17)

## [2.4.1](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.4.0...v2.4.1) (2024-07-11)


### Bug Fixes

* the start time and end time of the "repeat exceptions" are displayed correctly in the frontend ([99574aa](https://github.com/cgoIT/calendar-extended-bundle/commit/99574aa6b5e616cdbf8f876c5a138ba32a8e9d02)), closes [#15](https://github.com/cgoIT/calendar-extended-bundle/issues/15)

## [2.4.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.3.0...v2.4.0) (2024-06-25)


### Features

* default time list view in fullcalendar is now "month". ([00e7097](https://github.com/cgoIT/calendar-extended-bundle/commit/00e70971bda6d91bb4a99d60d3b699905b3b9adf))

## [2.3.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.2.1...v2.3.0) (2024-06-24)


### Features

* add calendar id to event link in fullcalendar ([c2d1af1](https://github.com/cgoIT/calendar-extended-bundle/commit/c2d1af10e871fd5a2a809b51cf172e1519897e55))


### Bug Fixes

* ensure that $objPage is never null on ajax requests ([a3d7e9c](https://github.com/cgoIT/calendar-extended-bundle/commit/a3d7e9c180dc0a861c1e343ff4e5f0d2f57dce91))

## [2.2.1](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.2.0...v2.2.1) (2024-06-07)


### Bug Fixes

* update calendar event model and remove dependency ([0b532e1](https://github.com/cgoIT/calendar-extended-bundle/commit/0b532e1cda70b10b717d7d025f5e8ff0c09adc47))

## [2.2.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.1.0...v2.2.0) (2024-05-22)


### Features

* change some cols to 'text NULL' to prevent row size too large error ([5679e14](https://github.com/cgoIT/calendar-extended-bundle/commit/5679e144d56454957595f6aea64eddbb0e5f6dc8))


### Bug Fixes

* fix loading of event details in ParseFrontendTemplateHook ([c2f7f23](https://github.com/cgoIT/calendar-extended-bundle/commit/c2f7f23ccb4f9d04cb60eec79eba24977949e90b))
* fix processing of fixed dates repeats ([416563e](https://github.com/cgoIT/calendar-extended-bundle/commit/416563ef3316a60d41a1e31985a86b138e555ca5))

## [2.1.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.0.3...v2.1.0) (2024-05-15)


### Features

* reimplement logic for day and times url parameter and display of event times in mod_eventreader ([a192b4a](https://github.com/cgoIT/calendar-extended-bundle/commit/a192b4a017a102adb9f471a1628e09141b507a37))


### Bug Fixes

* add day and times url parameters for repeated events ([53f3f70](https://github.com/cgoIT/calendar-extended-bundle/commit/53f3f7045c38e674e1c0fbb549d185c67e442be6))
* add until and recurring to event template in ModuleEventReader ([fe46742](https://github.com/cgoIT/calendar-extended-bundle/commit/fe4674202256df436ae0d6d93500e9db6c72f7a4))
* always show correct next date if no url parameters (day, times) are given ([7e26906](https://github.com/cgoIT/calendar-extended-bundle/commit/7e269060ca34804ca412df64cc91df64f1c84f40))
* call parent constructor in ParseFrontendTemplateHook ([9b156e6](https://github.com/cgoIT/calendar-extended-bundle/commit/9b156e69bd6acd69d0faca35390bcbacbb2fb06b))

## [2.0.3](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.0.2...v2.0.3) (2024-05-08)


### Bug Fixes

* add recurring events at the correct indices in the $arrEvents array ([fba1d2f](https://github.com/cgoIT/calendar-extended-bundle/commit/fba1d2f99a3c732f176e887182652f58aed77463))
* fix ecs findings ([d584a73](https://github.com/cgoIT/calendar-extended-bundle/commit/d584a73be17ad65a8a2f6451befe88c067a3eb40))

## [2.0.2](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.0.1...v2.0.2) (2024-05-05)


### Bug Fixes

* fix error adding new entries to exceptions via multi column wizard ([3c9a57c](https://github.com/cgoIT/calendar-extended-bundle/commit/3c9a57ca10ecfaa6cd931197ebc9600731ba88a4))

## [2.0.1](https://github.com/cgoIT/calendar-extended-bundle/compare/v2.0.0...v2.0.1) (2024-05-02)


### Bug Fixes

* fix warning foreach() argument must be of type array|object, null given ([db5bc69](https://github.com/cgoIT/calendar-extended-bundle/commit/db5bc69d0373c3d2fb519123aca8d7720a9eb107))

## [2.0.0](https://github.com/cgoIT/calendar-extended-bundle/compare/v1.1.3...v2.0.0) (2024-05-02)


### ⚠ BREAKING CHANGES

* change namespace

### Features

* fix all phpstan errors ([6715e54](https://github.com/cgoIT/calendar-extended-bundle/commit/6715e54c0bb956a43e462c7955e08e78ea637526))
* implement custom route for full calendar fetchEvents ([5ffeba8](https://github.com/cgoIT/calendar-extended-bundle/commit/5ffeba804530322ddc35b1e8f1ab208413531cb6))
* minor adjustments for contao 5 ([af00001](https://github.com/cgoIT/calendar-extended-bundle/commit/af000013426cb908534e687f8be0228ffbed6464))
* remove dependency to notification_center and leads ([861c451](https://github.com/cgoIT/calendar-extended-bundle/commit/861c451e381d401a305e0be3959183f3597317c8))


### Bug Fixes

* check correctly for fixed date reoccurences ([dcb0f28](https://github.com/cgoIT/calendar-extended-bundle/commit/dcb0f28f62921a6405ab16c4521f6b3ad1d9c8ef))
* Encode text in form values ([0caf02a](https://github.com/cgoIT/calendar-extended-bundle/commit/0caf02aebdbfd1f381328c9e0922a0b62e57a641))
* fix ecs findings ([71c370f](https://github.com/cgoIT/calendar-extended-bundle/commit/71c370fab2e175ae8ac70ba83d34eb287f20e47f))
* fix qs findings ([60759d9](https://github.com/cgoIT/calendar-extended-bundle/commit/60759d941d0d44867e20583b85075cbf29fb1884))
* fix some issues, refactor year view, add missing files ([997cbaa](https://github.com/cgoIT/calendar-extended-bundle/commit/997cbaade2811af67e6f8a9000c27b62307118fd))
* fix typo in german translation ([22a05e6](https://github.com/cgoIT/calendar-extended-bundle/commit/22a05e6eeea9bd75de97943e82dff42a00ec6f9c))
* suppress display of dates on recurring events in frontend ([b39fba4](https://github.com/cgoIT/calendar-extended-bundle/commit/b39fba49dc68e286d38455caef45c9f27af79b9a))


### Miscellaneous Chores

* add ecs and phpstan and fix issues ([ea038fb](https://github.com/cgoIT/calendar-extended-bundle/commit/ea038fb39dca4da98286e097325703bb34452584))
* add github actions ([bd3156b](https://github.com/cgoIT/calendar-extended-bundle/commit/bd3156b8aa6f6cb413f12a5540e0ad89d05ca759))
* add shadow dependencies ([aef2c7e](https://github.com/cgoIT/calendar-extended-bundle/commit/aef2c7e60b1435dc480bac235d08d19976bd1ae0))
* clear changelog ([bb7770c](https://github.com/cgoIT/calendar-extended-bundle/commit/bb7770cb4f4bcb74cb15fe6fe86e4eb2c9962db3))
* fix github actions stuff ([80a2301](https://github.com/cgoIT/calendar-extended-bundle/commit/80a2301546c347e0006722621c468f68e3c6fcd8))
* fix inline doc ([f273ee0](https://github.com/cgoIT/calendar-extended-bundle/commit/f273ee02d68ccdb84cd8aec7064c520a7b762827))
* fix phpstan errors ([d96b138](https://github.com/cgoIT/calendar-extended-bundle/commit/d96b13828aa5cf9dfb6d576b408044163d5c62b1))
* fix rector errors ([fbba769](https://github.com/cgoIT/calendar-extended-bundle/commit/fbba769ed49bd3e83e1e66fe1da2227563e819ab))
* make modules work again ([9d0834d](https://github.com/cgoIT/calendar-extended-bundle/commit/9d0834db742d80ebf870532d01b0ba3ce33cae1f))
* remove commented code ([8f9df1b](https://github.com/cgoIT/calendar-extended-bundle/commit/8f9df1be98ce1ba21e6b90198b85dfc1349dacf6))
* update dependencies ([5dc2744](https://github.com/cgoIT/calendar-extended-bundle/commit/5dc2744a1955ba7172b2902a24936a501032337a))


### Documentation

* fix README.md ([0ccb2fa](https://github.com/cgoIT/calendar-extended-bundle/commit/0ccb2fa625e53e82de38c7b661d564d2d9990702))
