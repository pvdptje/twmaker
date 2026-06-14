@php
    $totalModels = collect($configuredProviderOptions)->sum(fn ($p) => count($modelOptionsByProvider[$p['id']] ?? []));
@endphp

<x-app-shell active="setup">
    <x-slot:breadcrumb>
        <a href="{{ route('projects.index') }}" wire:navigate class="transition hover:text-zinc-300">Projects</a>
        <span class="text-zinc-700">/</span>
        <span class="text-zinc-300">LLM setup</span>
    </x-slot:breadcrumb>

    <div
        x-data="{
            primaryProvider: @entangle('primaryProvider').live,
            primaryModel: @entangle('primaryModel').live,
            modelsByProvider: @js($modelOptionsByProvider),
            sharedKey: 'twmaker.builder.modelSelection',
            setupName(kind, field) { return `twmaker.llmDefaults.${kind}.${field}`; },
            builderName(kind, field) { return `twmaker.builder.${kind}.${field}`; },
            selectedModelOption() {
                return (this.modelsByProvider[this.primaryProvider] || [])
                    .find((model) => model.id === this.primaryModel) || null;
            },
            persist() {
                if (!this.primaryProvider || !this.primaryModel) return;

                localStorage.setItem(this.setupName('primary', 'provider'), this.primaryProvider);
                localStorage.setItem(this.setupName('primary', 'model'), this.primaryModel);
                localStorage.setItem(this.setupName('editing', 'provider'), this.primaryProvider);
                localStorage.setItem(this.setupName('editing', 'model'), this.primaryModel);
                localStorage.setItem(this.builderName('primary', 'provider'), this.primaryProvider);
                localStorage.setItem(this.builderName('editing', 'provider'), this.primaryProvider);
                localStorage.setItem(this.builderName('primary', `model.${this.primaryProvider}`), this.primaryModel);
                localStorage.setItem(this.builderName('editing', `model.${this.primaryProvider}`), this.primaryModel);

                const modelOption = this.selectedModelOption();
                localStorage.setItem(this.sharedKey, JSON.stringify({
                    provider: this.primaryProvider,
                    model: this.primaryModel,
                    modalities: Array.isArray(modelOption?.modalities) ? modelOption.modalities : ['text'],
                }));
            },
        }"
    >
        {{-- Overview --}}
        <section class="border-b border-white/10 bg-[#111111]/70">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-300/80">Configuration</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">LLM setup</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-400">Provider keys are encrypted in the database and shared with this team. Configure providers and pick the default model used by the builder.</p>

                <div class="mt-7 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                        <p class="text-xs font-medium text-zinc-500">Providers</p>
                        <p class="mt-2 text-2xl font-bold text-white">{{ count($configuredProviderOptions) }}</p>
                        <p class="mt-1 text-xs text-zinc-500">Available in the builder</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-4">
                        <p class="text-xs font-medium text-zinc-500">Models</p>
                        <p class="mt-2 text-2xl font-bold text-white">{{ $totalModels }}</p>
                        <p class="mt-1 text-xs text-zinc-500">Across configured providers</p>
                    </div>
                    <div class="rounded-2xl border border-cyan-300/20 bg-cyan-300/[0.06] p-4">
                        <p class="text-xs font-medium text-cyan-200/70">Keys</p>
                        <p class="mt-2 text-2xl font-bold text-white">Encrypted</p>
                        <p class="mt-1 text-xs text-cyan-200">Shared with this team</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Content --}}
        <section class="bg-[#0b0b0b]">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <section class="min-w-0 overflow-hidden rounded-2xl border border-white/10 bg-[#161616] shadow-2xl shadow-black/20">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3 sm:px-5">
                            <div>
                                <h2 class="text-sm font-semibold text-white">Configured providers</h2>
                                <p class="mt-0.5 text-xs text-zinc-500">{{ count($configuredProviderOptions) }} available in the builder</p>
                            </div>
                            <button
                                type="button"
                                wire:click="reloadModels"
                                wire:loading.attr="disabled"
                                wire:target="reloadModels"
                                class="inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-white/10 px-3 text-sm font-semibold text-zinc-200 transition hover:border-cyan-400/40 hover:text-cyan-100 disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="reloadModels">Reload models</span>
                                <span wire:loading wire:target="reloadModels">Reloading</span>
                            </button>
                        </div>

                        <div class="border-b border-white/10 px-4 py-3 sm:px-5">
                            @if (count($availableProviderOptions) > 0)
                                <div class="grid gap-3 md:grid-cols-[minmax(0,13rem)_minmax(0,1fr)_auto] md:items-end">
                                    <label class="block text-xs font-medium text-zinc-400">
                                        Add provider
                                        <select wire:model="providerToAdd" class="mt-1 h-9 w-full rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none focus:border-cyan-300/50">
                                            <option value="">Select provider</option>
                                            @foreach ($availableProviderOptions as $providerOption)
                                                <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="block text-xs font-medium text-zinc-400">
                                        API key
                                        <input wire:model="newApiKey" type="password" autocomplete="off" class="mt-1 h-9 w-full rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-cyan-300/50" placeholder="Encrypted for this team">
                                    </label>

                                    <button type="button" wire:click="addProvider" class="inline-flex h-9 items-center justify-center rounded-lg bg-cyan-400 px-3 text-sm font-bold text-slate-950 transition hover:bg-cyan-300">Add provider</button>
                                </div>
                                @error('providerToAdd')
                                    <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                                @enderror
                                @error('newApiKey')
                                    <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                                @enderror
                            @else
                                <div class="text-sm text-zinc-400">All implemented providers are already configured for this team.</div>
                            @endif
                        </div>

                        <div class="divide-y divide-white/10">
                            @forelse ($configuredProviderOptions as $providerOption)
                                <article class="px-4 py-4 transition hover:bg-white/[0.02] sm:px-5">
                                    <div class="flex flex-col gap-3">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex min-w-0 items-center gap-2.5">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-[#0a0a0a] text-sm font-bold text-cyan-200">{{ str($providerOption['label'])->substr(0, 1)->upper() }}</span>
                                                    <div class="min-w-0">
                                                        <h3 class="truncate text-base font-semibold text-white">{{ $providerOption['label'] }}</h3>
                                                        <p class="mt-0.5 text-xs text-zinc-500">
                                                            {{ count($modelOptionsByProvider[$providerOption['id']] ?? []) }} models
                                                            @if (($providerOption['models_refreshed_at'] ?? null) !== null)
                                                                from {{ $providerOption['models_refreshed_at'] }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>

                                                @if (($modelCatalogStatuses[$providerOption['id']] ?? '') !== '')
                                                    <div class="mt-2 text-xs text-zinc-500">{{ $modelCatalogStatuses[$providerOption['id']] }}</div>
                                                @endif
                                            </div>

                                            <button type="button" wire:click="removeProvider('{{ $providerOption['id'] }}')" class="inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-white/10 px-3 text-sm font-semibold text-zinc-300 transition hover:border-red-500/60 hover:text-red-200">Remove</button>
                                        </div>

                                        <div class="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                                            <label class="block text-xs font-medium text-zinc-400">
                                                Replace key
                                                <input wire:model="replacementKeys.{{ $providerOption['id'] }}" type="password" autocomplete="off" class="mt-1 h-9 w-full rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-cyan-300/50" placeholder="Paste a new key">
                                            </label>
                                            <button type="button" wire:click="replaceProviderKey('{{ $providerOption['id'] }}')" class="inline-flex h-9 items-center justify-center rounded-lg border border-white/10 px-3 text-sm font-semibold text-zinc-200 transition hover:border-cyan-400/40 hover:text-cyan-100">Save key</button>
                                        </div>
                                        @error("replacementKeys.{$providerOption['id']}")
                                            <div class="text-xs text-red-300">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </article>
                            @empty
                                <div class="px-4 py-12 text-center sm:px-5">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-white/10 bg-[#0a0a0a] text-lg font-semibold text-cyan-200">＋</div>
                                    <h3 class="mt-4 text-sm font-semibold text-white">No providers yet</h3>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-zinc-500">Add a provider key before generating or editing with the builder.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <aside class="h-fit rounded-2xl border border-white/10 bg-[#161616] p-4 shadow-2xl shadow-black/20">
                        <h2 class="text-base font-semibold text-white">Defaults</h2>

                        @if (count($configuredProviderOptions) > 0)
                            <form wire:submit="save" x-on:submit="persist()">
                                <div class="mt-3">
                                    <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Builder model</div>
                                    <label class="mt-2 block text-xs font-medium text-zinc-400">
                                        Provider
                                        <select wire:model.live="primaryProvider" x-model="primaryProvider" class="mt-1 h-9 w-full rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none focus:border-cyan-300/50">
                                            @foreach ($configuredProviderOptions as $providerOption)
                                                <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="mt-2 block text-xs font-medium text-zinc-400">
                                        Model
                                        <select wire:model.live="primaryModel" x-model="primaryModel" class="mt-1 h-9 w-full rounded-lg border border-white/10 bg-[#0a0a0a] px-3 text-sm text-white outline-none focus:border-cyan-300/50">
                                            @foreach (($modelOptionsByProvider[$primaryProvider] ?? []) as $modelOption)
                                                <option value="{{ $modelOption['id'] }}">{{ $modelOption['label'] }} ({{ $modelOption['id'] }})</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>

                                <button type="submit" class="mt-3 inline-flex h-9 w-full items-center justify-center rounded-lg bg-cyan-400 px-3 text-sm font-bold text-slate-950 transition hover:bg-cyan-300">Save setup</button>
                            </form>
                        @else
                            <p class="mt-3 text-sm text-zinc-500">Defaults appear after the first provider is added.</p>
                        @endif

                        @if ($saveStatus !== '')
                            <div class="mt-2 text-xs text-zinc-500">{{ $saveStatus }}</div>
                        @endif
                    </aside>
                </div>
            </div>
        </section>
    </div>
</x-app-shell>
