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
                                        @else
                                            <button type="button" disabled class="inline-flex h-8 cursor-not-allowed items-center rounded-md border border-neutral-800 px-3 text-sm font-semibold text-neutral-600">Download</button>
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
</main>
