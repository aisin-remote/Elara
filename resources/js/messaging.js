import { apiRequest } from './api';

const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
}[character]));

const initials = (name = 'O') => name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
const timeLabel = (value) => value ? new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(new Date(value)) : '';

export function initMessaging() {
    const root = document.querySelector('[data-messages-app]');
    if (! root) return;

    const elements = Object.fromEntries([
        'conversation-list', 'conversation-search', 'conversation-panel', 'thread-panel', 'empty-thread', 'active-thread',
        'thread-title', 'thread-avatar', 'thread-presence', 'message-list', 'message-form', 'message-body', 'message-files',
        'attachment-preview', 'typing', 'load-older-wrap', 'load-older', 'details-empty', 'details-content', 'details-title',
        'details-avatar', 'details-type', 'details-members', 'conversation-dialog', 'conversation-form', 'conversation-type',
        'title-field', 'project-field', 'participant-field', 'conversation-error',
    ].map((name) => [name, root.querySelector(`[data-${name}]`)]));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let conversations = [];
    let active = null;
    let messages = [];
    let nextCursorUrl = null;
    let channel = null;
    let channelName = null;
    let polling = null;
    let typingTimer = null;

    const notify = (message) => window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: message }));
    const jsonHeaders = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf };

    function renderConversations() {
        const query = elements['conversation-search'].value.trim().toLowerCase();
        const filtered = conversations.filter((conversation) => conversation.title.toLowerCase().includes(query));
        elements['conversation-list'].innerHTML = filtered.length ? filtered.map((conversation) => `
            <button type="button" data-open-conversation="${conversation.public_id}" class="mb-1 flex w-full gap-3 rounded-xl p-3 text-left transition ${active?.public_id === conversation.public_id ? 'bg-orbit-50 dark:bg-orbit-950/50' : 'hover:bg-slate-50 dark:hover:bg-slate-800'}">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl ${conversation.type === 'project' ? 'bg-amber-100 text-amber-700' : 'bg-orbit-100 text-orbit-700'} text-sm font-bold dark:bg-slate-800 dark:text-slate-200">${escapeHtml(initials(conversation.title))}</span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2"><strong class="truncate text-sm">${escapeHtml(conversation.title)}</strong><small class="shrink-0 text-[11px] text-slate-400">${timeLabel(conversation.last_message_at)}</small></span>
                    <span class="mt-1 flex items-center justify-between gap-2"><span class="truncate text-xs text-slate-500">${escapeHtml(conversation.last_message?.body || 'No messages yet')}</span>${conversation.unread_count ? `<span class="grid min-w-5 place-items-center rounded-full bg-orbit-600 px-1.5 py-0.5 text-[10px] font-bold text-white">${conversation.unread_count}</span>` : ''}</span>
                </span>
            </button>`).join('') : '<div class="p-5 text-center text-sm text-slate-500">No conversations found.</div>';
    }

    function renderDetails() {
        if (! active) return;
        elements['details-empty'].classList.add('hidden');
        elements['details-content'].classList.remove('hidden');
        elements['details-title'].textContent = active.title;
        elements['details-avatar'].textContent = initials(active.title);
        elements['details-type'].textContent = `${active.type} conversation`;
        elements['details-members'].innerHTML = active.participants.map((person) => `
            <div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-slate-100 text-xs font-bold dark:bg-slate-800">${escapeHtml(initials(person.name))}</span><span class="truncate text-sm font-semibold">${escapeHtml(person.name)}</span></div>
        `).join('');
    }

    function messageHtml(message) {
        const attachments = (message.attachments || []).map((file) => `
            <a href="${escapeHtml(file.download_url)}" class="mt-2 flex max-w-sm items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-sm hover:border-orbit-300 dark:border-slate-700 dark:bg-slate-900">
                <span class="text-xl">📄</span><span class="min-w-0"><strong class="block truncate">${escapeHtml(file.name)}</strong><small class="text-slate-500">${Math.max(1, Math.round(file.size / 1024))} KB</small></span>
            </a>`).join('');
        const reactions = (message.reactions || []).map((reaction) => `
            <button type="button" data-react="${escapeHtml(reaction.emoji)}" data-message="${message.public_id}" title="${escapeHtml((reaction.people || []).join(', '))}" class="rounded-full border px-2 py-0.5 text-xs ${reaction.reacted ? 'border-orbit-300 bg-orbit-50 text-orbit-700 dark:bg-orbit-950/60' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'}">${escapeHtml(reaction.emoji)} ${reaction.count}</button>`).join('');
        const readReceipt = message.is_own && message.read_by?.length ? `<span title="Read by ${escapeHtml(message.read_by.join(', '))}" class="text-orbit-600">✓✓</span>` : '';

        return `<article data-message-row="${message.public_id}" class="group flex gap-3 ${message.is_own ? 'flex-row-reverse' : ''}">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-xs font-bold dark:bg-slate-800">${escapeHtml(initials(message.sender.name))}</span>
            <div class="max-w-[min(80%,680px)] ${message.is_own ? 'items-end' : 'items-start'} flex flex-col">
                <div class="mb-1 flex items-center gap-2 text-[11px] text-slate-400 ${message.is_own ? 'flex-row-reverse' : ''}"><strong class="text-slate-600 dark:text-slate-300">${escapeHtml(message.sender.name)}</strong><span>${timeLabel(message.created_at)}</span>${message.edited_at ? '<span>edited</span>' : ''}${readReceipt}</div>
                <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed ${message.is_own ? 'rounded-tr-md bg-orbit-600 text-white' : 'rounded-tl-md bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'}">${message.body ? escapeHtml(message.body).replace(/\n/g, '<br>') : ''}${attachments}</div>
                <div class="mt-1 flex flex-wrap items-center gap-1 ${message.is_own ? 'justify-end' : ''}">${reactions}<span class="hidden gap-1 group-hover:flex"><button type="button" data-react="👍" data-message="${message.public_id}" class="rounded-full px-1.5 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">👍</button><button type="button" data-react="❤️" data-message="${message.public_id}" class="rounded-full px-1.5 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">❤️</button><button type="button" data-react="🎉" data-message="${message.public_id}" class="rounded-full px-1.5 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">🎉</button>${message.can_edit ? `<button type="button" data-edit-message="${message.public_id}" class="px-1 text-[11px] font-semibold text-slate-500">Edit</button>` : ''}${message.can_delete ? `<button type="button" data-delete-message="${message.public_id}" class="px-1 text-[11px] font-semibold text-rose-500">Delete</button>` : ''}</span></div>
            </div>
        </article>`;
    }

    function renderMessages(scroll = false) {
        elements['message-list'].innerHTML = messages.length ? messages.map(messageHtml).join('') : '<div class="grid h-full place-items-center text-center text-sm text-slate-500"><div><span class="text-3xl">👋</span><p class="mt-2">Start this conversation.</p></div></div>';
        elements['load-older-wrap'].classList.toggle('hidden', ! nextCursorUrl);
        if (scroll) elements['message-list'].scrollTop = elements['message-list'].scrollHeight;
    }

    async function loadConversations(selectId = null) {
        elements['conversation-list'].setAttribute('aria-busy', 'true');
        try {
            const payload = await apiRequest(root.dataset.conversationsUrl);
            const currentId = active?.public_id;
            conversations = payload.data;
            if (currentId) active = conversations.find((conversation) => conversation.public_id === currentId) || active;
            renderConversations();
            const target = selectId || active?.public_id || root.dataset.initialConversation || conversations[0]?.public_id;
            if (target && active?.public_id !== target) await openConversation(target);
        } catch (error) {
            elements['conversation-list'].innerHTML = `<div class="m-3 rounded-xl bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">${escapeHtml(error.message)}</div>`;
            throw error;
        } finally {
            elements['conversation-list'].setAttribute('aria-busy', 'false');
        }
    }

    async function loadMessages(url = null, appendOlder = false) {
        if (! active) return;
        const payload = await apiRequest(url || `/api/internal/conversations/${active.public_id}/messages`);
        const page = payload.data.slice().reverse();
        messages = appendOlder ? [...page, ...messages] : page;
        nextCursorUrl = payload.links?.next;
        renderMessages(! appendOlder);
        if (! appendOlder && messages.length) await markRead(messages[messages.length - 1].public_id);
    }

    async function markRead(messagePublicId) {
        await apiRequest(`/api/internal/conversations/${active.public_id}/read`, {
            method: 'POST', body: JSON.stringify({ message_public_id: messagePublicId }),
        });
        active.unread_count = 0;
        renderConversations();
    }

    function connectRealtime() {
        if (channelName && window.Echo) window.Echo.leave(`conversations.${channelName}`);
        channel = null;
        channelName = null;
        clearInterval(polling);

        if (window.Echo) {
            channelName = active.public_id;
            channel = window.Echo.join(`conversations.${active.public_id}`)
                .here((users) => { elements['thread-presence'].textContent = `${users.length} online`; })
                .joining(() => { elements['thread-presence'].textContent = 'Online now'; })
                .leaving(() => { elements['thread-presence'].textContent = `${active.participants.length} members`; })
                .listen('.message.sent', ({ message }) => upsertMessage(message, true))
                .listen('.message.updated', ({ message }) => upsertMessage(message))
                .listen('.message.reaction', ({ message }) => upsertMessage(message))
                .listen('.message.deleted', ({ message_public_id: id }) => { messages = messages.filter((item) => item.public_id !== id); renderMessages(); })
                .listenForWhisper('typing', ({ name }) => {
                    elements.typing.textContent = `${name} is typing…`;
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => { elements.typing.textContent = ''; }, 1800);
                });
        } else {
            elements['thread-presence'].textContent = `${active.participants.length} members`;
            polling = setInterval(() => loadMessages().catch(() => {}), 8000);
        }
    }

    function upsertMessage(message, scroll = false) {
        const index = messages.findIndex((item) => item.public_id === message.public_id);
        if (index >= 0) messages[index] = message;
        else messages.push(message);
        renderMessages(scroll);
        if (! message.is_own) markRead(message.public_id).catch(() => {});
    }

    async function openConversation(id) {
        active = conversations.find((conversation) => conversation.public_id === id);
        if (! active) return;
        elements['empty-thread'].classList.add('hidden');
        elements['active-thread'].classList.remove('hidden');
        elements['active-thread'].classList.add('flex');
        elements['thread-title'].textContent = active.title;
        elements['thread-avatar'].textContent = initials(active.title);
        elements['conversation-panel'].classList.add('hidden', 'lg:block');
        elements['thread-panel'].classList.remove('hidden');
        renderConversations();
        renderDetails();
        await loadMessages();
        connectRealtime();
        history.replaceState({}, '', `${location.pathname}?conversation=${active.public_id}`);
    }

    async function createConversation(form) {
        const data = new FormData(form);
        const payload = {
            type: data.get('type'),
            title: data.get('title') || null,
            project_public_id: data.get('project_public_id') || null,
            participant_public_ids: data.getAll('participant_public_ids[]'),
        };
        try {
            const response = await apiRequest(root.dataset.createUrl, { method: 'POST', body: JSON.stringify(payload) });
            elements['conversation-dialog'].close();
            form.reset();
            await loadConversations(response.data.public_id);
        } catch (error) {
            elements['conversation-error'].textContent = Object.values(error.payload?.errors || {}).flat()[0] || error.message;
            elements['conversation-error'].classList.remove('hidden');
        }
    }

    async function sendMessage(form) {
        if (! active) return;
        const data = new FormData();
        data.append('body', elements['message-body'].value);
        [...elements['message-files'].files].forEach((file) => data.append('attachments[]', file));
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
        if (window.Echo?.socketId()) headers['X-Socket-ID'] = window.Echo.socketId();
        const response = await fetch(`/api/internal/conversations/${active.public_id}/messages`, { method: 'POST', headers, body: data });
        const payload = await response.json();
        if (! response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message);
        elements['message-body'].value = '';
        elements['message-files'].value = '';
        elements['attachment-preview'].classList.add('hidden');
        upsertMessage(payload.data, true);
        await loadConversations(active.public_id);
    }

    async function react(messageId, emoji) {
        const payload = await apiRequest(`/api/internal/messages/${messageId}/reactions`, { method: 'POST', body: JSON.stringify({ emoji }) });
        upsertMessage(payload.data);
    }

    async function editMessage(messageId) {
        const message = messages.find((item) => item.public_id === messageId);
        const body = window.prompt('Edit message', message?.body || '');
        if (body === null || ! body.trim()) return;
        const payload = await apiRequest(`/api/internal/messages/${messageId}`, { method: 'PATCH', body: JSON.stringify({ body }) });
        upsertMessage(payload.data);
    }

    async function deleteMessage(messageId) {
        if (! window.confirm('Delete this message?')) return;
        await apiRequest(`/api/internal/messages/${messageId}`, { method: 'DELETE' });
        messages = messages.filter((item) => item.public_id !== messageId);
        renderMessages();
    }

    root.addEventListener('click', (event) => {
        const open = event.target.closest('[data-open-conversation]');
        if (open) openConversation(open.dataset.openConversation).catch((error) => notify(error.message));
        if (event.target.closest('[data-new-conversation]')) elements['conversation-dialog'].showModal();
        if (event.target.closest('[data-close-conversation]')) elements['conversation-dialog'].close();
        if (event.target.closest('[data-back-conversations]')) {
            elements['conversation-panel'].classList.remove('hidden');
            elements['thread-panel'].classList.add('hidden', 'lg:flex');
        }
        const reaction = event.target.closest('[data-react]');
        if (reaction) react(reaction.dataset.message, reaction.dataset.react).catch((error) => notify(error.message));
        const edit = event.target.closest('[data-edit-message]');
        if (edit) editMessage(edit.dataset.editMessage).catch((error) => notify(error.message));
        const remove = event.target.closest('[data-delete-message]');
        if (remove) deleteMessage(remove.dataset.deleteMessage).catch((error) => notify(error.message));
        if (event.target.closest('[data-emoji-toggle]')) root.querySelector('[data-emoji-menu]').classList.toggle('hidden');
        const insert = event.target.closest('[data-insert-emoji]');
        if (insert) { elements['message-body'].value += insert.dataset.insertEmoji; elements['message-body'].focus(); }
    });
    elements['conversation-search'].addEventListener('input', renderConversations);
    elements['conversation-form'].addEventListener('submit', (event) => { event.preventDefault(); createConversation(event.currentTarget); });
    elements['message-form']?.addEventListener('submit', (event) => { event.preventDefault(); sendMessage(event.currentTarget).catch((error) => notify(error.message)); });
    elements['message-files']?.addEventListener('change', () => {
        const names = [...elements['message-files'].files].map((file) => file.name);
        elements['attachment-preview'].textContent = names.length ? `Attached: ${names.join(', ')}` : '';
        elements['attachment-preview'].classList.toggle('hidden', ! names.length);
    });
    elements['message-body']?.addEventListener('input', () => channel?.whisper('typing', { name: document.body.dataset.userName || 'Someone' }));
    elements['load-older'].addEventListener('click', () => loadMessages(nextCursorUrl, true).catch((error) => notify(error.message)));
    elements['conversation-type'].addEventListener('change', () => {
        const type = elements['conversation-type'].value;
        elements['title-field'].classList.toggle('hidden', type !== 'group');
        elements['project-field'].classList.toggle('hidden', type !== 'project');
        elements['participant-field'].classList.toggle('hidden', type === 'project');
    });
    elements['participant-field'].addEventListener('change', (event) => {
        if (elements['conversation-type'].value === 'direct' && event.target.checked) {
            elements['participant-field'].querySelectorAll('input').forEach((input) => { if (input !== event.target) input.checked = false; });
        }
    });

    loadConversations().catch((error) => notify(error.message));
}
