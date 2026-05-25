<x-layouts.app title="Log in">
    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-4 py-10 text-neutral-100">
        <section class="w-full max-w-sm rounded-lg border border-neutral-800 bg-neutral-900 p-5 shadow-2xl shadow-black/30">
            <div>
                <a href="{{ route('projects.index') }}" class="text-lg font-bold tracking-normal text-white">TwMaker</a>
                <p class="mt-1 text-sm text-neutral-400">Log in to continue building.</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="mt-5 flex flex-col gap-3">
                @csrf

                <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                    Email
                    <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="h-10 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                    @error('email') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                    Password
                    <input name="password" type="password" autocomplete="current-password" required class="h-10 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                    @error('password') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>

                <label class="inline-flex items-center gap-2 text-sm text-neutral-300">
                    <input name="remember" type="checkbox" value="1" class="h-4 w-4 rounded border-neutral-700 bg-neutral-950 text-cyan-400">
                    Remember me
                </label>

                <button type="submit" class="mt-1 inline-flex h-10 items-center justify-center rounded-md bg-cyan-400 px-4 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">
                    Log in
                </button>
            </form>

            <p class="mt-4 text-sm text-neutral-400">
                Need an account?
                <a href="{{ route('register') }}" class="font-medium text-cyan-300 hover:text-cyan-200">Register</a>
            </p>
        </section>
    </main>
</x-layouts.app>
