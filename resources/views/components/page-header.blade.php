@props([
    'title',
    'eyebrow' => null,
    'eyebrowHref' => null,
    'subtitle' => null,
])

<header {{ $attributes->class('flex flex-col gap-4 border-b border-neutral-800 px-2 py-4 sm:px-0 lg:flex-row lg:items-center lg:justify-between') }}>
    <div class="flex min-w-0 items-center gap-3 sm:gap-4">
        <a href="{{ route('projects.index') }}" wire:navigate class="shrink-0 text-lg font-bold tracking-normal text-white">
            TwMaker
        </a>
        <div class="h-8 w-px shrink-0 bg-neutral-800"></div>
        <div class="min-w-0">
            @if ($eyebrow !== null && $eyebrowHref !== null)
                <a href="{{ $eyebrowHref }}" wire:navigate class="mb-1 block text-[11px] font-semibold uppercase leading-none tracking-widest text-cyan-400 hover:text-cyan-300">{{ $eyebrow }}</a>
            @elseif ($eyebrow !== null)
                <p class="mb-1 text-[11px] font-semibold uppercase leading-none tracking-widest text-cyan-400">{{ $eyebrow }}</p>
            @endif

            <h1 class="truncate text-2xl font-bold leading-none text-white">{{ $title }}</h1>

            @if ($subtitle !== null && $subtitle !== '')
                <p class="mt-1 truncate text-[13px] text-neutral-400">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if (trim($actions ?? '') !== '')
        <div class="flex shrink-0 flex-wrap items-center gap-3">
            {{ $actions }}
        </div>
    @endif
</header>
