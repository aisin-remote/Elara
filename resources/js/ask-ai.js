const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const safeLinkedText = (element, value) => {
    element.textContent = '';
    const pattern = /(\*\*([^*]+)\*\*|\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s<]+))/g;
    let cursor = 0;

    for (const match of value.matchAll(pattern)) {
        element.append(document.createTextNode(value.slice(cursor, match.index)));
        if (match[2]) {
            const strong = document.createElement('strong');
            strong.textContent = match[2];
            element.append(strong);
        } else {
            const link = document.createElement('a');
            link.href = match[4] || match[5];
            link.textContent = match[3] || match[5];
            link.className = 'font-semibold text-orbit-700 underline decoration-orbit-300 underline-offset-2 dark:text-orbit-300';
            element.append(link);
        }
        cursor = match.index + match[0].length;
    }
    element.append(document.createTextNode(value.slice(cursor)));
};

const makeMessage = (role, text = '') => {
    const article = document.createElement('article');
    article.dataset.aiMessage = role;
    article.className = `flex gap-3 ${role === 'user' ? 'justify-end' : 'justify-start'}`;

    if (role === 'assistant') {
        const avatar = document.createElement('div');
        avatar.className = 'mt-1 grid size-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-sky-400 to-indigo-600 text-sm text-white';
        avatar.textContent = '✦';
        article.append(avatar);
    }

    const wrap = document.createElement('div');
    wrap.className = 'max-w-[88%]';
    const body = document.createElement('div');
    body.dataset.aiBody = '';
    body.className = `whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm leading-6 ${role === 'user'
        ? 'rounded-br-md bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
        : 'rounded-bl-md bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'}`;
    safeLinkedText(body, text);
    wrap.append(body);

    if (role === 'assistant') {
        const meta = document.createElement('p');
        meta.dataset.aiMeta = '';
        meta.className = 'mt-1 hidden px-1 text-[11px] text-slate-400';
        wrap.append(meta);
    }

    article.append(wrap);

    return { article, body, meta: wrap.querySelector('[data-ai-meta]') };
};

const parseEvent = (block) => {
    let event = 'message';
    const data = [];
    for (const line of block.split('\n')) {
        if (line.startsWith('event:')) event = line.slice(6).trim();
        if (line.startsWith('data:')) data.push(line.slice(5).trimStart());
    }

    if (! data.length) return null;

    return { event, payload: JSON.parse(data.join('\n')) };
};

export function initAskAi() {
    const root = document.querySelector('[data-ask-ai]');
    if (! root) return;

    const form = root.querySelector('[data-ai-form]');
    const input = root.querySelector('[data-ai-input]');
    const submit = root.querySelector('[data-ai-submit]');
    const project = root.querySelector('[data-ai-project]');
    const messages = root.querySelector('[data-ai-messages]');
    const thread = root.querySelector('[data-ai-thread]');
    const empty = root.querySelector('[data-ai-empty]');
    const error = root.querySelector('[data-ai-error]');
    const title = root.querySelector('[data-ai-title]');
    let controller = null;
    let busy = false;

    const scrollDown = () => requestAnimationFrame(() => { messages.scrollTop = messages.scrollHeight; });
    const showError = (message) => {
        error.textContent = message;
        error.classList.remove('hidden');
    };
    const clearError = () => {
        error.textContent = '';
        error.classList.add('hidden');
    };
    const setBusy = (value) => {
        busy = value;
        input.disabled = value;
        submit.textContent = value ? '■' : '↑';
        submit.setAttribute('aria-label', value ? 'Stop answer' : 'Send message');
        messages.setAttribute('aria-busy', value ? 'true' : 'false');
    };
    const rememberConversation = (payload) => {
        root.dataset.conversation = payload.public_id;
        title.textContent = payload.title;
        root.querySelector('[data-ai-context]')?.classList.add('hidden');
        window.history.replaceState({}, '', payload.url);

        const history = root.querySelector('[data-ai-history]');
        if (! history || history.querySelector(`[data-conversation-link="${payload.public_id}"]`)) return;
        history.querySelector('[data-ai-empty-history]')?.remove();
        const link = document.createElement('a');
        link.href = payload.url;
        link.dataset.conversationLink = payload.public_id;
        link.className = 'block rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700';
        const linkTitle = document.createElement('span');
        linkTitle.className = 'block truncate text-sm font-semibold text-slate-800 dark:text-slate-100';
        linkTitle.textContent = payload.title;
        const detail = document.createElement('span');
        detail.className = 'mt-1 block truncate text-xs text-slate-500';
        detail.textContent = 'Just now';
        link.append(linkTitle, detail);
        history.prepend(link);
    };

    root.querySelectorAll('[data-ai-message="assistant"] [data-ai-body]:not([data-ai-rendered])').forEach((body) => safeLinkedText(body, body.textContent));
    root.querySelectorAll('[data-ai-suggestion]').forEach((button) => button.addEventListener('click', () => {
        input.value = button.dataset.aiSuggestion;
        form.requestSubmit();
    }));
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 144)}px`;
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && ! event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) {
            controller?.abort();

            return;
        }

        const prompt = input.value.trim();
        if (! prompt) return;

        clearError();
        empty.classList.add('hidden');
        empty.classList.remove('grid');
        const userMessage = makeMessage('user', prompt);
        const assistantMessage = makeMessage('assistant', 'Thinking…');
        assistantMessage.body.classList.add('animate-pulse');
        thread.append(userMessage.article, assistantMessage.article);
        input.value = '';
        input.style.height = 'auto';
        scrollDown();
        controller = new AbortController();
        setBusy(true);
        let answer = '';

        try {
            const response = await fetch(root.dataset.streamUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'text/event-stream',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    message: prompt,
                    conversation_public_id: root.dataset.conversation || null,
                    project_public_id: project?.value || null,
                }),
                signal: controller.signal,
            });

            if (! response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message ?? Object.values(payload.errors ?? {}).flat()[0] ?? `Request failed (${response.status}).`);
            }
            if (! response.body) throw new Error('Streaming is not supported by this browser.');

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let streamError = null;

            while (true) {
                const { value, done } = await reader.read();
                buffer = (buffer + decoder.decode(value ?? new Uint8Array(), { stream: ! done })).replaceAll('\r\n', '\n');
                let boundary;
                while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                    const parsed = parseEvent(buffer.slice(0, boundary));
                    buffer = buffer.slice(boundary + 2);
                    if (! parsed) continue;
                    if (parsed.event === 'conversation') rememberConversation(parsed.payload);
                    if (parsed.event === 'delta') {
                        if (! answer) assistantMessage.body.classList.remove('animate-pulse');
                        answer += parsed.payload.text ?? '';
                        safeLinkedText(assistantMessage.body, answer);
                        scrollDown();
                    }
                    if (parsed.event === 'done' && assistantMessage.meta) {
                        const tokens = (parsed.payload.input_tokens ?? 0) + (parsed.payload.output_tokens ?? 0);
                        assistantMessage.meta.textContent = `${parsed.payload.model}${tokens ? ` · ${tokens.toLocaleString()} tokens` : ''}`;
                        assistantMessage.meta.classList.remove('hidden');
                    }
                    if (parsed.event === 'error') streamError = parsed.payload.message;
                }
                if (done) break;
            }

            if (streamError) throw new Error(streamError);
            if (! answer) throw new Error('Ask AI returned no answer.');
        } catch (requestError) {
            assistantMessage.body.classList.remove('animate-pulse');
            if (! answer) assistantMessage.article.remove();
            showError(requestError.name === 'AbortError' ? 'Answer stopped.' : requestError.message);
        } finally {
            controller = null;
            setBusy(false);
            input.focus();
            scrollDown();
        }
    });

    scrollDown();
}
