@php
    $downloadablePages = $pages->filter(fn ($page) => trim((string) ($page->html_source ?? '')) !== '');
    $generatedCount = $downloadablePages->count();
    $draftCount = $pages->count() - $generatedCount;
    $latestPage = $pages->first();

    $hasHtml = fn ($page) => trim((string) ($page->html_source ?? '')) !== '';
    $statusMeta = function ($page) use ($hasHtml) {
        $s = (string) $page->status;
        return match (true) {
            in_array($s, ['generating', 'queued', 'running'], true) => ['Generating', 'border-cyan-300/20 bg-cyan-300/10 text-cyan-200'],
            in_array($s, ['error', 'failed'], true) => ['Error', 'border-rose-500/25 bg-rose-500/10 text-rose-300'],
            $hasHtml($page) => ['Live', 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300'],
            default => ['Draft', 'border-blue-400/20 bg-blue-400/10 text-blue-300'],
        };
    };

    $thumbs = [
        ['grad' => 'from-slate-900 via-blue-950 to-cyan-950', 'ring' => 'border-cyan-300/15', 'bar' => 'bg-cyan-300/50', 'avatar' => 'bg-cyan-500/15 text-cyan-200 ring-cyan-300/20'],
        ['grad' => 'from-[#101010] via-[#181818] to-emerald-950/60', 'ring' => 'border-white/10', 'bar' => 'bg-emerald-300/40', 'avatar' => 'bg-emerald-500/15 text-emerald-200 ring-emerald-300/20'],
        ['grad' => 'from-violet-950/60 via-[#161616] to-[#090909]', 'ring' => 'border-violet-300/15', 'bar' => 'bg-violet-300/45', 'avatar' => 'bg-violet-500/15 text-violet-200 ring-violet-300/20'],
        ['grad' => 'from-orange-950/50 via-[#151515] to-[#090909]', 'ring' => 'border-orange-300/15', 'bar' => 'bg-orange-300/45', 'avatar' => 'bg-orange-500/15 text-orange-200 ring-orange-300/20'],
        ['grad' => 'from-pink-950/50 via-[#151515] to-[#090909]', 'ring' => 'border-pink-300/15', 'bar' => 'bg-pink-300/45', 'avatar' => 'bg-pink-500/15 text-pink-200 ring-pink-300/20'],
        ['grad' => 'from-blue-950/60 via-[#141414] to-[#090909]', 'ring' => 'border-blue-300/15', 'bar' => 'bg-blue-300/45', 'avatar' => 'bg-blue-500/15 text-blue-200 ring-blue-300/20'],
    ];
@endphp

<x-app-shell active="projects" x-data="{ newPage: false, q: '', filter: 'all' }">
    <x-slot:breadcrumb>
        <a href="{{ route('projects.index') }}" wire:navigate class="transition hover:text-zinc-300">Projects</a>
        <span class="text-zinc-700">/</span>
        <span class="max-w-[12rem] truncate text-zinc-300">{{ $project->name }}</span>
        <span class="text-zinc-700">/</span>
        <span>Pages</span>
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a
            href="{{ route('builder.projects.download-html', $project) }}"
            @class([
                'hidden rounded-lg border px-3 py-1.5 text-xs font-semibold transition sm:inline-flex',
                'border-white/10 bg-[#0b0b0b] text-zinc-300 hover:border-white/20 hover:text-white' => $downloadablePages->isNotEmpty(),
                'pointer-events-none border-white/5 text-zinc-600' => $downloadablePages->isEmpty(),
            ])
        >Download project</a>
        <button type="button" @click="newPage = true" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-500 px-3 py-1.5 text-xs font-bold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="h-3.5 w-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New page
        </button>
    </x-slot:actions>

    {{-- Contextual sidebar --}}
    <x-slot:sidebar>
        <div class="border-b border-white/10 p-4">
            <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Project</p>
            <div class="rounded-xl border border-white/10 bg-[#090909] p-3 shadow-inner shadow-black/40">
                <p class="truncate text-sm font-semibold text-white">{{ $project->name }}</p>
                <p class="mt-0.5 line-clamp-2 text-xs text-zinc-500">{{ $project->description ?: 'No description' }}</p>
            </div>
        </div>

        <div class="border-b border-white/10 p-4">
            <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Workspace</p>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between rounded-lg bg-white/[0.06] px-3 py-2 text-sm font-medium text-white ring-1 ring-white/10">
                    <span>Pages</span><span class="text-xs text-zinc-400">{{ $pages->count() }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-zinc-400">
                    <span>Generated</span><span class="text-xs text-zinc-500">{{ $generatedCount }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-zinc-400">
                    <span>Drafts</span><span class="text-xs text-zinc-500">{{ $draftCount }}</span>
                </div>
            </div>
        </div>

        <div class="flex-1 p-4">
            <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Project brief</p>
            <div class="rounded-xl border border-white/10 bg-[#0a0a0a] p-3">
                <p class="text-sm leading-6 text-zinc-300">{{ $project->description ?: 'Add a description to keep your generation prompts on-brand.' }}</p>
            </div>
        </div>

        <div class="space-y-2 border-t border-white/10 p-4">
            <button type="button" @click="newPage = true" class="w-full rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition hover:bg-cyan-300">New page</button>
            @if ($latestPage)
                <a href="{{ route('builder.workspace', [$project, $latestPage]) }}" wire:navigate class="block w-full rounded-xl border border-white/10 bg-black/30 px-4 py-2.5 text-center text-sm font-semibold text-zinc-300 transition hover:border-white/20 hover:text-white">Open editor</a>
            @endif
            <a href="{{ route('setup.llm') }}" wire:navigate class="block w-full rounded-xl border border-white/10 bg-black/30 px-4 py-2.5 text-center text-sm font-semibold text-zinc-300 transition hover:border-white/20 hover:text-white">LLM setup</a>
        </div>
    </x-slot:sidebar>

    {{-- Overview --}}
    <section class="border-b border-white/10 bg-[#111111]/70">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $project->name }}</h1>
                <span class="w-fit rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1 text-xs font-semibold text-cyan-200">{{ $project->id }}</span>
            </div>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-400">{{ $project->description ?: 'Manage generated pages, jump into the editor, and assemble a full site from a single source page.' }}</p>

            <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                    <p class="text-xs font-medium text-zinc-500">Pages</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $pages->count() }}</p>
                    <p class="mt-1 text-xs text-zinc-500">In this project</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                    <p class="text-xs font-medium text-zinc-500">Generated</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $generatedCount }}</p>
                    <p class="mt-1 text-xs text-emerald-300">Ready to download</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                    <p class="text-xs font-medium text-zinc-500">Drafts</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $draftCount }}</p>
                    <p class="mt-1 text-xs text-zinc-500">Awaiting generation</p>
                </div>
                <div class="rounded-2xl border border-cyan-300/20 bg-cyan-300/[0.06] p-4">
                    <p class="text-xs font-medium text-cyan-200/70">Realtime</p>
                    <p class="mt-2 text-2xl font-bold text-white">Connected</p>
                    <p class="mt-1 text-xs text-cyan-200">Waiting for edits</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Controls --}}
    <section class="bg-[#0b0b0b]">
        <div class="px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-[#161616] p-3 shadow-2xl shadow-black/20 md:flex-row md:items-center md:justify-between">
                <label class="relative flex min-w-0 flex-1 items-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3 h-4 w-4 text-zinc-500"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" x-model="q" placeholder="Search pages by name or prompt..." class="h-11 w-full rounded-xl border border-white/10 bg-[#080808] pl-9 pr-4 text-sm text-zinc-200 outline-none transition placeholder:text-zinc-600 focus:border-cyan-300/50 focus:ring-4 focus:ring-cyan-300/10">
                </label>
                <div class="flex flex-wrap items-center gap-2">
                    <template x-for="tab in [{k:'all',l:'All pages'},{k:'generated',l:'Generated'},{k:'draft',l:'Drafts'}]" :key="tab.k">
                        <button type="button" @click="filter = tab.k" x-text="tab.l"
                            :class="filter === tab.k ? 'rounded-xl bg-white/[0.08] px-3 py-2 text-xs font-semibold text-white ring-1 ring-white/10' : 'rounded-xl px-3 py-2 text-xs font-semibold text-zinc-400 transition hover:bg-white/[0.05] hover:text-zinc-100'"></button>
                    </template>
                </div>
            </div>
        </div>
    </section>

    {{-- Pages grid --}}
    <section class="bg-[#0b0b0b]">
        <div class="px-4 pb-12 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($pages as $i => $page)
                    @php
                        $t = $thumbs[$i % count($thumbs)];
                        [$badgeLabel, $badgeClass] = $statusMeta($page);
                        $pageHasHtml = $hasHtml($page);
                        $cardStatus = $pageHasHtml ? 'generated' : 'draft';

                        $siteRuns = $page->siteGenerationRuns ?? collect();
                        $completedSiteRuns = $siteRuns->where('status', 'completed')->filter(fn ($run) => filled($run->zip_path));
                        $activeSiteRun = $siteRuns->first(fn ($run) => in_array($run->status, ['queued', 'running'], true));
                        $failedSiteRun = $siteRuns->first(fn ($run) => $run->status === 'failed');
                    @endphp

                    <article
                        wire:key="page-{{ $page->id }}"
                        data-search="{{ str(($page->name.' '.$page->prompt))->lower() }}"
                        data-status="{{ $cardStatus }}"
                        x-show="(q === '' || $el.dataset.search.includes(q.toLowerCase())) && (filter === 'all' || filter === $el.dataset.status)"
                        class="group relative flex flex-col rounded-2xl border border-white/10 bg-[#181818] shadow-xl shadow-black/20 transition hover:-translate-y-0.5 hover:border-cyan-300/30"
                    >
                        @if ($editingPageId === $page->id)
                            <form wire:submit="renamePage" class="flex flex-col gap-2.5 p-4">
                                <label class="flex flex-col gap-1 text-xs font-medium text-zinc-400">
                                    Name
                                    <input wire:model="editingPageName" class="h-9 rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none focus:border-cyan-300/50" maxlength="160">
                                    @error('editingPageName') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                </label>
                                <label class="flex flex-col gap-1 text-xs font-medium text-zinc-400">
                                    Prompt
                                    <textarea wire:model="editingPagePrompt" rows="3" class="resize-none rounded-lg border border-white/10 bg-[#0a0a0a] px-3 py-2 text-sm text-white outline-none focus:border-cyan-300/50"></textarea>
                                    @error('editingPagePrompt') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="inline-flex h-8 items-center rounded-lg bg-cyan-400 px-3 text-xs font-bold text-slate-950 hover:bg-cyan-300">Save</button>
                                    <button type="button" wire:click="cancelRenamingPage" class="inline-flex h-8 items-center rounded-lg border border-white/10 px-3 text-xs font-semibold text-zinc-300 hover:border-white/25">Cancel</button>
                                </div>
                            </form>
                        @else
                            <a href="{{ route('builder.workspace', [$project, $page]) }}" wire:navigate class="block rounded-t-2xl border-b border-white/10 bg-[#0b0b0b] p-3">
                                <div class="h-28 rounded-xl border {{ $t['ring'] }} bg-gradient-to-br {{ $t['grad'] }} p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="h-3 w-20 rounded-full {{ $t['bar'] }}"></div>
                                        <div class="h-5 w-12 rounded-full bg-white/10"></div>
                                    </div>
                                    <div class="mt-4 h-3.5 w-28 rounded bg-white/60"></div>
                                    <div class="mt-2.5 h-2 w-40 rounded bg-white/20"></div>
                                    <div class="mt-4 grid grid-cols-3 gap-2">
                                        <div class="h-8 rounded-lg bg-white/10"></div>
                                        <div class="h-8 rounded-lg bg-white/10"></div>
                                        <div class="h-8 rounded-lg bg-white/10"></div>
                                    </div>
                                </div>
                            </a>

                            <div class="flex flex-1 flex-col p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <a href="{{ route('builder.workspace', [$project, $page]) }}" wire:navigate class="flex min-w-0 gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold ring-1 {{ $t['avatar'] }}">{{ str($page->name)->substr(0, 1)->upper() }}</span>
                                        <div class="min-w-0">
                                            <h2 class="truncate font-semibold text-white">{{ $page->name }}</h2>
                                            <p class="mt-1 line-clamp-2 text-sm leading-5 text-zinc-400">{{ $page->prompt ?: 'No prompt' }}</p>
                                        </div>
                                    </a>
                                    <span class="shrink-0 rounded-full border px-2 py-1 text-[10px] font-bold uppercase tracking-wide {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                </div>

                                {{-- site run status --}}
                                @if ($completedSiteRuns->isNotEmpty() || $activeSiteRun || $failedSiteRun)
                                    <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                                        @if ($activeSiteRun)
                                            <span class="rounded-md border border-cyan-900/70 bg-cyan-950/40 px-2 py-1 font-medium text-cyan-200">Site run {{ $activeSiteRun->status }}</span>
                                        @endif
                                        @if ($failedSiteRun)
                                            <span class="rounded-md border border-rose-900/70 bg-rose-950/30 px-2 py-1 font-medium text-rose-200">Latest site run failed</span>
                                        @endif
                                        @if ($completedSiteRuns->isNotEmpty())
                                            <details class="relative">
                                                <summary class="cursor-pointer list-none rounded-md border border-emerald-900/70 bg-emerald-950/30 px-2 py-1 font-medium text-emerald-200 hover:border-emerald-500">
                                                    {{ $completedSiteRuns->count() === 1 ? 'Site zip' : $completedSiteRuns->count().' site zips' }}
                                                </summary>
                                                <div class="absolute left-0 z-20 mt-2 w-72 rounded-xl border border-white/10 bg-[#0a0a0a] p-2 shadow-2xl shadow-black/50">
                                                    @foreach ($completedSiteRuns as $siteRun)
                                                        <a href="{{ route('builder.pages.site-runs.download', [$project, $page, $siteRun]) }}" class="block rounded-lg px-2 py-2 text-zinc-200 hover:bg-white/5 hover:text-white">
                                                            <span class="block truncate font-semibold">{{ $siteRun->zip_filename ?: 'site.zip' }}</span>
                                                            <span class="mt-0.5 block text-zinc-500">{{ optional($siteRun->completed_at)->diffForHumans() ?: 'Completed' }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-3 text-xs text-zinc-500">
                                    <span>Updated {{ $page->updated_at?->diffForHumans() ?? 'recently' }}</span>
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('builder.workspace', [$project, $page]) }}" wire:navigate class="rounded-lg border border-white/10 px-2.5 py-1 font-semibold text-zinc-200 transition hover:border-cyan-400/40 hover:text-cyan-200">Open</a>
                                        <div class="relative" x-data="{ menu: false }" @keydown.escape.window="menu = false">
                                            <button type="button" @click="menu = !menu" class="flex h-7 w-7 items-center justify-center rounded-lg border border-white/10 font-semibold text-zinc-300 transition hover:border-white/25" aria-label="More actions">⋯</button>
                                            <div x-show="menu" x-cloak x-transition.opacity @click.outside="menu = false" class="absolute right-0 z-30 mt-2 w-48 overflow-hidden rounded-xl border border-white/10 bg-[#141414] p-1 shadow-2xl shadow-black/50">
                                                @if ($pageHasHtml)
                                                    <a href="{{ route('builder.pages.download-html', [$project, $page]) }}" class="block rounded-lg px-3 py-2 text-left text-xs font-semibold text-zinc-200 transition hover:bg-white/5 hover:text-emerald-200">Download HTML</a>
                                                    <button type="button" wire:click="openGenerateSite('{{ $page->id }}')" @click="menu = false" wire:loading.attr="disabled" class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-zinc-200 transition hover:bg-white/5 hover:text-cyan-200">Generate site</button>
                                                @else
                                                    <span class="block cursor-not-allowed rounded-lg px-3 py-2 text-left text-xs font-semibold text-zinc-600">Download HTML</span>
                                                    <span class="block cursor-not-allowed rounded-lg px-3 py-2 text-left text-xs font-semibold text-zinc-600">Generate site</span>
                                                @endif
                                                <button type="button" wire:click="startRenamingPage('{{ $page->id }}')" @click="menu = false" class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-zinc-200 transition hover:bg-white/5">Rename</button>
                                                <button type="button" wire:confirm="Delete this page?" wire:click="deletePage('{{ $page->id }}')" class="block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-rose-300 transition hover:bg-rose-500/10">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                @endforelse

                {{-- Create card --}}
                <button type="button" @click="newPage = true" class="group flex min-h-[19rem] flex-col items-center justify-center rounded-2xl border border-dashed border-white/15 bg-[#141414] p-6 text-center shadow-xl shadow-black/20 transition hover:border-cyan-300/40 hover:bg-[#171717]">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl border border-cyan-300/25 bg-cyan-300/10 text-2xl font-light text-cyan-200">＋</span>
                    <span class="mt-4 font-semibold text-white">Generate another page</span>
                    <span class="mt-2 max-w-xs text-sm leading-6 text-zinc-500">Start from a prompt and open the builder workspace to refine it.</span>
                    <span class="mt-5 rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition group-hover:bg-cyan-300">Create page</span>
                </button>
            </div>

            @if ($pages->isEmpty())
                <p class="mt-6 text-center text-sm text-zinc-500">No pages yet — create a page to open the builder workspace.</p>
            @endif
        </div>
    </section>

    {{-- New page modal --}}
    <div x-show="newPage" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 px-4 py-10 backdrop-blur-sm" @keydown.escape.window="newPage = false">
        <div @click.outside="newPage = false" class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#161616] shadow-2xl shadow-black/50">
            <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-white">New page</h2>
                    <p class="mt-1 text-sm text-zinc-500">Describe the page and we'll open the builder.</p>
                </div>
                <button type="button" @click="newPage = false" class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-zinc-400 transition hover:border-white/25 hover:text-white">✕</button>
            </div>
            <form wire:submit="createPage" class="flex flex-col gap-3 px-5 py-4">
                <label class="flex flex-col gap-1 text-sm font-medium text-zinc-300">
                    Name
                    <input wire:model="name" class="h-10 rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-cyan-300/50 focus:ring-4 focus:ring-cyan-300/10" maxlength="160" placeholder="Landing page">
                    @error('name') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-zinc-300">
                    Prompt
                    <textarea wire:model="prompt" rows="4" class="resize-none rounded-lg border border-white/10 bg-[#0a0a0a] px-3 py-2 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-cyan-300/50 focus:ring-4 focus:ring-cyan-300/10" placeholder="Audience, layout, sections, tone"></textarea>
                    @error('prompt') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <div class="mt-1 flex items-center justify-end gap-2">
                    <button type="button" @click="newPage = false" class="inline-flex h-9 items-center rounded-lg border border-white/10 px-3 text-sm font-semibold text-zinc-300 transition hover:border-white/25">Cancel</button>
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-cyan-400 px-4 text-sm font-bold text-slate-950 transition hover:bg-cyan-300">Create page</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Site planner modal --}}
    @if ($sitePlannerOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 px-4 py-10 backdrop-blur-sm">
            <section class="w-full max-w-3xl rounded-2xl border border-white/10 bg-[#161616] shadow-2xl shadow-black/50">
                <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-white">Generate site</h2>
                        <p class="mt-1 text-sm text-zinc-400">Review the pages before queueing generation.</p>
                    </div>
                    <button type="button" wire:click="closeGenerateSite" class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-zinc-400 transition hover:border-white/25 hover:text-white">✕</button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex flex-col gap-1 text-sm font-medium text-zinc-300">
                            Provider
                            <select wire:model="siteProvider" wire:change="refreshSiteModel" class="h-10 rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none focus:border-cyan-300/50">
                                @foreach ($siteProviderOptions as $providerOption)
                                    <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-sm font-medium text-zinc-300">
                            Model
                            <select wire:model="siteModel" class="h-10 rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none focus:border-cyan-300/50">
                                @foreach ($siteModelOptions as $modelOption)
                                    <option value="{{ $modelOption['id'] }}">{{ $modelOption['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="planSitePages" wire:loading.attr="disabled" class="inline-flex h-9 items-center rounded-lg border border-white/10 px-3 text-sm font-semibold text-zinc-200 transition hover:border-cyan-400/40 hover:text-cyan-200">Recalculate</button>
                        <span wire:loading wire:target="openGenerateSite,planSitePages" class="text-sm text-cyan-200">Planning pages...</span>
                    </div>

                    @if ($sitePlanningError)
                        <div class="rounded-lg border border-rose-900/70 bg-rose-950/30 px-3 py-2 text-sm text-rose-100">{{ $sitePlanningError }}</div>
                    @endif

                    @if ($sitePlannerSummary !== '')
                        <p class="rounded-lg border border-white/10 bg-[#0a0a0a] px-3 py-2 text-sm text-zinc-300">{{ $sitePlannerSummary }}</p>
                    @endif

                    <div class="divide-y divide-white/10 overflow-hidden rounded-xl border border-white/10">
                        @forelse ($siteProposals as $index => $proposal)
                            <div wire:key="site-proposal-{{ $index }}" class="flex gap-3 bg-[#0a0a0a] px-3 py-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold text-white">{{ $proposal['name'] }}</h3>
                                        <span class="rounded-md bg-white/5 px-1.5 py-0.5 text-xs text-zinc-300">{{ $proposal['slug'] }}.html</span>
                                    </div>
                                    <p class="mt-1 text-sm text-zinc-400">{{ $proposal['brief'] }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">{{ $proposal['reason'] }}</p>
                                </div>
                                <button type="button" wire:click="removeSiteProposal({{ $index }})" class="h-8 shrink-0 rounded-lg border border-white/10 px-2 text-sm font-semibold text-zinc-300 transition hover:border-rose-500 hover:text-rose-200">Remove</button>
                            </div>
                        @empty
                            <div class="bg-[#0a0a0a] px-3 py-8 text-center text-sm text-zinc-500">No proposed pages yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-white/10 px-5 py-4">
                    <button type="button" wire:click="closeGenerateSite" class="inline-flex h-9 items-center rounded-lg border border-white/10 px-3 text-sm font-semibold text-zinc-200 transition hover:border-white/25">Cancel</button>
                    <button type="button" wire:click="proceedGenerateSite" wire:loading.attr="disabled" class="inline-flex h-9 items-center rounded-lg bg-cyan-400 px-4 text-sm font-bold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-wait disabled:bg-cyan-700">Proceed</button>
                </div>
            </section>
        </div>
    @endif
</x-app-shell>
