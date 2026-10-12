const Encore = require('@terminal42/contao-build-tools');

module.exports = Encore('assets')
    .setOutputPath('public/')
    .setPublicPath('/bundles/cgoitcalendarextended')
    // recurring.svg is maintained by hand and used in the back end
    .cleanupOutputBeforeBuild((options) => {
        options.keep = /(^|\/)recurring\.svg$/;
    })
    .addEntry('fullcalendar-js', './assets/js/fullcalendar.js')
    .addStyleEntry('timetable-css', './assets/scss/timetable.scss')
    .addStyleEntry('yearview-css', './assets/scss/yearview.scss')
    .getWebpackConfig()
;
