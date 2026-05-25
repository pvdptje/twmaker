<div
    class="relative z-40 flex min-w-0 items-center gap-2"
    x-data="{
        selectedValue: @js($defaultValue),
        choices: @js($choices),
        providerIds: @js($providerIds),
        sharedKey: 'twmaker.builder.modelSelection',
        storageKey(provider) { return `twmaker.apiKey.${provider}`; },
        defaultKey(kind, field) { return `twmaker.llmDefaults.${kind}.${field}`; },
        legacyKey(kind, field) { return `twmaker.builder.${kind}.${field}`; },
        selectedChoice() {
            return this.choices.find((choice) => choice.value === this.selectedValue) || this.choices[0] || null;
        },
        hasChoice(provider, model) {
            return this.choices.some((choice) => choice.provider === provider && choice.model === model);
        },
        valueFor(provider, model) {
            const choice = this.choices.find((option) => option.provider === provider && option.model === model);
            return choice?.value || '';
        },
        parseStoredSelection() {
            try {
                const stored = JSON.parse(localStorage.getItem(this.sharedKey) || 'null');
                if (stored?.provider && stored?.model && this.hasChoice(stored.provider, stored.model)) {
                    return stored;
                }
            } catch (error) {}

            const provider = localStorage.getItem(this.legacyKey('primary', 'provider'))
                || localStorage.getItem(this.legacyKey('editing', 'provider'))
                || localStorage.getItem(this.defaultKey('primary', 'provider'))
                || localStorage.getItem(this.defaultKey('editing', 'provider'))
                || '';
            const model = provider
                ? (localStorage.getItem(this.legacyKey('primary', `model.${provider}`))
                    || localStorage.getItem(this.legacyKey('editing', `model.${provider}`))
                    || localStorage.getItem(this.defaultKey('primary', 'model'))
                    || localStorage.getItem(this.defaultKey('editing', 'model'))
                    || '')
                : '';

            return provider && model && this.hasChoice(provider, model) ? { provider, model } : null;
        },
        browserApiKeys() {
            return this.providerIds.reduce((keys, provider) => {
                const apiKey = localStorage.getItem(this.storageKey(provider)) || '';
                if (apiKey) keys[provider] = apiKey;

                return keys;
            }, {});
        },
        hydrateChoices() {
            const apiKeys = this.browserApiKeys();
            if (Object.keys(apiKeys).length === 0) {
                this.loadSelection();

                return;
            }

            this.$wire.choicesForApiKeys(apiKeys)
                .then((choices) => {
                    if (Array.isArray(choices) && choices.length > 0) {
                        this.choices = choices;

                        if (!this.selectedChoice()) {
                            this.selectedValue = choices[0].value;
                        }
                    }

                    this.loadSelection();
                })
                .catch(() => this.loadSelection());
        },
        loadSelection() {
            const stored = this.parseStoredSelection();
            if (stored) {
                this.selectedValue = this.valueFor(stored.provider, stored.model);
            }

            this.saveSelection();
        },
        saveSelection() {
            const choice = this.selectedChoice();
            if (!choice) return;

            const selection = {
                provider: choice.provider,
                model: choice.model,
                modalities: Array.isArray(choice.modalities) ? choice.modalities : ['text'],
            };
            localStorage.setItem(this.sharedKey, JSON.stringify(selection));
            localStorage.setItem(this.legacyKey('primary', 'provider'), choice.provider);
            localStorage.setItem(this.legacyKey('editing', 'provider'), choice.provider);
            localStorage.setItem(this.legacyKey('primary', `model.${choice.provider}`), choice.model);
            localStorage.setItem(this.legacyKey('editing', `model.${choice.provider}`), choice.model);

            window.dispatchEvent(new CustomEvent('builder-model-selection-changed', {
                detail: {
                    provider: choice.provider,
                    model: choice.model,
                    modalities: Array.isArray(choice.modalities) ? choice.modalities : ['text'],
                    apiKey: localStorage.getItem(this.storageKey(choice.provider)) || '',
                },
            }));
        },
    }"
    x-init="hydrateChoices(); $watch('selectedValue', () => saveSelection())"
>
    <label for="builder-model-selector" class="text-xs font-medium text-neutral-500">Model</label>
    <select
        id="builder-model-selector"
        x-model="selectedValue"
        class="h-8 w-[min(24rem,34vw)] rounded-md border border-neutral-800 bg-neutral-950 px-2 text-xs text-white outline-none focus:border-cyan-400"
        aria-label="Model"
        x-bind:title="selectedChoice()?.label || 'No models available'"
    >
        <template x-for="choice in choices" :key="choice.value">
            <option :value="choice.value" x-text="choice.label"></option>
        </template>
    </select>
    <a href="{{ route('setup.llm') }}" wire:navigate class="flex h-8 shrink-0 items-center rounded-md border border-neutral-800 px-2 text-xs font-medium text-cyan-300 hover:border-cyan-500 hover:text-cyan-200">Setup</a>
</div>
