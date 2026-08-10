import { apiRequest } from './api';

export function initKanban() {
    const board = document.querySelector('[data-kanban]');

    if (! board) return;

    let dragged;
    let origin;
    let originNext;

    const refreshCounts = () => board.querySelectorAll('[data-status-column]').forEach((column) => {
        const badge = column.querySelector('[data-task-count]');
        if (badge) badge.textContent = column.querySelectorAll('[data-task-card]').length;
    });

    board.addEventListener('dragstart', (event) => {
        dragged = event.target.closest('[data-task-card]');
        if (! dragged) return;
        origin = dragged.parentElement;
        originNext = dragged.nextElementSibling;
        dragged.classList.add('opacity-50', 'rotate-1');
        event.dataTransfer.effectAllowed = 'move';
    });

    board.addEventListener('dragover', (event) => {
        const list = event.target.closest('[data-task-list]');
        if (! list || ! dragged) return;
        event.preventDefault();
        const after = [...list.querySelectorAll('[data-task-card]:not(.opacity-50)')]
            .find((card) => event.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2);
        list.insertBefore(dragged, after || null);
    });

    board.addEventListener('drop', async (event) => {
        const list = event.target.closest('[data-task-list]');
        if (! list || ! dragged) return;
        event.preventDefault();
        const card = dragged;
        // Claim the card before awaiting: dragend fires while the request is still in
        // flight, and it would otherwise put the card back where the drag started.
        dragged = null;
        const previous = card.previousElementSibling;
        const next = card.nextElementSibling;
        const status = list.closest('[data-status-column]');

        if (card.dataset.blocked === 'true'
            && status.dataset.statusCategory === 'in_progress'
            && ! window.confirm('This task still has unfinished dependencies. Move it to In Progress anyway?')) {
            origin.insertBefore(card, originNext);
            card.classList.remove('opacity-50', 'rotate-1');
            refreshCounts();
            return;
        }

        try {
            const payload = await apiRequest(card.dataset.moveUrl, {
                method: 'POST',
                body: JSON.stringify({
                    status_public_id: status.dataset.statusId,
                    before_task_public_id: previous?.dataset.taskId || null,
                    after_task_public_id: next?.dataset.taskId || null,
                    version: Number(card.dataset.version),
                    operation_id: crypto.randomUUID(),
                }),
            });
            card.dataset.version = payload.data.version;
            window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: payload.message }));
        } catch (error) {
            origin.insertBefore(card, originNext);
            const message = error.response?.status === 409
                ? 'Task changed elsewhere. Refresh before moving it.'
                : error.message;
            window.dispatchEvent(new CustomEvent('orbitra-toast', { detail: message }));
        } finally {
            card.classList.remove('opacity-50', 'rotate-1');
            refreshCounts();
        }
    });

    board.addEventListener('dragend', () => {
        if (! dragged) return;
        origin.insertBefore(dragged, originNext);
        dragged.classList.remove('opacity-50', 'rotate-1');
        dragged = null;
    });
}
