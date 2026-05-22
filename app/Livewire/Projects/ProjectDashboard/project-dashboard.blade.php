<main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-8 px-6 py-8">
    <header class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('projects.index') }}" wire:navigate class="text-sm text-cyan-300 hover:text-cyan-200">Projects</a>
            <h1 class="mt-2 text-3xl font-semibold tracking-normal text-white">{{ $project->name }}</h1>
            <p class="mt-1 text-sm text-neutral-400">{{ $project->description ?: 'Project dashboard' }}</p>
        </div>
        <a href="{{ route('setup.llm') }}" wire:navigate class="rounded-md border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 hover:border-neutral-500">LLM setup</a>
    </header>

    <section class="grid gap-4 lg:grid-cols-[22rem_1fr]">
        <form wire:submit="createPage" class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
            <h2 class="text-base font-semibold text-white">Create page</h2>
            <div class="mt-4 flex flex-col gap-3">
                <label class="flex flex-col gap-1 text-sm text-neutral-300">
                    Name
                    <input wire:model="name" class="rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none focus:border-cyan-400" maxlength="160">
                    @error('name') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1 text-sm text-neutral-300">
                    Prompt
                    <textarea wire:model="prompt" rows="5" class="rounded-md border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none focus:border-cyan-400"></textarea>
                    @error('prompt') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>
                <button type="submit" class="rounded-md bg-cyan-400 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">Create</button>
            </div>
        </form>

        <div class="rounded-lg border border-neutral-800 bg-neutral-900">
            <div class="border-b border-neutral-800 px-4 py-3 text-sm font-medium text-neutral-300">Pages</div>
            <div class="divide-y divide-neutral-800">
                @forelse ($pages as $page)
                    <a href="{{ route('builder.workspace', [$project, $page]) }}" wire:navigate class="block px-4 py-3 hover:bg-neutral-800">
                        <div class="flex items-center justify-between gap-4">
                            <span class="font-medium text-white">{{ $page->name }}</span>
                            <span class="rounded bg-neutral-800 px-2 py-1 text-xs text-neutral-300">{{ $page->status }}</span>
                        </div>
                        <div class="mt-1 text-sm text-neutral-400">{{ $page->prompt ?: 'Empty draft' }}</div>
                    </a>
                @empty
                    <div class="px-4 py-8 text-sm text-neutral-400">No pages yet.</div>
                @endforelse
            </div>
        </div>
    </section>
</main>
