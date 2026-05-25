<main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-8 px-6 py-8">
    <header class="flex items-start justify-between gap-4">
        <div class="flex flex-col gap-2">
            <p class="text-sm font-medium text-cyan-300">Tailwind Template Builder</p>
            <h1 class="text-3xl font-semibold tracking-normal text-white">Projects</h1>
        </div>
        <a href="{{ route('setup.llm') }}" wire:navigate class="rounded-md border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 hover:border-neutral-500">LLM setup</a>
    </header>

    <section class="grid gap-4 lg:grid-cols-[22rem_1fr]">
        <form wire:submit="createProject" class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
            <h2 class="text-base font-semibold text-white">Create project</h2>
            <div class="mt-4 flex flex-col gap-3">
                <label class="flex flex-col gap-1 text-sm text-neutral-300">
                    Name
                    <input wire:model="name" class="rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none focus:border-cyan-400" maxlength="120">
                    @error('name') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-neutral-300">
                    Description
                    <textarea wire:model="description" rows="4" class="rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none focus:border-cyan-400"></textarea>
                    @error('description') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <button type="submit" class="rounded-md bg-cyan-400 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">Create</button>
            </div>
        </form>

        <div class="rounded-lg border border-neutral-800 bg-neutral-900">
            <div class="border-b border-neutral-800 px-4 py-3 text-sm font-medium text-neutral-300">Recent projects</div>
            <div class="divide-y divide-neutral-800">
                @forelse ($projects as $project)
                    <div wire:key="project-{{ $project->id }}" class="px-4 py-3 hover:bg-neutral-800">
                        @if ($editingProjectId === $project->id)
                            <form wire:submit="renameProject" class="flex flex-col gap-3">
                                <label class="flex flex-col gap-1 text-sm text-neutral-300">
                                    Name
                                    <input wire:model="editingProjectName" class="rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none focus:border-cyan-400" maxlength="120">
                                    @error('editingProjectName') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                </label>
                                <label class="flex flex-col gap-1 text-sm text-neutral-300">
                                    Description
                                    <textarea wire:model="editingProjectDescription" rows="3" class="rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none focus:border-cyan-400"></textarea>
                                    @error('editingProjectDescription') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="rounded-md bg-cyan-400 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">Save</button>
                                    <button type="button" wire:click="cancelRenamingProject" class="rounded-md border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Cancel</button>
                                </div>
                            </form>
                        @else
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <a href="{{ route('projects.show', $project) }}" wire:navigate class="min-w-0 flex-1">
                                    <div class="font-medium text-white">{{ $project->name }}</div>
                                    <div class="mt-1 text-sm text-neutral-400">{{ $project->description ?: 'No description' }}</div>
                                </a>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <button type="button" wire:click="startRenamingProject('{{ $project->id }}')" class="rounded-md border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Rename</button>
                                    <button
                                        type="button"
                                        wire:confirm="Delete this project and all pages?"
                                        wire:click="deleteProject('{{ $project->id }}')"
                                        class="rounded-md border border-rose-900/70 px-3 py-2 text-sm font-semibold text-rose-200 hover:border-rose-500"
                                    >Delete</button>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-8 text-sm text-neutral-400">No projects yet.</div>
                @endforelse
            </div>
        </div>
    </section>
</main>
