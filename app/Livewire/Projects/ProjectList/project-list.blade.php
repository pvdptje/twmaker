@php
    $totalPages = $projects->sum('pages_count');
    $thumbs = [
        ['grad' => 'from-slate-900 via-blue-950 to-cyan-950', 'ring' => 'border-cyan-300/15', 'bar' => 'bg-cyan-300/50', 'avatar' => 'bg-cyan-500/15 text-cyan-200 ring-cyan-300/20'],
        ['grad' => 'from-[#101010] via-[#181818] to-emerald-950/60', 'ring' => 'border-white/10', 'bar' => 'bg-emerald-300/40', 'avatar' => 'bg-emerald-500/15 text-emerald-200 ring-emerald-300/20'],
        ['grad' => 'from-violet-950/60 via-[#161616] to-[#090909]', 'ring' => 'border-violet-300/15', 'bar' => 'bg-violet-300/45', 'avatar' => 'bg-violet-500/15 text-violet-200 ring-violet-300/20'],
        ['grad' => 'from-orange-950/50 via-[#151515] to-[#090909]', 'ring' => 'border-orange-300/15', 'bar' => 'bg-orange-300/45', 'avatar' => 'bg-orange-500/15 text-orange-200 ring-orange-300/20'],
        ['grad' => 'from-pink-950/50 via-[#151515] to-[#090909]', 'ring' => 'border-pink-300/15', 'bar' => 'bg-pink-300/45', 'avatar' => 'bg-pink-500/15 text-pink-200 ring-pink-300/20'],
        ['grad' => 'from-blue-950/60 via-[#141414] to-[#090909]', 'ring' => 'border-blue-300/15', 'bar' => 'bg-blue-300/45', 'avatar' => 'bg-blue-500/15 text-blue-200 ring-blue-300/20'],
    ];
@endphp

<x-app-shell active="projects" x-data="{ newProject: false, q: '' }">
    <x-slot:breadcrumb>
        <span>Projects</span>
        <span class="text-zinc-700">/</span>
        <span class="text-zinc-300">All projects</span>
    </x-slot:breadcrumb>

    <x-slot:actions>
        <button type="button" @click="newProject = true" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-500 px-3 py-1.5 text-xs font-bold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="h-3.5 w-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New project
        </button>
    </x-slot:actions>

    {{-- Overview --}}
    <section class="border-b border-white/10 bg-[#111111]/70">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-300/80">Workspace</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Projects</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-400">Manage your TwMaker projects, jump into the editor, and start new AI-generated Tailwind pages from a single workspace.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('setup.llm') }}" wire:navigate class="rounded-xl border border-white/10 bg-[#0b0b0b] px-4 py-2.5 text-center text-sm font-semibold text-zinc-200 transition hover:border-white/20 hover:bg-white/[0.04]">LLM setup</a>
                    <button type="button" @click="newProject = true" class="rounded-xl bg-blue-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-400">＋ New project</button>
                </div>
            </div>

            <div class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                    <p class="text-xs font-medium text-zinc-500">Projects</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $projects->count() }}</p>
                    <p class="mt-1 text-xs text-zinc-500">In this workspace</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                    <p class="text-xs font-medium text-zinc-500">Pages</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $totalPages }}</p>
                    <p class="mt-1 text-xs text-zinc-500">Across all projects</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                    <p class="text-xs font-medium text-zinc-500">Editor</p>
                    <p class="mt-2 text-2xl font-bold text-white">Tailwind</p>
                    <p class="mt-1 text-xs text-zinc-500">AI generate &amp; edit</p>
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
        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-[#161616] p-3 shadow-2xl shadow-black/20 md:flex-row md:items-center md:justify-between">
                <label class="relative flex min-w-0 flex-1 items-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="pointer-events-none absolute left-3 h-4 w-4 text-zinc-500"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" x-model="q" placeholder="Search projects by name or description..." class="h-11 w-full rounded-xl border border-white/10 bg-[#080808] pl-9 pr-4 text-sm text-zinc-200 outline-none transition placeholder:text-zinc-600 focus:border-cyan-300/50 focus:ring-4 focus:ring-cyan-300/10">
                </label>
                <p class="shrink-0 px-1 text-xs text-zinc-500">{{ $projects->count() }} {{ $projects->count() === 1 ? 'project' : 'projects' }}</p>
            </div>
        </div>
    </section>

    {{-- Grid --}}
    <section class="bg-[#0b0b0b]">
        <div class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($projects as $i => $project)
                    @php $t = $thumbs[$i % count($thumbs)]; @endphp
                    <article
                        wire:key="project-{{ $project->id }}"
                        data-search="{{ str(($project->name.' '.$project->description))->lower() }}"
                        x-show="q === '' || $el.dataset.search.includes(q.toLowerCase())"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-[#181818] shadow-xl shadow-black/20 transition hover:-translate-y-0.5 hover:border-cyan-300/30"
                    >
                        @if ($editingProjectId === $project->id)
                            <form wire:submit="renameProject" class="flex flex-col gap-2.5 p-4">
                                <label class="flex flex-col gap-1 text-xs font-medium text-zinc-400">
                                    Name
                                    <input wire:model="editingProjectName" class="h-9 rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none focus:border-cyan-300/50" maxlength="120">
                                    @error('editingProjectName') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                </label>
                                <label class="flex flex-col gap-1 text-xs font-medium text-zinc-400">
                                    Description
                                    <textarea wire:model="editingProjectDescription" rows="3" class="resize-none rounded-lg border border-white/10 bg-[#0a0a0a] px-3 py-2 text-sm text-white outline-none focus:border-cyan-300/50"></textarea>
                                    @error('editingProjectDescription') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="inline-flex h-8 items-center rounded-lg bg-cyan-400 px-3 text-xs font-bold text-slate-950 hover:bg-cyan-300">Save</button>
                                    <button type="button" wire:click="cancelRenamingProject" class="inline-flex h-8 items-center rounded-lg border border-white/10 px-3 text-xs font-semibold text-zinc-300 hover:border-white/25">Cancel</button>
                                </div>
                            </form>
                        @else
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="block border-b border-white/10 bg-[#0b0b0b] p-3">
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
                                    <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex min-w-0 gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold ring-1 {{ $t['avatar'] }}">{{ str($project->name)->substr(0, 1)->upper() }}</span>
                                        <div class="min-w-0">
                                            <h2 class="truncate font-semibold text-white">{{ $project->name }}</h2>
                                            <p class="mt-1 line-clamp-2 text-sm leading-5 text-zinc-400">{{ $project->description ?: 'No description' }}</p>
                                        </div>
                                    </a>
                                    <span class="shrink-0 rounded-full border border-white/10 bg-black/30 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-zinc-300">{{ $project->pages_count }} {{ $project->pages_count === 1 ? 'page' : 'pages' }}</span>
                                </div>

                                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-3 text-xs text-zinc-500">
                                    <span>Updated {{ $project->updated_at?->diffForHumans() ?? 'recently' }}</span>
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="rounded-lg border border-white/10 px-2.5 py-1 font-semibold text-zinc-200 transition hover:border-cyan-400/40 hover:text-cyan-200">Open</a>
                                        <button type="button" wire:click="startRenamingProject('{{ $project->id }}')" class="rounded-lg border border-white/10 px-2.5 py-1 font-semibold text-zinc-300 transition hover:border-white/25">Rename</button>
                                        <button type="button" wire:confirm="Delete this project and all pages?" wire:click="deleteProject('{{ $project->id }}')" class="rounded-lg border border-rose-900/70 px-2.5 py-1 font-semibold text-rose-300 transition hover:border-rose-500">Delete</button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                @endforelse

                {{-- Create card --}}
                <button type="button" @click="newProject = true" class="group flex min-h-[19rem] flex-col items-center justify-center rounded-2xl border border-dashed border-white/15 bg-[#141414] p-6 text-center shadow-xl shadow-black/20 transition hover:border-cyan-300/40 hover:bg-[#171717]">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl border border-cyan-300/25 bg-cyan-300/10 text-2xl font-light text-cyan-200">＋</span>
                    <span class="mt-4 font-semibold text-white">Create a project</span>
                    <span class="mt-2 max-w-xs text-sm leading-6 text-zinc-500">Group related pages, then generate and edit Tailwind layouts with AI.</span>
                    <span class="mt-5 rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-bold text-slate-950 shadow-lg shadow-cyan-500/20 transition group-hover:bg-cyan-300">New project</span>
                </button>
            </div>

            @if ($projects->isEmpty())
                <p class="mt-6 text-center text-sm text-zinc-500">No projects yet — create your first one to start drafting pages with TwMaker.</p>
            @endif
        </div>
    </section>

    {{-- New project modal --}}
    <div x-show="newProject" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 px-4 py-10 backdrop-blur-sm" @keydown.escape.window="newProject = false">
        <div @click.outside="newProject = false" class="w-full max-w-lg rounded-2xl border border-white/10 bg-[#161616] shadow-2xl shadow-black/50">
            <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-white">New project</h2>
                    <p class="mt-1 text-sm text-zinc-500">Give it a name and an optional brief.</p>
                </div>
                <button type="button" @click="newProject = false" class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-zinc-400 transition hover:border-white/25 hover:text-white">✕</button>
            </div>
            <form wire:submit="createProject" class="flex flex-col gap-3 px-5 py-4">
                <label class="flex flex-col gap-1 text-sm font-medium text-zinc-300">
                    Name
                    <input wire:model="name" class="h-10 rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-cyan-300/50 focus:ring-4 focus:ring-cyan-300/10" maxlength="120" placeholder="Acme redesign">
                    @error('name') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm font-medium text-zinc-300">
                    Description
                    <textarea wire:model="description" rows="3" class="resize-none rounded-lg border border-white/10 bg-[#0a0a0a] px-3 py-2 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-cyan-300/50 focus:ring-4 focus:ring-cyan-300/10" placeholder="Brand, audience, or campaign notes"></textarea>
                    @error('description') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <div class="mt-1 flex items-center justify-end gap-2">
                    <button type="button" @click="newProject = false" class="inline-flex h-9 items-center rounded-lg border border-white/10 px-3 text-sm font-semibold text-zinc-300 transition hover:border-white/25">Cancel</button>
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-cyan-400 px-4 text-sm font-bold text-slate-950 transition hover:bg-cyan-300">Create project</button>
                </div>
            </form>
        </div>
    </div>
</x-app-shell>
