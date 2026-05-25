<main
    class="min-h-screen bg-neutral-950 text-neutral-100"
    x-data="{
        apiKeys: @entangle('apiKeys'),
        primaryProvider: @entangle('primaryProvider').live,
        primaryModel: @entangle('primaryModel').live,
        editingProvider: @entangle('editingProvider').live,
        editingModel: @entangle('editingModel').live,
        providers: @js(array_column($providerOptions, 'id')),
        modelsByProvider: @js($modelOptionsByProvider),
        sharedKey: 'twmaker.builder.modelSelection',
        keyName(provider) { return `twmaker.apiKey.${provider}`; },
        setupName(kind, field) { return `twmaker.llmDefaults.${kind}.${field}`; },
        builderName(kind, field) { return `twmaker.builder.${kind}.${field}`; },
        selectedModelOption() {
            return (this.modelsByProvider[this.primaryProvider] || [])
                .find((model) => model.id === this.primaryModel) || null;
        },
        syncDefaults() {
            this.editingProvider = this.primaryProvider;
            this.editingModel = this.primaryModel;
        },
        persistProviderKey(provider) {
            const key = this.apiKeys[provider] || '';
            if (key) localStorage.setItem(this.keyName(provider), key);
            else localStorage.removeItem(this.keyName(provider));
        },
        load() {
            this.providers.forEach((provider) => {
                this.apiKeys[provider] = localStorage.getItem(this.keyName(provider)) || '';
            });

            this.primaryProvider = localStorage.getItem(this.setupName('primary', 'provider'))
                || localStorage.getItem(this.setupName('editing', 'provider'))
                || this.primaryProvider;
            this.primaryModel = localStorage.getItem(this.setupName('primary', 'model'))
                || localStorage.getItem(this.setupName('editing', 'model'))
                || this.primaryModel;
            this.syncDefaults();
        },
        persist() {
            this.syncDefaults();
            this.providers.forEach((provider) => {
                this.persistProviderKey(provider);
            });

            localStorage.setItem(this.setupName('primary', 'provider'), this.primaryProvider);
            localStorage.setItem(this.setupName('primary', 'model'), this.primaryModel);
            localStorage.setItem(this.setupName('editing', 'provider'), this.editingProvider);
            localStorage.setItem(this.setupName('editing', 'model'), this.editingModel);
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
    x-init="load()"
>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <header class="flex h-28 flex-col justify-center gap-3 overflow-hidden border-b border-neutral-800 lg:h-20 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ route('projects.index') }}" wire:navigate class="shrink-0 text-xl font-semibold tracking-normal text-white">
                    TwMaker
                </a>
                <div class="hidden h-8 w-px bg-neutral-800 sm:block"></div>
                <div class="min-w-0 overflow-hidden">
                    <a href="{{ route('projects.index') }}" wire:navigate class="text-xs font-semibold uppercase tracking-normal text-cyan-300 hover:text-cyan-200">Projects</a>
                    <h1 class="mt-1 text-2xl font-semibold tracking-normal text-white sm:text-3xl">LLM setup</h1>
                    <p class="mt-1 max-w-2xl truncate text-sm text-neutral-400">Keys are stored only in this browser. Server env keys are optional fallbacks.</p>
                </div>
            </div>
        </header>

        <form wire:submit="save" x-on:submit="persist()" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="min-w-0 rounded-lg border border-neutral-800 bg-neutral-900 shadow-2xl shadow-black/20">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 px-4 py-3 sm:px-5">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Provider keys</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">{{ count($providerOptions) }} configured providers</p>
                    </div>
                </div>

                <div class="divide-y divide-neutral-800">
                    @foreach ($providerOptions as $providerOption)
                        <article class="px-4 py-3 transition hover:bg-neutral-800/55 sm:px-5">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-neutral-700 bg-neutral-950 text-sm font-semibold text-cyan-200">
                                            {{ str($providerOption['label'])->substr(0, 1)->upper() }}
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="truncate text-base font-semibold text-white">{{ $providerOption['label'] }}</h3>
                                            <p class="mt-0.5 text-xs text-neutral-500">{{ $providerOption['driver'] }} provider</p>
                                        </div>
                                    </div>

                                    <label class="mt-3 block text-xs font-medium text-neutral-400">
                                        API key
                                        <input wire:model.blur="apiKeys.{{ $providerOption['id'] }}" x-model="apiKeys['{{ $providerOption['id'] }}']" type="password" autocomplete="off" class="mt-1 h-9 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 text-sm text-white outline-none placeholder:text-neutral-600 focus:border-cyan-400" placeholder="Stored in this browser">
                                    </label>

                                    @error("apiKeys.{$providerOption['id']}")
                                        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                                    @enderror

                                    @if (($modelCatalogStatuses[$providerOption['id']] ?? '') !== '')
                                        <div class="mt-2 text-xs text-neutral-500">{{ $modelCatalogStatuses[$providerOption['id']] }}</div>
                                    @endif
                                </div>

                                <button type="button" x-on:click="persistProviderKey('{{ $providerOption['id'] }}')" wire:click="refreshModels('{{ $providerOption['id'] }}')" class="inline-flex h-8 shrink-0 items-center justify-center rounded-md border border-neutral-700 px-3 text-sm font-semibold text-neutral-200 hover:border-neutral-500">
                                    Refresh models
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <aside class="rounded-lg border border-neutral-800 bg-neutral-900 p-3 shadow-2xl shadow-black/20">
                <h2 class="text-base font-semibold text-white">Defaults</h2>

                <div class="mt-3">
                    <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Builder model</div>
                    <label class="mt-2 block text-xs font-medium text-neutral-400">
                        Provider
                        <select wire:model.live="primaryProvider" x-model="primaryProvider" x-on:change="syncDefaults()" class="mt-1 h-9 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                            @foreach ($providerOptions as $providerOption)
                                <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="mt-2 block text-xs font-medium text-neutral-400">
                        Model
                        <select wire:model.live="primaryModel" x-model="primaryModel" x-on:change="syncDefaults()" class="mt-1 h-9 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                            @foreach (($modelOptionsByProvider[$primaryProvider] ?? []) as $modelOption)
                                <option value="{{ $modelOption['id'] }}">{{ $modelOption['label'] }} ({{ $modelOption['id'] }})</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <button type="submit" class="mt-3 inline-flex h-9 w-full items-center justify-center rounded-md bg-cyan-400 px-3 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">Save setup</button>
                @if ($saveStatus !== '')
                    <div class="mt-2 text-xs text-neutral-500">{{ $saveStatus }}</div>
                @endif
            </aside>
        </form>
    </div>
</main>
