@props([
    'title' => '',
    'icon' => 'voyager-window-list',
])

<section {{ $attributes->merge(['class' => 'cen-dashboard-block metas-hub-compass-block']) }}>
    @if ($title !== '')
        <header class="cen-dashboard-block__head">
            <div class="cen-dashboard-block__head-main">
                <h3 class="cen-dashboard-block__title">
                    <i class="{{ $icon }}"></i> {{ $title }}
                </h3>
            </div>
        </header>
    @endif
    <div class="cen-dashboard-block__body">
        {{ $slot }}
    </div>
</section>
