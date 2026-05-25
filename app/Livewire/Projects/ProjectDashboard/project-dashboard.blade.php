@php
    $downloadablePages = $pages->filter(fn ($page) => trim((string) ($page->html_source ?? '')) !== '');
@endphp

<main class="min-h-screen bg-neutral-950 text-neutral-100">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <x-page-header
            :title="$project->name"
            eyebrow="Projects"
            :eyebrow-href="route('projects.index')"
            :subtitle="$project->description ?: 'Project dashboard'"
        >
            <x-slot:actions>
                <a
                    href="{{ route('builder.projects.download-html', $project) }}"
                    aria-disabled="{{ $downloadablePages->isEmpty() ? 'true' : 'false' }}"
                    class="inline-flex h-10 items-center rounded border bg-neutral-900 px-4 text-sm font-medium transition-colors {{ $downloadablePages->isNotEmpty() ? 'border-neutral-700 text-white hover:bg-neutral-800' : 'border-neutral-800 text-neutral-600' }}"
                >
                    Download project
                </a>
                <a href="{{ route('setup.llm') }}" wire:navigate class="inline-flex h-10 items-center rounded border border-neutral-700 bg-neutral-900 px-4 text-sm font-medium text-white transition-colors hover:bg-neutral-800">
                    LLM setup
                </a>
            </x-slot:actions>
        </x-page-header>

        <section class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <aside>
                <form wire:submit="createPage" class="rounded-lg border border-neutral-800 bg-neutral-900 p-3 shadow-2xl shadow-black/20">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold text-white">New page</h2>
                    </div>

                    <div class="mt-3 flex flex-col gap-2.5">
                        <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                            Name
                            <input wire:model="name" class="h-9 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none placeholder:text-neutral-600 focus:border-cyan-400" maxlength="160" placeholder="Landing page">
                            @error('name') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                        </label>

                        <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                            Prompt
                            <textarea wire:model="prompt" rows="4" class="resize-none rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-white outline-none placeholder:text-neutral-600 focus:border-cyan-400" placeholder="Audience, layout, sections, tone"></textarea>
                            @error('prompt') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                        </label>

                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-cyan-400 px-3 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">
                            Create page
                        </button>
                    </div>
                </form>
            </aside>

            <section class="min-w-0 rounded-lg border border-neutral-800 bg-neutral-900 shadow-2xl shadow-black/20">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 px-4 py-3 sm:px-5">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Pages</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">{{ $pages->count() }} {{ $pages->count() === 1 ? 'page' : 'pages' }} in {{ $project->name }}</p>
                    </div>
                </div>

                <div class="divide-y divide-neutral-800">
                    @forelse ($pages as $page)
                        <article wire:key="page-{{ $page->id }}" class="group px-4 py-3 transition hover:bg-neutral-800/55 sm:px-5">
                            @if ($editingPageId === $page->id)
                                <form wire:submit="renamePage" class="flex flex-col gap-2.5">
                                    <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                                        Name
                                        <input wire:model="editingPageName" class="h-9 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400" maxlength="160">
                                        @error('editingPageName') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                    </label>

                                    <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                                        Prompt
                                        <textarea wire:model="editingPagePrompt" rows="3" class="resize-none rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400"></textarea>
                                        @error('editingPagePrompt') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                    </label>

                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="inline-flex h-8 items-center rounded-md bg-cyan-400 px-3 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">Save</button>
                                        <button type="button" wire:click="cancelRenamingPage" class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Cancel</button>
                                    </div>
                                </form>
                            @else
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <a href="{{ route('builder.workspace', [$project, $page]) }}" wire:navigate class="min-w-0 flex-1">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-neutral-700 bg-neutral-950 text-sm font-semibold text-cyan-200">
                                                {{ str($page->name)->substr(0, 1)->upper() }}
                                            </span>
                                            <div class="min-w-0">
                                                <h3 class="truncate text-base font-semibold text-white">{{ $page->name }}</h3>
                                                <p class="mt-1 line-clamp-2 text-sm text-neutral-400">{{ $page->prompt ?: 'No prompt' }}</p>
                                            </div>
                                        </div>
                                    </a>

                                    <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                                        <a href="{{ route('builder.workspace', [$project, $page]) }}" wire:navigate class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-cyan-500 hover:text-cyan-200">Open</a>
                                        @if (trim((string) ($page->html_source ?? '')) !== '')
                                            <a href="{{ route('builder.pages.download-html', [$project, $page]) }}" class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-emerald-500 hover:text-emerald-200">Download</a>
                                            <button type="button" wire:click="openGenerateSite('{{ $page->id }}')" wire:loading.attr="disabled" class="inline-flex h-8 items-center rounded-md border border-cyan-900/70 px-3 text-sm font-semibold text-cyan-200 hover:border-cyan-500">
                                                Generate site
                                            </button>
                                        @else
                                            <button type="button" disabled class="inline-flex h-8 cursor-not-allowed items-center rounded-md border border-neutral-800 px-3 text-sm font-semibold text-neutral-600">Download</button>
                                            <button type="button" disabled class="inline-flex h-8 cursor-not-allowed items-center rounded-md border border-neutral-800 px-3 text-sm font-semibold text-neutral-600">Generate site</button>
                                        @endif
                                        <button type="button" wire:click="startRenamingPage('{{ $page->id }}')" class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Rename</button>
                                        <button
                                            type="button"
                                            wire:confirm="Delete this page?"
                                            wire:click="deletePage('{{ $page->id }}')"
                                            class="inline-flex h-8 items-center rounded-md border border-rose-900/70 px-3 text-sm font-semibold text-rose-200 hover:border-rose-500"
                                        >Delete</button>
                                    </div>
                                </div>

                                @php
                                    $siteRuns = $page->siteGenerationRuns ?? collect();
                                    $completedSiteRuns = $siteRuns->where('status', 'completed')->filter(fn ($run) => filled($run->zip_path));
                                    $activeSiteRun = $siteRuns->first(fn ($run) => in_array($run->status, ['queued', 'running'], true));
                                    $failedSiteRun = $siteRuns->first(fn ($run) => $run->status === 'failed');
                                @endphp

                                @if ($completedSiteRuns->isNotEmpty() || $activeSiteRun || $failedSiteRun)
                                    <div class="mt-3 flex flex-wrap items-center gap-2 pl-11 text-xs">
                                        @if ($activeSiteRun)
                                            <span class="rounded border border-cyan-900/70 bg-cyan-950/40 px-2 py-1 font-medium text-cyan-200">
                                                Site run {{ $activeSiteRun->status }}
                                            </span>
                                        @endif

                                        @if ($failedSiteRun)
                                            <span class="rounded border border-rose-900/70 bg-rose-950/30 px-2 py-1 font-medium text-rose-200">
                                                Latest site run failed
                                            </span>
                                        @endif

                                        @if ($completedSiteRuns->isNotEmpty())
                                            <details class="relative">
                                                <summary class="cursor-pointer rounded border border-emerald-900/70 bg-emerald-950/30 px-2 py-1 font-medium text-emerald-200 hover:border-emerald-500">
                                                    {{ $completedSiteRuns->count() === 1 ? 'Site zip' : $completedSiteRuns->count().' site zips' }}
                                                </summary>
                                                <div class="absolute right-0 z-20 mt-2 w-72 rounded-md border border-neutral-700 bg-neutral-950 p-2 shadow-2xl">
                                                    @foreach ($completedSiteRuns as $siteRun)
                                                        <a
                                                            href="{{ route('builder.pages.site-runs.download', [$project, $page, $siteRun]) }}"
                                                            class="block rounded px-2 py-2 text-neutral-200 hover:bg-neutral-800 hover:text-white"
                                                        >
                                                            <span class="block truncate font-semibold">{{ $siteRun->zip_filename ?: 'site.zip' }}</span>
                                                            <span class="mt-0.5 block text-neutral-500">{{ optional($siteRun->completed_at)->diffForHumans() ?: 'Completed' }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </article>
                    @empty
                        <div class="px-4 py-14 text-center sm:px-5">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-md border border-neutral-800 bg-neutral-950 text-lg font-semibold text-cyan-200">T</div>
                            <h3 class="mt-4 text-base font-semibold text-white">No pages yet</h3>
                            <p class="mx-auto mt-1 max-w-sm text-sm text-neutral-400">Create a page to open the builder workspace.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </section>
    </div>

    @if ($sitePlannerOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6">
            <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg border border-neutral-700 bg-neutral-950 shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-neutral-800 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-white">Generate site</h2>
                        <p class="mt-1 text-sm text-neutral-400">Review the pages before queueing generation.</p>
                    </div>
                    <button type="button" wire:click="closeGenerateSite" class="rounded border border-neutral-700 px-2 py-1 text-sm font-semibold text-neutral-300 hover:border-neutral-500">Close</button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                            Provider
                            <select wire:model="siteProvider" wire:change="refreshSiteModel" class="h-9 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                                @foreach ($siteProviderOptions as $providerOption)
                                    <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                            Model
                            <select wire:model="siteModel" class="h-9 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                                @foreach ($siteModelOptions as $modelOption)
                                    <option value="{{ $modelOption['id'] }}">{{ $modelOption['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="planSitePages" wire:loading.attr="disabled" class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-cyan-500 hover:text-cyan-200">
                            Recalculate
                        </button>
                        <span wire:loading wire:target="openGenerateSite,planSitePages" class="text-sm text-cyan-200">Planning pages...</span>
                    </div>

                    @if ($sitePlanningError)
                        <div class="rounded-md border border-rose-900/70 bg-rose-950/30 px-3 py-2 text-sm text-rose-100">{{ $sitePlanningError }}</div>
                    @endif

                    @if ($sitePlannerSummary !== '')
                        <p class="rounded-md border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-300">{{ $sitePlannerSummary }}</p>
                    @endif

                    <div class="divide-y divide-neutral-800 rounded-lg border border-neutral-800">
                        @forelse ($siteProposals as $index => $proposal)
                            <div wire:key="site-proposal-{{ $index }}" class="flex gap-3 px-3 py-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold text-white">{{ $proposal['name'] }}</h3>
                                        <span class="rounded bg-neutral-800 px-1.5 py-0.5 text-xs text-neutral-300">{{ $proposal['slug'] }}.html</span>
                                    </div>
                                    <p class="mt-1 text-sm text-neutral-400">{{ $proposal['brief'] }}</p>
                                    <p class="mt-1 text-xs text-neutral-500">{{ $proposal['reason'] }}</p>
                                </div>
                                <button type="button" wire:click="removeSiteProposal({{ $index }})" class="h-8 rounded-md border border-neutral-700 px-2 text-sm font-semibold text-neutral-300 hover:border-rose-500 hover:text-rose-200">
                                    Remove
                                </button>
                            </div>
                        @empty
                            <div class="px-3 py-8 text-center text-sm text-neutral-500">No proposed pages yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-800 px-5 py-4">
                    <button type="button" wire:click="closeGenerateSite" class="inline-flex h-9 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Cancel</button>
                    <button type="button" wire:click="proceedGenerateSite" wire:loading.attr="disabled" class="inline-flex h-9 items-center rounded-md bg-cyan-400 px-3 text-sm font-semibold text-neutral-950 hover:bg-cyan-300 disabled:cursor-wait disabled:bg-cyan-700">
                        Proceed
                    </button>
                </div>
            </section>
        </div>
    @endif
</main>
