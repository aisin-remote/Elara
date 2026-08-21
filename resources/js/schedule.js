import { apiRequest } from './api';

const DAY = 86400000;
const HOUR_HEIGHT = 64;

function escapeHtml(value = '') {
    return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
}

function parts(date, timezone) {
    return new Intl.DateTimeFormat('en', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
        .formatToParts(date).reduce((values, part) => ({ ...values, [part.type]: part.value }), {});
}

function dateKey(date, timezone) {
    const value = parts(date, timezone);
    return `${value.year}-${value.month}-${value.day}`;
}

function addDays(date, days) {
    return new Date(date.getTime() + days * DAY);
}

function startOfWeek(day, weekStart) {
    return addDays(day, -((day.getUTCDay() - weekStart + 7) % 7));
}

function localInput(iso, timezone) {
    if (! iso) return '';
    const value = parts(new Date(iso), timezone);
    return `${value.year}-${value.month}-${value.day}T${value.hour}:${value.minute}`;
}

function localAt(day, minutes) {
    const hour = String(Math.floor(minutes / 60)).padStart(2, '0');
    const minute = String(minutes % 60).padStart(2, '0');
    return `${day}T${hour}:${minute}`;
}

async function moveItem(item, day, minutes) {
    const mutation = { ...item.mutation };
    const duration = Math.max(30, (new Date(item.end || item.start) - new Date(item.start)) / 60000 || 60);

    if (item.type === 'event') {
        mutation.start_at = localAt(day, minutes);
        const endDate = new Date(Date.parse(`${day}T00:00:00Z`) + (minutes + duration) * 60000);
        mutation.end_at = `${endDate.toISOString().slice(0, 10)}T${endDate.toISOString().slice(11, 16)}`;
    } else if (mutation.start_at) {
        mutation.start_at = localAt(day, minutes);
        const endDate = new Date(Date.parse(`${day}T00:00:00Z`) + (minutes + duration) * 60000);
        mutation.due_at = `${endDate.toISOString().slice(0, 10)}T${endDate.toISOString().slice(11, 16)}`;
    } else {
        mutation.due_at = localAt(day, minutes);
    }

    return apiRequest(item.mutation_url, { method: 'PATCH', body: JSON.stringify(mutation) });
}

function openEditor(root, item) {
    if (item.type !== 'event') {
        window.location = item.url;
        return;
    }

    const dialog = document.getElementById(root.dataset.scheduleDialog || 'schedule-edit-dialog');
    if (! dialog) return;

    const detailTitle = dialog.querySelector('[data-schedule-detail-title]');
    const detailDescription = dialog.querySelector('[data-schedule-detail-description]');
    const detailWhen = dialog.querySelector('[data-schedule-detail-when]');
    const momLink = dialog.querySelector('[data-schedule-mom]');
    const meetingLink = dialog.querySelector('[data-schedule-meeting-link]');

    if (detailTitle) detailTitle.textContent = item.title;
    if (detailDescription) detailDescription.textContent = item.description || 'No agenda or notes were added.';
    if (detailWhen) {
        const formatter = new Intl.DateTimeFormat('en', { timeZone: root.dataset.timezone, dateStyle: 'medium', timeStyle: 'short' });
        detailWhen.textContent = `${formatter.format(new Date(item.start))} – ${formatter.format(new Date(item.end))}`;
    }
    if (momLink) {
        momLink.href = item.mom_url;
        momLink.textContent = item.mom_label;
    }
    if (meetingLink) {
        meetingLink.classList.toggle('hidden', ! item.meeting_url);
        meetingLink.href = item.meeting_url || '#';
    }

    const form = dialog.querySelector('[data-schedule-edit-form]');
    if (form && item.mutation_url) {
        form.action = item.mutation_url;
        const deleteForm = dialog.querySelector('[data-schedule-delete-form]');
        if (deleteForm) deleteForm.action = item.mutation_url;
        Object.entries(item.mutation || {}).forEach(([field, value]) => {
            const input = form.querySelector(`[data-schedule-field="${field}"]`);
            if (input) input.value = ['start_at', 'end_at'].includes(field) ? localInput(value, root.dataset.timezone) : (value ?? '');
        });
        form.querySelectorAll('[data-schedule-attendee]').forEach((input) => {
            input.checked = (item.mutation.attendee_public_ids || []).includes(input.value);
        });
    }
    dialog.showModal();
}

function renderSchedule(root, week, items) {
    const timezone = root.dataset.timezone;
    const days = Array.from({ length: 7 }, (_, index) => addDays(week, index));
    const itemMap = new Map(items.map((item) => [item.id, item]));
    const dayColumns = days.map((day) => {
        const key = day.toISOString().slice(0, 10);
        const cards = items.filter((item) => dateKey(new Date(item.start), timezone) === key).map((item) => {
            const startParts = parts(new Date(item.start), timezone);
            const startMinutes = Number(startParts.hour) * 60 + Number(startParts.minute);
            const duration = Math.max(30, (new Date(item.end || item.start) - new Date(item.start)) / 60000 || 60);
            const top = startMinutes / 60 * HOUR_HEIGHT;
            const height = Math.max(38, Math.min(duration / 60 * HOUR_HEIGHT, 1440 - top));
            return `<article ${item.can_update ? 'draggable="true"' : ''} data-schedule-item="${escapeHtml(item.id)}" class="absolute left-1 right-1 overflow-hidden rounded-lg border-l-4 bg-white p-2 text-xs shadow-sm hover:z-20 dark:bg-slate-900" style="top:${top}px;height:${height}px;border-color:${escapeHtml(item.color)}"><button type="button" data-schedule-open class="block w-full text-left"><strong class="block truncate">${escapeHtml(item.title)}</strong><span class="text-slate-500">${startParts.hour}:${startParts.minute}</span></button>${item.meeting_url ? `<a href="${escapeHtml(item.meeting_url)}" target="_blank" rel="noopener" class="mt-1 block font-semibold text-orbit-700" data-schedule-join>Join meeting</a>` : ''}</article>`;
        }).join('');
        const today = key === dateKey(new Date(), timezone);
        const now = parts(new Date(), timezone);
        const line = today ? `<span class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-rose-500" style="top:${(Number(now.hour) * 60 + Number(now.minute)) / 60 * HOUR_HEIGHT}px"><span class="absolute -left-1 -top-1 size-2 rounded-full bg-rose-500"></span></span>` : '';
        return `<div class="relative border-l border-slate-200 dark:border-slate-800" style="height:${24 * HOUR_HEIGHT}px" data-schedule-day="${key}">${line}${cards}</div>`;
    }).join('');
    const timeLabels = Array.from({ length: 24 }, (_, hour) => `<span class="absolute right-2 -translate-y-2 text-xs text-slate-400" style="top:${hour * HOUR_HEIGHT}px">${String(hour).padStart(2, '0')}:00</span>`).join('');
    const gridLines = `repeating-linear-gradient(to bottom, transparent 0, transparent ${HOUR_HEIGHT - 1}px, rgb(226 232 240) ${HOUR_HEIGHT}px)`;
    const header = days.map((day) => `<div class="border-l border-slate-200 px-2 py-3 text-center dark:border-slate-800"><strong class="block text-xs uppercase text-slate-400">${new Intl.DateTimeFormat('en', { weekday: 'short', timeZone: 'UTC' }).format(day)}</strong><span class="text-sm font-bold">${day.getUTCDate()}</span></div>`).join('');

    root.querySelector('[data-schedule-title]').textContent = `${new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(days[0])} – ${new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(days[6])}`;
    root.querySelector('[data-schedule-grid]').innerHTML = `<div class="grid min-w-[1080px] grid-cols-[64px_repeat(7,minmax(140px,1fr))]"><div></div>${header}<div class="relative" style="height:${24 * HOUR_HEIGHT}px">${timeLabels}</div><div class="col-span-7 grid grid-cols-7" style="background:${gridLines}">${dayColumns}</div></div>`;
    if (! root.dataset.initialScroll) {
        root.querySelector('[data-schedule-grid]').scrollTop = 8 * HOUR_HEIGHT;
        root.dataset.initialScroll = 'true';
    }
    root.querySelector('[data-schedule-agenda]').innerHTML = items.length
        ? items.sort((a, b) => new Date(a.start) - new Date(b.start)).map((item) => `<article data-schedule-item="${escapeHtml(item.id)}" class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><span class="mt-1 size-2.5 shrink-0 rounded-full" style="background:${escapeHtml(item.color)}"></span><button type="button" data-schedule-open class="min-w-0 flex-1 text-left"><strong class="block text-sm">${escapeHtml(item.title)}</strong><small class="text-slate-500">${new Intl.DateTimeFormat('en', { timeZone: timezone, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(item.start))}</small></button>${item.meeting_url ? `<a href="${escapeHtml(item.meeting_url)}" target="_blank" rel="noopener" class="text-sm font-semibold text-orbit-700" data-schedule-join>Join</a>` : ''}</article>`).join('')
        : '<p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No tasks or events this week.</p>';

    let draggedId = null;
    root.querySelectorAll('[data-schedule-item]').forEach((element) => {
        element.querySelector('[data-schedule-open]').addEventListener('click', () => openEditor(root, itemMap.get(element.dataset.scheduleItem)));
        element.addEventListener('dragstart', () => { draggedId = element.dataset.scheduleItem; });
    });
    root.querySelectorAll('[data-schedule-day]').forEach((column) => {
        column.addEventListener('dragover', (event) => event.preventDefault());
        column.addEventListener('drop', async (event) => {
            event.preventDefault();
            const item = itemMap.get(draggedId);
            if (! item) return;
            const minutes = Math.max(0, Math.min(1425, Math.round(((event.clientY - column.getBoundingClientRect().top) / HOUR_HEIGHT * 60) / 15) * 15));
            try {
                const payload = await moveItem(item, column.dataset.scheduleDay, minutes);
                window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: payload.message || 'Schedule updated.' }));
                root.dispatchEvent(new CustomEvent('schedule:reload'));
            } catch (error) {
                window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: error.message }));
            }
        });
        column.addEventListener('dblclick', (event) => {
            const dialog = document.getElementById('schedule-create-dialog');
            if (! dialog) return;
            const minutes = Math.max(0, Math.min(1425, Math.round(((event.clientY - column.getBoundingClientRect().top) / HOUR_HEIGHT * 60) / 15) * 15));
            dialog.querySelector('[data-schedule-field="start_at"]').value = localAt(column.dataset.scheduleDay, minutes);
            dialog.querySelector('[data-schedule-field="end_at"]').value = localAt(column.dataset.scheduleDay, Math.min(1439, minutes + 60));
            dialog.showModal();
        });
    });
}

export function initSchedules() {
    document.querySelectorAll('[data-schedule]').forEach((root) => {
        const today = new Date(`${dateKey(new Date(), root.dataset.timezone)}T00:00:00Z`);
        let week = startOfWeek(today, Number(root.dataset.weekStart || 1));
        const load = async () => {
            const url = new URL(root.dataset.url, window.location.origin);
            url.searchParams.set('start', week.toISOString().slice(0, 10));
            url.searchParams.set('end', addDays(week, 7).toISOString().slice(0, 10));
            root.querySelector('[data-schedule-loading]').classList.remove('hidden');
            try {
                const payload = await apiRequest(url);
                renderSchedule(root, week, payload.data);
            } catch (error) {
                root.querySelector('[data-schedule-agenda]').innerHTML = `<p class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(error.message)}</p>`;
            } finally {
                root.querySelector('[data-schedule-loading]').classList.add('hidden');
            }
        };
        root.querySelectorAll('[data-schedule-action]').forEach((button) => button.addEventListener('click', () => {
            const action = button.dataset.scheduleAction;
            week = action === 'today' ? startOfWeek(today, Number(root.dataset.weekStart || 1)) : addDays(week, action === 'next' ? 7 : -7);
            load();
        }));
        root.addEventListener('schedule:reload', load);
        load();
    });
}
