import './bootstrap';

import Alpine from 'alpinejs';
import { apiRequest } from './api';
import { initAskAi } from './ask-ai';
import { initCalendars } from './calendar';
import { initCharts } from './charts';
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
    sidebarCollapsed: false,
    mobile: window.matchMedia('(max-width: 1023px)').matches,
    sidebarSections: {
        work: true,
        projects: true,
        team: true,
        more: false,
    },
    init() {
        this.sidebarCollapsed = localStorage.getItem('orbitra-sidebar-collapsed') === '1';

        const media = window.matchMedia('(max-width: 1023px)');
        media.addEventListener('change', (event) => {
            this.mobile = event.matches;
            if (! event.matches) this.sidebarOpen = false;
        });

        try {
            const savedSections = JSON.parse(localStorage.getItem('orbitra-sidebar-sections') ?? '{}');
            this.sidebarSections = { ...this.sidebarSections, ...savedSections };
        } catch {
            localStorage.removeItem('orbitra-sidebar-sections');
        }
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
    collapseDesktopSidebar() {
        if (this.mobile) return;
        this.sidebarCollapsed = true;
        localStorage.setItem('orbitra-sidebar-collapsed', '1');
        this.$nextTick(() => this.$refs.sidebarExpand?.focus());
    },
    expandDesktopSidebar() {
        if (this.mobile) return;
        this.sidebarCollapsed = false;
        localStorage.setItem('orbitra-sidebar-collapsed', '0');
        this.$nextTick(() => this.$refs.sidebarCollapse?.focus());
    },
    sidebarSectionOpen(section) {
        return this.sidebarSections[section] ?? true;
    },
    toggleSidebarSection(section) {
        this.sidebarSections[section] = ! this.sidebarSectionOpen(section);
        localStorage.setItem('orbitra-sidebar-sections', JSON.stringify(this.sidebarSections));
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

Alpine.data('globalSearch', ({ endpoint }) => ({
    query: '',
    results: [],
    selected: 0,
    loading: false,
    searched: false,
    error: '',
    request: null,
    open() {
        if (! this.$refs.dialog.open) this.$refs.dialog.showModal();
        this.$nextTick(() => this.$refs.input.focus());
    },
    close() {
        this.$refs.dialog.close();
    },
    reset() {
        this.request?.abort();
        this.query = '';
        this.results = [];
        this.selected = 0;
        this.loading = false;
        this.searched = false;
        this.error = '';
    },
    async search() {
        const query = this.query.trim();
        this.request?.abort();
        this.results = [];
        this.selected = 0;
        this.searched = false;
        this.error = '';

        if (query.length < 2) {
            this.loading = false;
            return;
        }

        const request = new AbortController();
        this.request = request;
        this.loading = true;

        try {
            const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: request.signal,
            });
            if (! response.ok) throw new Error('Search is temporarily unavailable.');

            const payload = await response.json();
            if (this.query.trim() !== query) return;
            this.results = payload.results ?? [];
            this.searched = true;
        } catch (error) {
            if (error.name !== 'AbortError') this.error = error.message;
        } finally {
            if (this.request === request) this.loading = false;
        }
    },
    move(direction) {
        if (! this.results.length) return;
        this.selected = (this.selected + direction + this.results.length) % this.results.length;
        this.$nextTick(() => this.$refs.results.querySelector(`[data-search-index="${this.selected}"]`)?.scrollIntoView({ block: 'nearest' }));
    },
    openSelected() {
        const result = this.results[this.selected];
        if (result) window.location.assign(result.url);
    },
}));

Alpine.data('descriptionDraft', ({ endpoint, kind, initialDescription = '', aiEnabled = true }) => ({
    endpoint,
    kind,
    ai: aiEnabled,
    description: initialDescription,
    generating: false,
    generated: false,
    error: '',
    async generate() {
        const nameInput = this.$root.elements.namedItem('name');
        const systemInput = this.$root.elements.namedItem('system_public_id');
        const name = String(nameInput?.value ?? '').trim();
        const brief = this.description.trim();

        if (! name) {
            this.error = `Enter the ${this.kind} name first.`;
            nameInput?.focus();
            return;
        }

        if (brief.length < 3) {
            this.error = 'Write a short idea first, then let AI expand it.';
            this.$refs.description?.focus();
            return;
        }

        if (this.kind === 'feature' && ! systemInput?.value) {
            this.error = 'Choose a system before generating a feature description.';
            systemInput?.focus();
            return;
        }

        this.generating = true;
        this.generated = false;
        this.error = '';

        try {
            const payload = await apiRequest(this.endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    kind: this.kind,
                    name,
                    description: brief,
                    system_public_id: systemInput?.value || null,
                }),
            });

            this.description = payload.data.description;
            this.generated = true;
            this.$nextTick(() => this.$refs.description?.focus());
        } catch (error) {
            this.error = error.message;
        } finally {
            this.generating = false;
        }
    },
}));

Alpine.data('taskDatabase', () => ({
    propertyPanel: null,
    propertyPosition: { left: 16, top: 16 },
    openTask(defaults = {}) {
        const dialog = document.getElementById('new-task-dialog');
        const form = dialog?.querySelector('form');
        if (! dialog || ! form) return;

        form.reset();
        Object.entries(defaults).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (field) field.value = value ?? '';
        });
        dialog.showModal();
    },
    openProperty(panel, event) {
        if (this.propertyPanel === panel) {
            this.closeProperty();
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const width = Math.min(384, window.innerWidth - 32);
        this.propertyPosition = {
            left: Math.max(16, Math.min(rect.left, window.innerWidth - width - 16)),
            top: Math.max(16, Math.min(rect.bottom + 8, window.innerHeight - 520)),
        };
        this.propertyPanel = panel;
    },
    closeProperty() {
        this.propertyPanel = null;
    },
}));

Alpine.data('inlineTaskProperty', ({ url, initial, type, reloadOnSave = false }) => {
    const empty = type === 'checkbox' ? false : '';
    const startingValue = initial ?? empty;

    return {
        value: startingValue,
        saved: startingValue,
        saving: false,
        request: null,
        async save() {
            const value = type === 'checkbox'
                ? Boolean(this.value)
                : (String(this.value ?? '').trim() || null);

            if (value === this.saved || (value === null && this.saved === '')) return;

            this.request?.abort();
            const request = new AbortController();
            this.request = request;
            this.saving = true;

            try {
                await apiRequest(url, {
                    method: 'PUT',
                    body: JSON.stringify({ value }),
                    signal: request.signal,
                });
                if (this.request !== request) return;
                this.saved = value ?? empty;
                this.value = value ?? empty;
                if (reloadOnSave) window.location.reload();
            } catch (error) {
                if (error.name === 'AbortError') return;
                this.value = this.saved;
                window.dispatchEvent(new CustomEvent('orbitra-toast', {
                    detail: { variant: 'error', title: 'Could not save property', message: error.message },
                }));
            } finally {
                if (this.request === request) this.saving = false;
            }
        },
    };
});

Alpine.data('inlineTaskRow', ({ url, version, initial, reloadFields = [] }) => ({
    url,
    version,
    values: structuredClone(initial),
    saved: structuredClone(initial),
    savingField: null,
    assigneeEditor: false,
    normalize(field) {
        if (field === 'assignees') return [...new Set(this.values.assignees ?? [])].sort();
        if (field === 'description' || field === 'due_at') return String(this.values[field] ?? '').trim() || null;
        return String(this.values[field] ?? '').trim();
    },
    same(left, right) {
        return JSON.stringify(left) === JSON.stringify(right);
    },
    assigneeNames() {
        const selected = new Set(this.values.assignees ?? []);
        const names = [...this.$root.querySelectorAll('[data-assignee-id]')]
            .filter((member) => selected.has(member.dataset.assigneeId))
            .map((member) => member.dataset.assigneeName);

        return names.join(', ') || 'Unassigned';
    },
    cancelAssignees() {
        this.values.assignees = structuredClone(this.saved.assignees ?? []);
        this.assigneeEditor = false;
    },
    async save(field) {
        if (this.savingField !== null) return false;

        const value = this.normalize(field);
        const saved = field === 'assignees' ? [...(this.saved[field] ?? [])].sort() : this.saved[field];
        if (this.same(value, saved)) return true;

        this.savingField = field;

        try {
            const payload = await apiRequest(url, {
                method: 'PATCH',
                body: JSON.stringify({ field, value, version: this.version }),
            });
            this.version = payload.data.version;
            this.saved[field] = structuredClone(value);
            this.values[field] = structuredClone(value ?? '');
            if (reloadFields.includes(field)) window.location.reload();

            return true;
        } catch (error) {
            this.values[field] = structuredClone(this.saved[field] ?? (field === 'assignees' ? [] : ''));
            if (error.response?.status === 409 && error.payload?.server_version) {
                this.version = error.payload.server_version;
            }
            window.dispatchEvent(new CustomEvent('orbitra-toast', {
                detail: {
                    variant: 'error',
                    title: error.response?.status === 409 ? 'Task changed elsewhere' : 'Could not save task',
                    message: error.response?.status === 409 ? 'Your edit was reverted. Review the latest value and try again.' : error.message,
                },
            }));

            return false;
        } finally {
            this.savingField = null;
        }
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
        this.schedulePreview();
    },
    addChecklist(taskIndex) {
        this.tasks[taskIndex].checklist.push('');
    },
    removeChecklist(taskIndex, checklistIndex) {
        this.tasks[taskIndex].checklist.splice(checklistIndex, 1);
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

function initLazyWidgets() {
    const widgets = [...document.querySelectorAll('[data-lazy-widget]')];
    if (! widgets.length) return;

    const load = async (widget) => {
        if (widget.dataset.loading) return;
        widget.dataset.loading = 'true';
        try {
            const response = await fetch(widget.dataset.lazyWidget, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
            if (! response.ok) throw new Error(response.statusText);
            widget.innerHTML = await response.text();
            initCharts();
        } catch {
            widget.innerHTML = '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700">This dashboard section could not be loaded. Refresh to try again.</div>';
        }
    };

    if (! ('IntersectionObserver' in window)) {
        widgets.forEach(load);
        return;
    }
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (! entry.isIntersecting) return;
        observer.unobserve(entry.target);
        load(entry.target);
    }), { rootMargin: '240px' });
    widgets.forEach((widget) => observer.observe(widget));
}

Alpine.start();
initCalendars();
initSchedules();
initCharts();
initMessaging();
initAskAi();
initNotifications();
initNotificationSettings();
initConnectivity();
initSubmitStates();
initLazyWidgets();
