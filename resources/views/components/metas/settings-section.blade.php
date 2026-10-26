@props(['title', 'icon' => null, 'tone' => 'default', 'intro' => null, 'wide' => false])

<div @class([
    'metas-settings-section-wrap',
    'metas-settings-section-wrap--wide' => $wide,
    'metas-settings-section-wrap--' . $tone => $tone !== 'default',
])>
    <x-centinela.compass.section :title="$title" :icon="$icon" class="metas-settings-section">
        @if ($intro)
            <p class="metas-settings-section__intro text-muted">{{ $intro }}</p>
        @endif

        {{ $slot }}

        @isset($actions)
            <div class="metas-settings-actions">
                {{ $actions }}
            </div>
        @endisset
    </x-centinela.compass.section>
</div>
