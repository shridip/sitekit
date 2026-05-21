<x-guest-layout>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
        <div>
            <x-authentication-card-logo />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                {{ __('Two-Factor Authentication') }}
            </h2>

            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                {{ __('Please enter the authentication code from your authenticator app, or use one of your recovery codes.') }}
            </p>

            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-800 rounded-md">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('auth.otp-verify') }}">
                @csrf

                <div class="mb-4">
                    <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('Code') }}
                    </label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        autofocus
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        placeholder="{{ __('Enter 6-digit code or recovery code') }}"
                    />
                </div>

                <div class="flex items-center justify-between mt-6">
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 underline">
                        {{ __('Back to login') }}
                    </a>

                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        {{ __('Verify') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
