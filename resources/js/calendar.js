import { apiRequest } from './api';

const DAY = 86400000;

function escapeHtml(value = '') {
    return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
}

function dateKey(date, timezone) {
    const parts = new Intl.DateTimeFormat('en', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' })
        .formatToParts(date).reduce((values, part) => ({ ...values, [part.type]: part.value }), {});

    return `${parts.year}-${parts.month}-${parts.day}`;
}

function addDays(date, days) {
    return new Date(date.getTime() + days * DAY);
}

function startOfGrid(month, weekStart) {
    const first = new Date(Date.UTC(month.getUTCFullYear(), month.getUTCMonth(), 1));
    return addDays(first, -((first.getUTCDay() - weekStart + 7) % 7));
}

function eventDates(event, timezone) {
    const start = dateKey(new Date(event.start), timezone);
    const end = dateKey(new Date(event.end || event.start), timezone);
    const dates = [];

    for (let cursor = new Date(`${start}T00:00:00Z`), limit = 0; dateKey(cursor, 'UTC') <= end && limit < 42; cursor = addDays(cursor, 1), limit += 1) {
        dates.push(dateKey(cursor, 'UTC'));
    }

    return dates;
}

async function moveEvent(event, targetDate) {
    const sourceDate = dateKey(new Date(event.start), event.timezone);
    const delta = (Date.parse(`${targetDate}T00:00:00Z`) - Date.parse(`${sourceDate}T00:00:00Z`)) / DAY;
    const mutation = { ...event.mutation };
    const shift = (value) => value ? new Date(new Date(value).getTime() + delta * DAY).toISOString() : null;

    if (event.type === 'task') {
        mutation.start_at = shift(mutation.start_at);
        mutation.due_at = shift(mutation.due_at);
    } else {
        mutation.start_at = shift(mutation.start_at);
        mutation.end_at = shift(mutation.end_at);
    }

    return apiRequest(event.mutation_url, { method: 'PATCH', body: JSON.stringify(mutation) });
}

function renderCalendar(root, month, events) {
    const timezone = root.dataset.timezone;
    const weekStart = Number(root.dataset.weekStart || 1);
    const gridStart = startOfGrid(month, weekStart);
    const monthNumber = month.getUTCMonth();
    const byDate = new Map();

    events.forEach((event) => {
        event.timezone = timezone;
        eventDates(event, timezone).forEach((key) => byDate.set(key, [...(byDate.get(key) || []), event]));
    });

    root.querySelector('[data-calendar-title]').textContent = new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(month);
    root.querySelector('[data-calendar-month]').value = month.toISOString().slice(0, 7);
    const headers = Array.from({ length: 7 }, (_, index) => new Intl.DateTimeFormat('en', { weekday: 'short', timeZone: 'UTC' }).format(addDays(gridStart, index)));
    const cells = Array.from({ length: 42 }, (_, index) => {
        const day = addDays(gridStart, index);
        const key = day.toISOString().slice(0, 10);
        const items = byDate.get(key) || [];
        const isOutside = day.getUTCMonth() !== monthNumber;
        const isToday = key === dateKey(new Date(), timezone);
        const chips = items.map((event, itemIndex) => `<a href="${escapeHtml(event.url || event.meeting_url || '#')}" ${event.can_update ? 'draggable="true"' : ''} data-calendar-event="${escapeHtml(event.id)}" class="${itemIndex > 2 ? 'hidden' : ''} block truncate rounded-md px-2 py-1 text-xs font-semibold text-white shadow-sm" style="background:${escapeHtml(event.color)}" title="${escapeHtml(event.title)}">${escapeHtml(event.title)}</a>`).join('');
        const more = items.length > 3 ? `<button type="button" data-calendar-more class="mt-1 text-xs font-semibold text-orbit-700">+${items.length - 3} more</button>` : '';

        return `<div class="min-h-32 border-b border-r border-slate-200 p-2 dark:border-slate-800 ${isOutside ? 'bg-slate-50/70 text-slate-400 dark:bg-slate-950/40' : ''}" data-calendar-day="${key}"><span class="grid size-7 place-items-center rounded-full text-xs font-semibold ${isToday ? 'bg-orbit-600 text-white' : ''}">${day.getUTCDate()}</span><div class="mt-2 space-y-1">${chips}${more}</div></div>`;
    }).join('');

    root.querySelector('[data-calendar-grid]').innerHTML = `<div class="grid grid-cols-7 bg-slate-50 text-center text-xs font-bold uppercase tracking-wide text-slate-400 dark:bg-slate-950">${headers.map((header) => `<div class="border-b border-r border-slate-200 px-2 py-3 dark:border-slate-800">${header}</div>`).join('')}</div><div class="grid grid-cols-7">${cells}</div>`;
    const agenda = events.filter((event) => new Date(event.start).getTime() >= gridStart.getTime() && new Date(event.start).getTime() < addDays(gridStart, 42).getTime()).sort((a, b) => new Date(a.start) - new Date(b.start));
    root.querySelector('[data-calendar-agenda]').innerHTML = agenda.length
        ? agenda.map((event) => `<a href="${escapeHtml(event.url || event.meeting_url || '#')}" class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><span class="mt-1 size-2.5 shrink-0 rounded-full" style="background:${escapeHtml(event.color)}"></span><span><strong class="block text-sm">${escapeHtml(event.title)}</strong><small class="text-slate-500">${new Intl.DateTimeFormat('en', { timeZone: timezone, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(event.start))}</small></span></a>`).join('')
        : '<p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No dated tasks or events this month.</p>';

    const eventMap = new Map(events.map((event) => [event.id, event]));
    let draggedId = null;
    root.querySelectorAll('[data-calendar-event][draggable="true"]').forEach((element) => element.addEventListener('dragstart', () => { draggedId = element.dataset.calendarEvent; }));
    root.querySelectorAll('[data-calendar-day]').forEach((cell) => {
        cell.addEventListener('dragover', (event) => event.preventDefault());
        cell.addEventListener('drop', async (browserEvent) => {
            browserEvent.preventDefault();
            const item = eventMap.get(draggedId);
            if (! item) return;
            try {
                await moveEvent(item, cell.dataset.calendarDay);
                window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: 'Date updated.' }));
                root.dispatchEvent(new CustomEvent('calendar:reload'));
            } catch (error) {
                window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: error.message }));
            }
        });
    });
    root.querySelectorAll('[data-calendar-more]').forEach((button) => button.addEventListener('click', () => {
        button.parentElement.querySelectorAll('a.hidden').forEach((item) => item.classList.remove('hidden'));
        button.remove();
    }));
}

export function initCalendars() {
    document.querySelectorAll('[data-calendar]').forEach((root) => {
        const nowKey = dateKey(new Date(), root.dataset.timezone);
        let month = new Date(`${nowKey.slice(0, 7)}-01T00:00:00Z`);

        const load = async () => {
            const start = startOfGrid(month, Number(root.dataset.weekStart || 1));
            const url = new URL(root.dataset.url, window.location.origin);
            url.searchParams.set('start', start.toISOString().slice(0, 10));
            url.searchParams.set('end', addDays(start, 42).toISOString().slice(0, 10));
            root.querySelector('[data-calendar-loading]').classList.remove('hidden');
            try {
                const payload = await apiRequest(url);
                renderCalendar(root, month, payload.data);
            } catch (error) {
                root.querySelector('[data-calendar-agenda]').innerHTML = `<p class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(error.message)}</p>`;
            } finally {
                root.querySelector('[data-calendar-loading]').classList.add('hidden');
            }
        };

        root.querySelectorAll('[data-calendar-action]').forEach((button) => button.addEventListener('click', () => {
            const action = button.dataset.calendarAction;
            month = action === 'today' ? new Date(`${dateKey(new Date(), root.dataset.timezone).slice(0, 7)}-01T00:00:00Z`) : new Date(Date.UTC(month.getUTCFullYear(), month.getUTCMonth() + (action === 'next' ? 1 : -1), 1));
            load();
        }));
        root.querySelector('[data-calendar-month]').addEventListener('change', (event) => { month = new Date(`${event.target.value}-01T00:00:00Z`); load(); });
        root.addEventListener('calendar:reload', load);
        load();
    });
}
