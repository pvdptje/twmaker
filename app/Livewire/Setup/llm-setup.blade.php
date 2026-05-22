<main
    class="mx-auto flex min-h-screen w-full max-w-5xl flex-col gap-6 px-6 py-8"
    x-data="{
        apiKeys: @entangle('apiKeys'),
        primaryProvider: @entangle('primaryProvider').live,
        primaryModel: @entangle('primaryModel').live,
        editingProvider: @entangle('editingProvider').live,
        editingModel: @entangle('editingModel').live,
        providers: @js(array_column($providerOptions, 'id')),
        keyName(provider) { return `twmaker.apiKey.${provider}`; },
        setupName(kind, field) { return `twmaker.llmDefaults.${kind}.${field}`; },
        load() {
            this.providers.forEach((provider) => {
                this.apiKeys[provider] = localStorage.getItem(this.keyName(provider)) || '';
            });

            this.primaryProvider = localStorage.getItem(this.setupName('primary', 'provider')) || this.primaryProvider;
            this.primaryModel = localStorage.getItem(this.setupName('primary', 'model')) || this.primaryModel;
            this.editingProvider = localStorage.getItem(this.setupName('editing', 'provider')) || this.editingProvider;
            this.editingModel = localStorage.getItem(this.setupName('editing', 'model')) || this.editingModel;
        },
        persist() {
            this.providers.forEach((provider) => {
                const key = this.apiKeys[provider] || '';
                if (key) localStorage.setItem(this.keyName(provider), key);
                else localStorage.removeItem(this.keyName(provider));
            });

            localStorage.setItem(this.setupName('primary', 'provider'), this.primaryProvider);
            localStorage.setItem(this.setupName('primary', 'model'), this.primaryModel);
            localStorage.setItem(this.setupName('editing', 'provider'), this.editingProvider);
            localStorage.setItem(this.setupName('editing', 'model'), this.editingModel);
        },
    }"
    x-init="load()"
>
    <header class="flex items-start justify-between gap-4">
        <div>
            <a href="{{ route('projects.index') }}" wire:navigate class="text-sm text-cyan-300 hover:text-cyan-200">Projects</a>
            <h1 class="mt-2 text-3xl font-semibold tracking-normal text-white">LLM setup</h1>
            <p class="mt-1 text-sm text-neutral-400">Manage provider keys and choose the default models used by generation and edits.</p>
        </div>
    </header>

    <form wire:submit="save" x-on:submit="persist()" class="grid gap-4 lg:grid-cols-[1fr_22rem]">
        <section class="rounded-lg border border-neutral-800 bg-neutral-900">
            <div class="border-b border-neutral-800 px-4 py-3 text-sm font-medium text-neutral-300">Provider keys</div>
            <div class="divide-y divide-neutral-800">
                @foreach ($providerOptions as $providerOption)
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-white">{{ $providerOption['label'] }}</h2>
                                <p class="mt-1 text-xs text-neutral-500">{{ $providerOption['driver'] }} provider</p>
                            </div>
                            <button type="button" wire:click="refreshModels('{{ $providerOption['id'] }}')" class="rounded-md border border-neutral-700 px-3 py-2 text-sm font-semibold text-neutral-200 hover:border-neutral-500">Refresh models</button>
                        </div>

                        <label class="mt-4 block text-xs font-medium text-neutral-400">
                            API key
                            <input wire:model.blur="apiKeys.{{ $providerOption['id'] }}" x-model="apiKeys['{{ $providerOption['id'] }}']" type="password" autocomplete="off" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400" placeholder="Stored in this browser">
                        </label>
                        @error("apiKeys.{$providerOption['id']}")
                            <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                        @enderror

                        @if (($modelCatalogStatuses[$providerOption['id']] ?? '') !== '')
                            <div class="mt-2 text-xs text-neutral-500">{{ $modelCatalogStatuses[$providerOption['id']] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <aside class="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
            <h2 class="text-base font-semibold text-white">Defaults</h2>

            <div class="mt-4 border-b border-neutral-800 pb-4">
                <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Primary generation</div>
                <label class="mt-3 block text-xs font-medium text-neutral-400">
                    Provider
                    <select wire:model.live="primaryProvider" x-model="primaryProvider" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
                        @foreach ($providerOptions as $providerOption)
                            <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-3 block text-xs font-medium text-neutral-400">
                    Model
                    <select wire:model.live="primaryModel" x-model="primaryModel" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
                        @foreach (($modelOptionsByProvider[$primaryProvider] ?? []) as $modelOption)
                            <option value="{{ $modelOption['id'] }}">{{ $modelOption['label'] }} ({{ $modelOption['id'] }})</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="mt-4">
                <div class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Editing</div>
                <label class="mt-3 block text-xs font-medium text-neutral-400">
                    Provider
                    <select wire:model.live="editingProvider" x-model="editingProvider" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
                        @foreach ($providerOptions as $providerOption)
                            <option value="{{ $providerOption['id'] }}">{{ $providerOption['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-3 block text-xs font-medium text-neutral-400">
                    Model
                    <select wire:model.live="editingModel" x-model="editingModel" class="mt-1 w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400">
                        @foreach (($modelOptionsByProvider[$editingProvider] ?? []) as $modelOption)
                            <option value="{{ $modelOption['id'] }}">{{ $modelOption['label'] }} ({{ $modelOption['id'] }})</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <button type="submit" class="mt-4 w-full rounded-md bg-cyan-500 px-3 py-2 text-sm font-semibold text-neutral-950 hover:bg-cyan-400">Save setup</button>
            @if ($saveStatus !== '')
                <div class="mt-2 text-xs text-neutral-500">{{ $saveStatus }}</div>
            @endif
        </aside>
    </form>
</main>
