import './bootstrap';

import Alpine from 'alpinejs';
import { apiRequest } from './api';
import { initAskAi } from './ask-ai';
import { initCalendars } from './calendar';
import { initCharts } from './charts';
import { initKanban } from './kanban';
import { initMessaging } from './messaging';
import { initNotifications, initNotificationSettings } from './notifications';
import { initSchedules } from './schedule';

window.Alpine = Alpine;

Alpine.data('themePreference', () => ({
    theme: localStorage.getItem('orbitra-theme') ?? document.documentElement.dataset.theme ?? 'dark',
    savedTheme: localStorage.getItem('orbitra-theme') ?? document.documentElement.dataset.theme ?? 'dark',
    init() {
        // Accounts saved before this became a two-way toggle still hold 'system'. Resolve it
        // once to whatever it was already showing, so the button never reads a third state
        // it can no longer produce.
        if (this.theme === 'system') {
            this.theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        this.apply();
    },
    get isDark() {
        return this.theme === 'dark';
    },
    toggle() {
        this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
    },
    async setTheme(value) {
        const previous = this.savedTheme;
        this.theme = value;
        localStorage.setItem('orbitra-theme', value);
        this.apply();

        const endpoint = document.documentElement.dataset.themeEndpoint;
        if (endpoint) {
            try {
                await apiRequest(endpoint, { method: 'PATCH', body: JSON.stringify({ theme: value }) });
                this.savedTheme = value;
            } catch (error) {
                this.theme = previous;
                localStorage.setItem('orbitra-theme', previous);
                this.apply();
                window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: error.message }));
            }
        }
    },
    apply() {
        document.documentElement.classList.toggle('dark', this.theme === 'dark');
    },
}));

Alpine.data('appShell', () => ({
    sidebarOpen: false,
    mobile: window.matchMedia('(max-width: 1023px)').matches,
    init() {
        const media = window.matchMedia('(max-width: 1023px)');
        media.addEventListener('change', (event) => {
            this.mobile = event.matches;
            if (! event.matches) this.sidebarOpen = false;
        });
    },
    openSidebar() {
        this.sidebarOpen = true;
        this.$nextTick(() => this.$refs.sidebarClose?.focus());
    },
    closeSidebar() {
        if (! this.sidebarOpen) return;
        this.sidebarOpen = false;
        this.$nextTick(() => this.$refs.sidebarTrigger?.focus());
    },
    trapTab(event) {
        if (! this.mobile || ! this.sidebarOpen) return;
        const focusable = [...this.$refs.sidebar.querySelectorAll('a, button, select, input, [tabindex]:not([tabindex="-1"])')]
            .filter((element) => ! element.disabled && element.offsetParent !== null);
        if (! focusable.length) return;
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (! event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    },
}));

let toastId = 0;

Alpine.data('toastStack', () => ({
    toasts: [],
    // Accepts a plain string (most callers) or { message, title, variant, duration }.
    push(detail) {
        const toast = {
            variant: 'info',
            duration: 5000,
            ...(typeof detail === 'string' ? { message: detail } : detail),
            id: ++toastId,
        };
        this.toasts.push(toast);
        setTimeout(() => this.dismiss(toast.id), toast.duration);
    },
    dismiss(id) {
        this.toasts = this.toasts.filter((toast) => toast.id !== id);
    },
    chip(variant) {
        return {
            success: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
            error: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
            warning: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        }[variant] ?? 'bg-orbit-500/15 text-orbit-600 dark:text-orbit-400';
    },
    bar(variant) {
        return {
            success: 'bg-emerald-500',
            error: 'bg-rose-500',
            warning: 'bg-amber-500',
        }[variant] ?? 'bg-orbit-500';
    },
}));

Alpine.data('taskBreakdown', ({ tasks, previewUrl }) => ({
    tasks: tasks.map((task) => ({
        ...task,
        checklist: Array.isArray(task.checklist) ? [...task.checklist] : [],
        depends_on: Array.isArray(task.depends_on) ? task.depends_on.map(Number) : [],
        requires_user_validation: !! task.requires_user_validation,
    })),
    finishLabel: '—',
    timer: null,
    init() {
        this.refreshPreview();
    },
    get totalHours() {
        const minutes = this.tasks.reduce((sum, task) => sum + (Number(task.estimate_minutes) || 0), 0);
        return Math.round((minutes / 60) * 10) / 10;
    },
    remove(index) {
        this.tasks.splice(index, 1);
        this.tasks.forEach((task) => {
            task.depends_on = task.depends_on
                .filter((dependencyIndex) => dependencyIndex !== index)
                .map((dependencyIndex) => dependencyIndex > index ? dependencyIndex - 1 : dependencyIndex);
        });
        this.schedulePreview();
    },
    addChecklist(taskIndex) {
        this.tasks[taskIndex].checklist.push('');
    },
    removeChecklist(taskIndex, checklistIndex) {
        this.tasks[taskIndex].checklist.splice(checklistIndex, 1);
    },
    toggleDependency(taskIndex, dependencyIndex, selected) {
        const dependencies = this.tasks[taskIndex].depends_on;
        this.tasks[taskIndex].depends_on = selected
            ? [...new Set([...dependencies, dependencyIndex])].sort((a, b) => a - b)
            : dependencies.filter((index) => index !== dependencyIndex);
    },
    // Debounced: the reviewer types over an estimate, and one request per keystroke would
    // ask the planner to walk the calendar for nothing.
    schedulePreview() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.refreshPreview(), 400);
    },
    async refreshPreview() {
        const minutes = this.tasks.map((task) => Number(task.estimate_minutes) || 0).filter((value) => value > 0);
        if (! minutes.length) {
            this.finishLabel = '—';
            return;
        }
        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ minutes }),
            });
            if (! response.ok) throw new Error(response.statusText);
            const data = await response.json();
            this.finishLabel = data.finish_label ?? 'not schedulable';
        } catch {
            // The estimate still submits; only the projection is unavailable.
            this.finishLabel = 'unavailable';
        }
    },
}));

Alpine.data('requestMonitoring', ({ initial, url }) => ({
    data: initial,
    syncing: false,
    error: false,
    timer: null,
    init() {
        this.timer = setInterval(() => this.refresh(), 10000);
    },
    destroy() {
        clearInterval(this.timer);
    },
    async refresh() {
        if (this.syncing || document.hidden) return;
        this.syncing = true;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (! response.ok) throw new Error(response.statusText);
            this.data = await response.json();
            this.error = false;
        } catch {
            this.error = true;
        } finally {
            this.syncing = false;
        }
    },
}));

function initConnectivity() {
    const banners = [...document.querySelectorAll('[data-offline-banner]')];
    if (! banners.length) return;
    const render = () => banners.forEach((banner) => banner.classList.toggle('hidden', navigator.onLine));
    window.addEventListener('online', render);
    window.addEventListener('offline', render);
    render();
}

function initSubmitStates() {
    document.addEventListener('submit', (event) => requestAnimationFrame(() => {
        if (event.defaultPrevented) return;
        event.target.setAttribute('aria-busy', 'true');
        if (event.submitter) event.submitter.disabled = true;
    }));
}

Alpine.start();
initKanban();
initCalendars();
initSchedules();
initCharts();
initMessaging();
initAskAi();
initNotifications();
initNotificationSettings();
initConnectivity();
initSubmitStates();
