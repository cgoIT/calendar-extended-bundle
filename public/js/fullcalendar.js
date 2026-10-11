(function () {
    'use strict';

    function addIcon(timeElement, iconClass) {
        const icon = document.createElement('i');
        icon.classList.add('fa', iconClass, 'fc-event-icon');
        timeElement.insertBefore(icon, timeElement.firstChild);
    }

    function init(el) {
        const config = JSON.parse(el.dataset.fullcalendar || '{}');

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

    function initAll() {
        document.querySelectorAll('[data-fullcalendar]').forEach(init);
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
