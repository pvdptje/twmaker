@php
    /**
     * Variables provided by the caller:
     * - string $wrapperClasses  Tailwind classes for the <form> wrapper.
     * - string $submitLabel     Label shown on the submit button while idle.
     * - string $loadingLabel    Label shown while the request is in flight.
     *
     * Inherits the section-tree Alpine x-data scope: insertRunning, attachments,
     * attachError, dragOver, visionAvailable, attachFile / removeAttachment /
     * clearAttachments / handlePaste / handleDrop / startInsert.
     */
@endphp
<form
    x-on:submit.prevent="startInsert()"
    x-init="clearAttachments()"
    x-on:dragover.prevent="dragOver = true"
    x-on:dragleave="if ($event.target === $el) dragOver = false"
    x-on:drop.prevent="dragOver = false; handleDrop($event)"
    x-bind:class="{ 'ring-2 ring-cyan-400/40': dragOver }"
    class="{{ $wrapperClasses }}"
>
    <textarea
        wire:model="insertInstruction"
        x-on:paste="handlePaste($event)"
        rows="3"
        class="w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-white outline-none focus:border-cyan-400"
        placeholder="Describe the new section (Cmd/Ctrl+V to paste a screenshot)"
    ></textarea>
    @error('insertInstruction')
        <div class="mt-1 text-xs text-red-300">{{ $message }}</div>
    @enderror

    <template x-if="attachments.length > 0">
        <div class="mt-2 flex flex-wrap gap-2">
            <template x-for="(att, idx) in attachments" :key="idx">
                <div class="relative">
                    <img :src="att.dataUrl" alt="" class="h-14 w-14 rounded border border-neutral-700 object-cover" />
                    <button
                        type="button"
                        x-on:click="removeAttachment(idx)"
                        class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full border border-neutral-700 bg-neutral-950 text-[10px] text-neutral-300 hover:bg-red-500 hover:text-white"
                        aria-label="Remove attachment"
                    >&times;</button>
                </div>
            </template>
        </div>
    </template>

    <template x-if="attachError">
        <div class="mt-1 text-xs text-red-300" x-text="attachError"></div>
    </template>

    <div class="mt-2 flex items-center gap-2">
        <button
            type="submit"
            x-bind:disabled="insertRunning"
            wire:loading.attr="disabled"
            wire:target="insertSectionWithSelection,insertSection"
            class="inline-flex h-8 flex-1 items-center justify-center rounded-md bg-cyan-500 px-2 text-xs font-semibold text-neutral-950 hover:bg-cyan-400 disabled:bg-neutral-800 disabled:text-neutral-400"
        >
            <span wire:loading.remove wire:target="insertSectionWithSelection,insertSection" x-text="insertRunning ? @js($loadingLabel) : @js($submitLabel)"></span>
            <span wire:loading wire:target="insertSectionWithSelection,insertSection">{{ $loadingLabel }}</span>
        </button>
        <label
            x-bind:class="visionAvailable ? 'cursor-pointer text-neutral-300 hover:text-white hover:border-neutral-600' : 'cursor-not-allowed text-neutral-600'"
            x-bind:title="visionAvailable ? 'Attach screenshot' : 'Pick a vision-capable model to attach images'"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-800 bg-neutral-900 transition"
            aria-label="Attach screenshot"
        >
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5" aria-hidden="true">
                <rect x="3" y="4" width="14" height="12" rx="1.5"/>
                <path d="M3 13l4-4 3 3 3-2 4 4" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="13" cy="8" r="1.25" fill="currentColor" stroke="none"/>
            </svg>
            <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="hidden"
                x-bind:disabled="!visionAvailable"
                x-on:change="Array.from($event.target.files || []).forEach((file) => attachFile(file)); $event.target.value = '';"
            />
        </label>
        <button
            type="button"
            wire:click="cancelInsert"
            x-on:click="clearAttachments()"
            class="h-8 rounded-md border border-neutral-700 px-2 text-xs text-neutral-300 hover:border-neutral-500"
        >Cancel</button>
    </div>
</form>
