@php
    $projectRequest = $projectRequest ?? null;
    $objectives = collect(old('objectives', $projectRequest?->objectives ?? []))
        ->map(fn ($objective, $index) => [
            'key' => 'objective-'.$index,
            'title' => $objective['title'] ?? '',
            'description' => $objective['description'] ?? '',
        ])
        ->take(6);
    $benefits = collect(old('benefits', $projectRequest?->benefits ?? []))
        ->map(fn ($benefit, $index) => ['key' => 'benefit-'.$index, 'value' => $benefit])
        ->take(4);
    $costItems = collect(old('cost_items', $projectRequest?->cost_items ?? []))
        ->map(fn ($costItem, $index) => ['key' => 'cost-item-'.$index, 'value' => $costItem])
        ->take(3);

    if ($objectives->isEmpty()) {
        $objectives->push(['key' => 'objective-0', 'title' => '', 'description' => '']);
    }

    if ($benefits->isEmpty()) {
        $benefits->push(['key' => 'benefit-0', 'value' => '']);
    }

    if ($costItems->isEmpty()) {
        $costItems->push(['key' => 'cost-item-0', 'value' => '']);
    }
@endphp

<div
    class="space-y-6"
    x-data="{
        objectives: {{ Illuminate\Support\Js::from($objectives->values()) }},
        benefits: {{ Illuminate\Support\Js::from($benefits->values()) }},
        costItems: {{ Illuminate\Support\Js::from($costItems->values()) }},
        errors: {{ Illuminate\Support\Js::from($errors->messages()) }},
        key(prefix) { return `${prefix}-${Date.now()}-${Math.random()}` },
    }"
>
<section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <h3 class="text-lg font-bold">Project overview</h3>
    <p class="mt-1 text-sm text-slate-500">Name the project and explain the problem it should solve.</p>

    <div class="mt-5 space-y-5">
        <div>
            <x-label for="title">Project name</x-label>
            <x-input id="title" name="title" value="{{ old('title', $projectRequest?->title) }}" placeholder="Enter the project name" required />
            <x-field-error name="title" />
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div>
                <x-label for="background">Background</x-label>
                <x-textarea id="background" name="background" rows="6" required
                    placeholder="Describe the job or activity that needs improvement.">{{ old('background', $projectRequest?->background) }}</x-textarea>
                <x-field-error name="background" />
            </div>
            <div>
                <x-label for="why_needed">Why is this needed?</x-label>
                <x-textarea id="why_needed" name="why_needed" rows="6" required
                    placeholder="Explain the pain point and why it needs to be solved.">{{ old('why_needed', $projectRequest?->why_needed) }}</x-textarea>
                <x-field-error name="why_needed" />
            </div>
        </div>
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-bold">Objectives</h3>
            <p class="mt-1 text-sm text-slate-500">Add up to six measurable outcomes. At least one complete objective is required.</p>
        </div>
        <button
            type="button"
            x-cloak
            x-show="objectives.length < 6"
            x-on:click="objectives.push({ key: key('objective'), title: '', description: '' }); errors = {}"
            class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-orbit-600 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-orbit-700 dark:bg-orbit-400 dark:text-slate-950 dark:hover:bg-orbit-300"
        >
            <x-icon name="plus" />
            Add objective
        </button>
    </div>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <template x-for="(objective, index) in objectives" :key="objective.key">
            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400" x-text="`Objective ${index + 1}`"></p>
                    <button
                        type="button"
                        x-cloak
                        x-show="objectives.length > 1"
                        x-on:click="objectives.splice(index, 1); errors = {}"
                        :aria-label="`Remove objective ${index + 1}`"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-200 dark:hover:bg-rose-900/70"
                    >
                        <x-icon name="trash" class="size-3.5" />
                        Remove
                    </button>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200" :for="`objectives_${index}_title`">Title</label>
                    <input
                        x-model="objective.title"
                        x-on:input="delete errors[`objectives.${index}.title`]"
                        :id="`objectives_${index}_title`"
                        :name="`objectives[${index}][title]`"
                        placeholder="What should improve?"
                        class="block min-h-11 w-full rounded-xl border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                    >
                    <p
                        x-show="errors[`objectives.${index}.title`]"
                        x-text="errors[`objectives.${index}.title`]?.[0]"
                        class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                    ></p>
                </div>
                <div class="mt-4">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200" :for="`objectives_${index}_description`">Expected result</label>
                    <textarea
                        x-model="objective.description"
                        x-on:input="delete errors[`objectives.${index}.description`]"
                        :id="`objectives_${index}_description`"
                        :name="`objectives[${index}][description]`"
                        rows="3"
                        placeholder="Describe a measurable result."
                        class="block min-h-28 w-full rounded-xl border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                    ></textarea>
                    <p
                        x-show="errors[`objectives.${index}.description`]"
                        x-text="errors[`objectives.${index}.description`]?.[0]"
                        class="mt-1 text-sm text-rose-600 dark:text-rose-400"
                    ></p>
                </div>
            </div>
        </template>
    </div>
    <x-field-error name="objectives" />
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <h3 class="text-lg font-bold">Project illustration</h3>
    <p class="mt-1 text-sm text-slate-500">Explain the process or layout, then compare the current and expected state.</p>

    <div class="mt-5">
        <x-label for="illustration">Illustration or process overview</x-label>
        <x-textarea id="illustration" name="illustration" rows="4" required
            placeholder="Describe the process flow, layout, or other context.">{{ old('illustration', $projectRequest?->illustration) }}</x-textarea>
        <x-field-error name="illustration" />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
            <x-label for="before_state">Before</x-label>
            <x-textarea id="before_state" name="before_state" rows="7" required class="mt-2"
                placeholder="Explain how the work or process runs today.">{{ old('before_state', $projectRequest?->before_state) }}</x-textarea>
            <x-field-error name="before_state" />
        </div>
        <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
            <x-label for="after_state">After</x-label>
            <x-textarea id="after_state" name="after_state" rows="7" required class="mt-2"
                placeholder="Explain how the improved process should work.">{{ old('after_state', $projectRequest?->after_state) }}</x-textarea>
            <x-field-error name="after_state" />
        </div>
    </div>
</section>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold">Benefits</h3>
                <p class="mt-1 text-sm text-slate-500">Add up to four tangible or intangible benefits.</p>
            </div>
            <button
                type="button"
                x-cloak
                x-show="benefits.length < 4"
                x-on:click="benefits.push({ key: key('benefit'), value: '' }); errors = {}"
                class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-orbit-600 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-orbit-700 dark:bg-orbit-400 dark:text-slate-950 dark:hover:bg-orbit-300"
            >
                <x-icon name="plus" />
                Add benefit
            </button>
        </div>

        <div class="mt-5 space-y-3">
            <template x-for="(benefit, index) in benefits" :key="benefit.key">
                <div class="flex items-start gap-3">
                    <span class="mt-2.5 grid size-6 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300" x-text="index + 1"></span>
                    <div class="min-w-0 flex-1">
                        <label :for="`benefits_${index}`" class="sr-only" x-text="`Benefit ${index + 1}`"></label>
                        <input
                            x-model="benefit.value"
                            x-on:input="delete errors[`benefits.${index}`]"
                            :id="`benefits_${index}`"
                            :name="`benefits[${index}]`"
                            placeholder="Expected benefit"
                            class="block min-h-11 w-full rounded-xl border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        >
                        <p x-show="errors[`benefits.${index}`]" x-text="errors[`benefits.${index}`]?.[0]" class="mt-1 text-sm text-rose-600 dark:text-rose-400"></p>
                    </div>
                    <button
                        type="button"
                        x-cloak
                        x-show="benefits.length > 1"
                        x-on:click="benefits.splice(index, 1); errors = {}"
                        :aria-label="`Remove benefit ${index + 1}`"
                        class="mt-1.5 inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-700 transition hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-200 dark:hover:bg-rose-900/70"
                    >
                        <x-icon name="trash" class="size-3.5" />
                    </button>
                </div>
            </template>
        </div>
        <x-field-error name="benefits" />
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold">Cost & ROI</h3>
                <p class="mt-1 text-sm text-slate-500">List the expected costs and explain the value returned.</p>
            </div>
            <button
                type="button"
                x-cloak
                x-show="costItems.length < 3"
                x-on:click="costItems.push({ key: key('cost-item'), value: '' }); errors = {}"
                class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-orbit-600 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-orbit-700 dark:bg-orbit-400 dark:text-slate-950 dark:hover:bg-orbit-300"
            >
                <x-icon name="plus" />
                Add cost item
            </button>
        </div>

        <div class="mt-5 space-y-3">
            <template x-for="(costItem, index) in costItems" :key="costItem.key">
                <div class="flex items-start gap-3">
                    <span class="mt-2.5 grid size-6 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300" x-text="index + 1"></span>
                    <div class="min-w-0 flex-1">
                        <label :for="`cost_items_${index}`" class="sr-only" x-text="`Cost item ${index + 1}`"></label>
                        <input
                            x-model="costItem.value"
                            x-on:input="delete errors[`cost_items.${index}`]"
                            :id="`cost_items_${index}`"
                            :name="`cost_items[${index}]`"
                            placeholder="Expected cost item"
                            class="block min-h-11 w-full rounded-xl border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                        >
                        <p x-show="errors[`cost_items.${index}`]" x-text="errors[`cost_items.${index}`]?.[0]" class="mt-1 text-sm text-rose-600 dark:text-rose-400"></p>
                    </div>
                    <button
                        type="button"
                        x-cloak
                        x-show="costItems.length > 1"
                        x-on:click="costItems.splice(index, 1); errors = {}"
                        :aria-label="`Remove cost item ${index + 1}`"
                        class="mt-1.5 inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-700 transition hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-200 dark:hover:bg-rose-900/70"
                    >
                        <x-icon name="trash" class="size-3.5" />
                    </button>
                </div>
            </template>
        </div>
        <x-field-error name="cost_items" />

        <div class="mt-5">
            <x-label for="roi">ROI information</x-label>
            <x-textarea id="roi" name="roi" rows="5" required
                placeholder="Explain the expected return or how success will be measured.">{{ old('roi', $projectRequest?->roi) }}</x-textarea>
            <x-field-error name="roi" />
        </div>
    </section>
</div>

<section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
    <x-label for="target_date">Preferred target date <span class="font-normal text-slate-400">(optional)</span></x-label>
    <x-input id="target_date" type="date" name="target_date" value="{{ old('target_date', $projectRequest?->target_date?->format('Y-m-d')) }}" class="mt-2 max-w-sm" />
    <x-field-error name="target_date" />
    <p class="mt-2 text-xs text-slate-500">This is a preference, not a delivery commitment. ITD schedules approved work against actual capacity.</p>
</section>
</div>
