import { config as fontAwesomeConfig, icon } from '@fortawesome/fontawesome-svg-core';
import { faCalendar } from '@fortawesome/free-regular-svg-icons';
import { faRepeat } from '@fortawesome/free-solid-svg-icons';
import { Calendar } from 'fullcalendar';
import dayGridPlugin from 'fullcalendar/daygrid';
import listPlugin from 'fullcalendar/list';
import locales from 'fullcalendar/locales-all';
import classicThemePlugin from 'fullcalendar/themes/classic';
import timeGridPlugin from 'fullcalendar/timegrid';

import 'fullcalendar/skeleton.css';
import 'fullcalendar/themes/classic/theme.css';
import 'fullcalendar/themes/classic/palette.css';
import '@fortawesome/fontawesome-svg-core/styles.css';
import '../scss/fullcalendar.scss';

// The styles are part of the stylesheet, Font Awesome must not add a style element (Content Security Policy)
fontAwesomeConfig.autoAddCss = false;

// The names of the icons are delivered with the events (see FullCalendarEventMapper)
const icons = {
    repeat: faRepeat,
    calendar: faCalendar,
};

function createIcon(name) {
    if (!icons[name]) {
        return null;
    }

    const wrapper = document.createElement('span');
    wrapper.classList.add('fc-event-icon');
    wrapper.appendChild(icon(icons[name]).node[0]);

    return wrapper;
}

function init(el) {
    const config = JSON.parse(el.dataset.calendarConfig || '{}');

    const options = {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, classicThemePlugin],
        locales,
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
        eventSources: [
            {
                url: config.url,
                method: 'POST',
                extraParams() {
                    return { REQUEST_TOKEN: config.requestToken };
                },
                failure() {
                    // eslint-disable-next-line no-alert
                    alert(config.fetchError);
                },
            },
        ],
        eventDidMount(info) {
            if (info.event.title) {
                info.el.setAttribute('title', info.event.title);
            }

            if (info.event.extendedProps.calendarId) {
                info.el.classList.add(`cal-${info.event.extendedProps.calendarId}`);
            }

            // The markup of FullCalendar has no stable class names: the icon is put in front of the first text
            // of the event, which is the time or, for events without time, the title
            const iconElement = createIcon(info.event.extendedProps.icon);
            const textElement = [...info.el.querySelectorAll('*')].find(
                (child) => child.children.length === 0 && child.textContent.trim() !== '',
            );

            if (iconElement && textElement) {
                textElement.parentNode.insertBefore(iconElement, textElement);
            }
        },
    };

    if (config.businessHours) {
        options.businessHours = config.businessHours;
    }

    new Calendar(el, options).render();
}

function initAll() {
    document.querySelectorAll('[data-calendar-config]').forEach(init);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
} else {
    initAll();
}
