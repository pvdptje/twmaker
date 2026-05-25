<x-layouts.app title="Register">
    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-4 py-10 text-neutral-100">
        <section class="w-full max-w-sm rounded-lg border border-neutral-800 bg-neutral-900 p-5 shadow-2xl shadow-black/30">
            <div>
                <a href="{{ route('projects.index') }}" class="text-lg font-bold tracking-normal text-white">TwMaker</a>
                <p class="mt-1 text-sm text-neutral-400">Create an account to start building.</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="mt-5 flex flex-col gap-3">
                @csrf

                <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                    Name
                    <input name="name" value="{{ old('name') }}" autocomplete="name" required autofocus class="h-10 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                    @error('name') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                    Email
                    <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="h-10 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                    @error('email') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                    Password
                    <input name="password" type="password" autocomplete="new-password" required class="h-10 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                    @error('password') <span class="text-xs text-rose-300">{{ $message }}</span> @enderror
                    <span class="text-xs text-neutral-500">Use at least 12 characters with uppercase, lowercase, numbers, and symbols.</span>
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-neutral-300">
                    Confirm password
                    <input name="password_confirmation" type="password" autocomplete="new-password" required class="h-10 rounded-md border border-neutral-700 bg-neutral-950 px-3 text-sm text-white outline-none focus:border-cyan-400">
                </label>

                <button type="submit" class="mt-1 inline-flex h-10 items-center justify-center rounded-md bg-cyan-400 px-4 text-sm font-semibold text-neutral-950 hover:bg-cyan-300">
                    Create account
                </button>
            </form>

            <p class="mt-4 text-sm text-neutral-400">
                Already have an account?
                <a href="{{ route('login') }}" class="font-medium text-cyan-300 hover:text-cyan-200">Log in</a>
            </p>
        </section>
    </main>
</x-layouts.app>
