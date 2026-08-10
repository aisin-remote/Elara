<div x-data="toastStack" x-on:orbitra-toast.window="push($event.detail)"
    {{ $attributes->class(['pointer-events-none fixed bottom-5 right-5 z-[70] flex w-[min(92vw,384px)] flex-col gap-3']) }}
    role="region" aria-label="Notifications">
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-x-6 scale-95 opacity-0"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-end="translate-x-4 opacity-0"
            class="pointer-events-auto relative overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-950/10 ring-1 ring-slate-950/5 dark:bg-slate-900 dark:ring-white/10"
            role="status" aria-live="polite">
            <div class="flex items-start gap-3 p-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-full" :class="chip(toast.variant)">
                    <span x-show="toast.variant === 'success'"><x-icon name="check" class="size-5" /></span>
                    <span x-show="toast.variant === 'error' || toast.variant === 'warning'"><x-icon name="alert" class="size-5" /></span>
                    <span x-show="! ['success', 'error', 'warning'].includes(toast.variant)"><x-icon name="info" class="size-5" /></span>
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="text-sm font-bold" x-show="toast.title" x-text="toast.title"></p>
                    <p class="text-sm leading-6 text-slate-600 dark:text-slate-300" :class="toast.title ? 'mt-0.5' : ''" x-text="toast.message"></p>
                </div>
                <button type="button" x-on:click="dismiss(toast.id)" class="-mr-1 -mt-1 grid size-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200" aria-label="Dismiss notification"><x-icon name="close" /></button>
            </div>
            <span class="absolute inset-x-0 bottom-0 h-1 origin-left toast-progress" :class="bar(toast.variant)" :style="`animation-duration: ${toast.duration}ms`" aria-hidden="true"></span>
        </div>
    </template>
</div>
