<div
    class="flex h-full items-center justify-between gap-4 px-4"
    x-data="{
        selectedValue: @js($defaultValue),
        choices: @js($choices),
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

            const selection = { provider: choice.provider, model: choice.model };
            localStorage.setItem(this.sharedKey, JSON.stringify(selection));
            localStorage.setItem(this.legacyKey('primary', 'provider'), choice.provider);
            localStorage.setItem(this.legacyKey('editing', 'provider'), choice.provider);
            localStorage.setItem(this.legacyKey('primary', `model.${choice.provider}`), choice.model);
            localStorage.setItem(this.legacyKey('editing', `model.${choice.provider}`), choice.model);

            window.dispatchEvent(new CustomEvent('builder-model-selection-changed', {
                detail: {
                    provider: choice.provider,
                    model: choice.model,
                    apiKey: localStorage.getItem(this.storageKey(choice.provider)) || '',
                },
            }));
        },
    }"
    x-init="loadSelection(); $watch('selectedValue', () => saveSelection())"
>
    <div class="min-w-0">
        <div class="text-sm font-semibold text-white">Model</div>
        <div class="mt-0.5 truncate text-xs text-neutral-500" x-text="selectedChoice()?.label || 'No models available'"></div>
    </div>
    <div class="flex min-w-[18rem] items-center gap-2">
        <select
            x-model="selectedValue"
            class="w-full rounded-md border border-neutral-800 bg-neutral-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-400"
            aria-label="Model"
        >
            <template x-for="choice in choices" :key="choice.value">
                <option :value="choice.value" x-text="choice.label"></option>
            </template>
        </select>
        <a href="{{ route('setup.llm') }}" wire:navigate class="shrink-0 rounded-md border border-neutral-800 px-3 py-2 text-sm font-medium text-cyan-300 hover:border-cyan-500 hover:text-cyan-200">Setup</a>
    </div>
</div>
