# Changelog

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
