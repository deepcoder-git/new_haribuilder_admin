import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('kt_calendar_app');
    var calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: [], 
        editable: true,
        droppable: true,
    });

    calendar.render();

   
    if (window.livewire) {
        window.livewire.on('refreshCalendar', (events) => {
            calendar.removeAllEvents();
            calendar.addEventSource(events);
        });
    } else {
        console.error('Livewire is not loaded.');
    }
});