(function () {
    'use strict';

    function addIcon(timeElement, iconClass) {
        const icon = document.createElement('i');
        icon.classList.add('fa', iconClass, 'fc-event-icon');
        timeElement.insertBefore(icon, timeElement.firstChild);
    }

    function init(el) {
        const config = JSON.parse(el.dataset.calendarConfig || '{}');

        const options = {
            locale: config.locale,
            initialDate: config.initialDate,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,dayGridDay,listMonth',
            },
            navLinks: true,
            editable: false,
            dayMaxEvents: true,
            timeZone: 'local',
            initialView: config.initialView,
            weekNumbers: !!config.weekNumbers,
            weekNumberCalculation: 'ISO',
            firstDay: config.firstDay,
            eventSources: [{
                url: config.url,
                method: 'POST',
                extraParams: function () {
                    return { REQUEST_TOKEN: config.requestToken };
                },
                failure: function () {
                    alert(config.fetchError);
                },
            }],
            eventDidMount: function (info) {
                if (info.event.title) {
                    info.el.setAttribute('title', info.event.title);
                }

                if (info.event.extendedProps.calendarId) {
                    info.el.classList.add('cal-' + info.event.extendedProps.calendarId);
                }

                const timeElement = info.el.querySelector('.fc-event-time');
                if (!timeElement) {
                    return;
                }
                if (info.event.extendedProps.editable) {
                    addIcon(timeElement, 'fa-pencil-square-o');
                }
                if (info.event.extendedProps.icon) {
                    addIcon(timeElement, info.event.extendedProps.icon);
                }
            },
        };

        if (config.businessHours) {
            options.businessHours = config.businessHours;
        }

        new FullCalendar.Calendar(el, options).render();
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = function () {
                reject(new Error('Could not load ' + src));
            };
            document.head.appendChild(script);
        });
    }

    // FullCalendar reads the CSP nonce from the meta tag in the module template only once while it is loaded,
    // so it must not be loaded before the page body exists.
    let libraryPromise = null;

    function loadLibrary(scripts) {
        if ('undefined' !== typeof FullCalendar) {
            return Promise.resolve();
        }

        if (null === libraryPromise) {
            libraryPromise = scripts.reduce(function (chain, src) {
                return chain.then(function () {
                    return loadScript(src);
                });
            }, Promise.resolve());
        }

        return libraryPromise;
    }

    function initAll() {
        document.querySelectorAll('[data-calendar-config]').forEach(function (el) {
            loadLibrary(JSON.parse(el.dataset.calendarScripts || '[]')).then(function () {
                init(el);
            });
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
