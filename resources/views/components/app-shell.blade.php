@props([
    'active' => null,
])

@php
    $shellUser = auth()->user();
    $shellInitials = collect(preg_split('/\s+/', trim((string) ($shellUser?->name ?? ''))) ?: [])
        ->filter()
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $shellInitials = $shellInitials !== ''
        ? $shellInitials
        : mb_strtoupper(mb_substr((string) ($shellUser?->email ?? 'U'), 0, 1));

    $navLink = fn (bool $on) => $on
        ? 'rounded-lg bg-white/[0.07] px-3 py-1.5 text-xs font-semibold text-zinc-100 ring-1 ring-white/10'
        : 'rounded-lg px-3 py-1.5 text-xs font-medium text-zinc-400 transition hover:bg-white/5 hover:text-zinc-100';
@endphp

<div {{ $attributes->class('relative min-h-screen bg-[#0b0b0b] font-sans text-zinc-100 antialiased selection:bg-cyan-400/30 selection:text-cyan-50') }}>
    {{-- ambient glow --}}
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute left-64 top-0 h-80 w-[36rem] rounded-full bg-cyan-500/10 blur-3xl"></div>
        <div class="absolute right-16 top-24 h-96 w-[34rem] rounded-full bg-blue-600/10 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/2 h-64 w-[44rem] -translate-x-1/2 rounded-full bg-indigo-500/[0.06] blur-3xl"></div>
    </div>

    {{-- top bar --}}
    <header class="fixed inset-x-0 top-0 z-40 h-14 border-b border-white/10 bg-[#0d0d0d]/95 backdrop-blur-xl">
        <div class="flex h-full items-center justify-between gap-3 px-4 sm:px-5">
            <div class="flex min-w-0 items-center gap-4">
                <a href="{{ route('projects.index') }}" wire:navigate class="flex shrink-0 items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg border border-cyan-400/30 bg-cyan-400/10 text-sm font-black text-cyan-300 shadow-[0_0_24px_rgba(34,211,238,0.18)]">T</span>
                    <span class="text-sm font-bold tracking-tight text-white">TwMaker</span>
                </a>
                @isset($breadcrumb)
                    <div class="hidden h-5 w-px bg-white/10 sm:block"></div>
                    <div class="hidden min-w-0 items-center gap-2 text-xs text-zinc-500 sm:flex">
                        {{ $breadcrumb }}
                    </div>
                @endisset
            </div>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Main navigation">
                <a href="{{ route('projects.index') }}" wire:navigate class="{{ $navLink($active === 'projects') }}">Projects</a>
                <a href="{{ route('setup.llm') }}" wire:navigate class="{{ $navLink($active === 'setup') }}">LLM setup</a>
            </nav>

            <div class="flex items-center gap-2">
                @isset($actions)
                    {{ $actions }}
                    <div class="hidden h-5 w-px bg-white/10 sm:block"></div>
                @endisset
                <div class="flex items-center gap-2">
                    <span class="hidden text-right text-xs leading-tight text-zinc-400 sm:block">
                        <span class="block max-w-[10rem] truncate font-medium text-zinc-200">{{ $shellUser?->name ?: 'Account' }}</span>
                        <span class="block max-w-[10rem] truncate text-zinc-500">{{ $shellUser?->email }}</span>
                    </span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-xs font-bold text-white shadow-lg shadow-blue-500/20">{{ $shellInitials }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Log out" aria-label="Log out" class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 bg-[#0b0b0b] text-zinc-400 transition hover:border-white/20 hover:text-white">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    @isset($sidebar)
        <div class="pt-14 lg:grid lg:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="hidden border-r border-white/10 bg-[#121212]/80 lg:block">
                <div class="sticky top-14 flex max-h-[calc(100vh-3.5rem)] flex-col overflow-y-auto">
                    {{ $sidebar }}
                </div>
            </aside>
            <main class="min-w-0">
                {{ $slot }}
            </main>
        </div>
    @else
        <main class="pt-14">
            {{ $slot }}
        </main>
    @endisset
</div>
