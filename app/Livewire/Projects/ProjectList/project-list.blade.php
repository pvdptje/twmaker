<main class="min-h-screen bg-neutral-950 text-neutral-100">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <header class="flex h-24 flex-col justify-center gap-3 overflow-hidden border-b border-neutral-800 lg:h-20 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ route('projects.index') }}" wire:navigate class="shrink-0 text-xl font-semibold tracking-normal text-white">
                    TwMaker
                </a>
                <div class="hidden h-8 w-px bg-neutral-800 sm:block"></div>
                <div class="min-w-0 overflow-hidden">
                    <p class="text-xs font-semibold uppercase tracking-normal text-cyan-300">Workspace</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-normal text-white sm:text-3xl">Projects</h1>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a href="{{ route('setup.llm') }}" wire:navigate class="inline-flex h-10 items-center rounded-md border border-neutral-700 bg-neutral-900 px-3 text-sm font-semibold text-neutral-200 hover:border-cyan-500 hover:text-cyan-200">
                    LLM setup
                </a>
            </div>
        </header>

        <section class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
            <aside>
                <form wire:submit="createProject" class="rounded-lg border border-neutral-800 bg-neutral-900 p-3 shadow-2xl shadow-black/20">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold text-white">New project</h2>
                    </div>

                    <div class="mt-3 flex flex-col gap-2.5">
                        <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                            Name
                            <input wire:model="name" class="h-9 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none placeholder:text-neutral-600 focus:border-cyan-400" maxlength="120" placeholder="Acme redesign">
                            @error('name') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                        </label>

                        <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                            Description
                            <textarea wire:model="description" rows="3" class="resize-none rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-white outline-none placeholder:text-neutral-600 focus:border-cyan-400" placeholder="Brand, audience, or campaign notes"></textarea>
                            @error('description') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                        </label>

                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-cyan-400 px-3 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">
                            Create project
                        </button>
                    </div>
                </form>
            </aside>

            <section class="min-w-0 rounded-lg border border-neutral-800 bg-neutral-900 shadow-2xl shadow-black/20">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 px-4 py-3 sm:px-5">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Recent projects</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">{{ $projects->count() }} {{ $projects->count() === 1 ? 'project' : 'projects' }} in this workspace</p>
                    </div>
                </div>

                <div class="divide-y divide-neutral-800">
                    @forelse ($projects as $project)
                        <article wire:key="project-{{ $project->id }}" class="group px-4 py-3 transition hover:bg-neutral-800/55 sm:px-5">
                            @if ($editingProjectId === $project->id)
                                <form wire:submit="renameProject" class="flex flex-col gap-2.5">
                                    <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                                        Name
                                        <input wire:model="editingProjectName" class="h-9 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400" maxlength="120">
                                        @error('editingProjectName') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                    </label>

                                    <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                                        Description
                                        <textarea wire:model="editingProjectDescription" rows="2" class="resize-none rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400"></textarea>
                                        @error('editingProjectDescription') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                    </label>

                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="inline-flex h-8 items-center rounded-md bg-cyan-400 px-3 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">Save</button>
                                        <button type="button" wire:click="cancelRenamingProject" class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Cancel</button>
                                    </div>
                                </form>
                            @else
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <a href="{{ route('projects.show', $project) }}" wire:navigate class="min-w-0 flex-1">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-neutral-700 bg-neutral-950 text-sm font-semibold text-cyan-200">
                                                {{ str($project->name)->substr(0, 1)->upper() }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                                    <h3 class="truncate text-base font-semibold text-white">{{ $project->name }}</h3>
                                                    <span class="rounded-md border border-neutral-700 bg-neutral-950 px-2 py-0.5 text-xs font-medium text-neutral-300">{{ $project->pages_count }} {{ $project->pages_count === 1 ? 'page' : 'pages' }}</span>
                                                </div>
                                                <p class="mt-1 line-clamp-2 text-sm text-neutral-400">{{ $project->description ?: 'No description' }}</p>
                                            </div>
                                        </div>
                                    </a>

                                    <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-cyan-500 hover:text-cyan-200">Open</a>
                                        <button type="button" wire:click="startRenamingProject('{{ $project->id }}')" class="inline-flex h-8 items-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Rename</button>
                                        <button
                                            type="button"
                                            wire:confirm="Delete this project and all pages?"
                                            wire:click="deleteProject('{{ $project->id }}')"
                                            class="inline-flex h-8 items-center rounded-md border border-rose-900/70 px-3 text-sm font-semibold text-rose-200 hover:border-rose-500"
                                        >Delete</button>
                                    </div>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="px-4 py-14 text-center sm:px-5">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-md border border-neutral-800 bg-neutral-950 text-lg font-semibold text-cyan-200">T</div>
                            <h3 class="mt-4 text-base font-semibold text-white">No projects yet</h3>
                            <p class="mx-auto mt-1 max-w-sm text-sm text-neutral-400">Create a project to start drafting pages with TwMaker.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </section>
    </div>
</main>
