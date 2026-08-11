@if ($request->background)
    <div class="space-y-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-bold">Project overview</h3>
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Background</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->background }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Why needed</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->why_needed }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-bold">Objectives</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($request->objectives ?? [] as $objective)
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                        <p class="font-semibold">{{ $objective['title'] }}</p>
                        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $objective['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-bold">Project illustration</h3>
            <p class="mt-3 whitespace-pre-line text-sm leading-6">{{ $request->illustration }}</p>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Before</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->before_state }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">After</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->after_state }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-bold">Benefits</h3>
                <ol class="mt-4 space-y-3">
                    @foreach ($request->benefits ?? [] as $benefit)
                        <li class="flex gap-3 text-sm leading-6">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ $loop->iteration }}</span>
                            <span>{{ $benefit }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-lg font-bold">Cost & ROI</h3>
                <ol class="mt-4 space-y-2 text-sm leading-6">
                    @foreach ($request->cost_items ?? [] as $costItem)
                        <li>{{ $loop->iteration }}. {{ $costItem }}</li>
                    @endforeach
                </ol>
                <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">ROI information</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $request->roi }}</p>
                </div>
            </section>
        </div>
    </div>
@else
    <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        @foreach ([['Benefit', $request->benefit], ['Concept', $request->concept], ['Business process', $request->business_process], ['Flow', $request->flow]] as $index => [$label, $value])
            <div @class(['border-t border-slate-100 pt-5 dark:border-slate-800' => $index > 0])>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $value }}</p>
            </div>
        @endforeach
    </section>
@endif
