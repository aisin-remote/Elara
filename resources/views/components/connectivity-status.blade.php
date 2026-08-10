{{-- `hidden` is toggled by initConnectivity(); keep it as the first display class. --}}
<div data-offline-banner class="fixed inset-x-0 top-4 z-[90] hidden px-4" role="status">
    <span class="mx-auto flex w-fit items-center gap-2.5 rounded-full bg-slate-950 py-2 pl-3 pr-4 text-sm font-semibold text-white shadow-2xl ring-1 ring-white/10">
        <span class="grid size-6 place-items-center rounded-full bg-amber-400/20 text-amber-300"><x-icon name="alert" class="size-3.5" /></span>
        You are offline. Changes are paused until your connection returns.
    </span>
</div>
