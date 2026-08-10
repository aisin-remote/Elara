import { apiRequest } from './api';

const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[character]));

const relativeTime = (value) => {
    if (! value) return 'now';
    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const unit = Math.abs(seconds) < 3600 ? 'minute' : (Math.abs(seconds) < 86400 ? 'hour' : 'day');
    const divisor = unit === 'minute' ? 60 : (unit === 'hour' ? 3600 : 86400);
    return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(Math.round(seconds / divisor), unit);
};

export function initNotifications() {
    const root = document.querySelector('[data-notification-center]');
    if (! root) return;
    const toggle = root.querySelector('[data-notification-toggle]');
    const panel = root.querySelector('[data-notification-panel]');
    const list = root.querySelector('[data-notification-list]');
    const badge = root.querySelector('[data-notification-badge]');
    const summary = root.querySelector('[data-notification-summary]');
    let notifications = [];
    let unread = 0;

    function render() {
        badge.textContent = unread > 99 ? '99+' : unread;
        badge.classList.toggle('hidden', unread === 0);
        summary.textContent = unread ? `${unread} unread` : 'You are all caught up';
        list.innerHTML = notifications.length ? notifications.map((item) => `
            <button type="button" data-notification-id="${item.id}" data-notification-url="${escapeHtml(item.url || '#')}" class="block w-full border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800 ${item.read_at ? '' : 'bg-orbit-50/60 dark:bg-orbit-950/30'}">
                <span class="flex items-start gap-3"><span class="mt-1 size-2 shrink-0 rounded-full ${item.read_at ? 'bg-slate-200 dark:bg-slate-700' : 'bg-orbit-500'}"></span><span class="min-w-0"><strong class="block truncate text-sm">${escapeHtml(item.title)}</strong><span class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-slate-500">${escapeHtml(item.body)}</span><small class="mt-1 block text-[11px] text-slate-400">${relativeTime(item.created_at)}</small></span></span>
            </button>`).join('') : '<div class="p-8 text-center text-sm text-slate-500">No notifications yet.</div>';
    }

    async function load() {
        const query = root.dataset.workspace ? `?workspace_public_id=${root.dataset.workspace}` : '';
        const payload = await apiRequest(root.dataset.url + query);
        notifications = payload.data;
        unread = payload.meta.unread_count;
        render();
    }

    toggle.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        toggle.setAttribute('aria-expanded', String(! panel.classList.contains('hidden')));
    });
    document.addEventListener('click', (event) => { if (! root.contains(event.target)) panel.classList.add('hidden'); });
    list.addEventListener('click', async (event) => {
        const item = event.target.closest('[data-notification-id]');
        if (! item) return;
        await apiRequest(`/api/internal/notifications/${item.dataset.notificationId}/read`, { method: 'POST', body: '{}' });
        const current = notifications.find((notification) => notification.id === item.dataset.notificationId);
        if (current && ! current.read_at) { current.read_at = new Date().toISOString(); unread = Math.max(0, unread - 1); }
        render();
        if (item.dataset.notificationUrl && item.dataset.notificationUrl !== '#') location.href = item.dataset.notificationUrl;
    });
    root.querySelector('[data-notification-read-all]')?.addEventListener('click', async () => {
        await apiRequest(root.dataset.readAllUrl, { method: 'POST', body: '{}' });
        unread = 0;
        notifications = notifications.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() }));
        render();
    });

    if (window.Echo) {
        window.Echo.private(`users.${root.dataset.user}`).notification((notification) => {
            notifications.unshift({ ...notification, read_at: null, created_at: new Date().toISOString() });
            unread += 1;
            render();
        });
    } else {
        setInterval(() => load().catch(() => {}), 30000);
    }
    load().catch(() => {});
}

function urlBase64ToUint8Array(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
}

export function initNotificationSettings() {
    const root = document.querySelector('[data-notification-settings]');
    if (! root) return;
    const form = root.querySelector('[data-preference-form]');
    const status = root.querySelector('[data-preference-status]');
    const notify = (message) => window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: message }));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const preferences = {};
        root.querySelectorAll('[data-preference-row]').forEach((row) => {
            preferences[row.dataset.preferenceRow] = Object.fromEntries([...row.querySelectorAll('[data-channel]')].map((input) => [input.dataset.channel, input.checked]));
        });
        status.textContent = 'Saving…';
        try {
            await apiRequest(root.dataset.endpoint, { method: 'PATCH', body: JSON.stringify({ workspace_public_id: root.dataset.workspace, preferences }) });
            status.textContent = 'Preferences saved.';
            notify('Notification preferences saved.');
        } catch (error) {
            status.textContent = error.message;
        }
    });

    root.querySelector('[data-enable-push]')?.addEventListener('click', async () => {
        try {
            if (! root.dataset.vapidKey) throw new Error('VAPID key is not configured.');
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('Browser notification permission was not granted.');
            const registration = await navigator.serviceWorker.register('/sw.js');
            const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(root.dataset.vapidKey) });
            const json = subscription.toJSON();
            await apiRequest('/api/internal/push-subscriptions', { method: 'POST', body: JSON.stringify({
                endpoint: json.endpoint,
                keys: json.keys,
                content_encoding: window.PushManager?.supportedContentEncodings?.[0] || 'aesgcm',
            }) });
            notify('Push notifications enabled on this device.');
        } catch (error) {
            notify(error.message);
        }
    });
}
