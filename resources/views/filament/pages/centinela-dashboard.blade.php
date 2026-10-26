@php
    $voyagerLinkInUserMenu = Auth::user() instanceof \App\Models\User && Auth::user()->hasRole('admin');
@endphp

<x-filament-panels::page>
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
            Panel Filament en construcción
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            Este shell se reconstruye de forma gradual desde Voyager. Mientras una superficie no esté
            habilitada aquí, usa la administración clásica.
        </p>
        @unless ($voyagerLinkInUserMenu)
            <p class="mt-4">
                <a href="{{ url('/admin') }}"
                    class="text-sm font-medium text-primary-600 underline decoration-primary-600/30 hover:decoration-primary-600">
                    Ir a Voyager (/admin)
                </a>
            </p>
        @endunless
    </div>
</x-filament-panels::page>
