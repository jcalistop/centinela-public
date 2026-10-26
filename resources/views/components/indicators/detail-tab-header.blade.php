@props(['title', 'description' => null, 'variant' => 'spotlight'])

@if ($variant === 'ficha-section')
    <div {{ $attributes->class(['metas-ficha-tab-intro']) }}>
        @if (isset($actions))
            <div class="metas-ficha-section__head">
                <h5 class="metas-ficha-section__heading metas-ficha-section__heading--toolbar">{{ $title }}</h5>
                <div class="metas-ficha-tab-intro__actions">
                    {{ $actions }}
                </div>
            </div>
        @else
            <h5 class="metas-ficha-section__heading">{{ $title }}</h5>
        @endif
        @if ($description)
            <p class="metas-ficha-tab-intro__description text-muted">{{ $description }}</p>
        @endif
    </div>
@else
    <div {{ $attributes->class(['metas-tab-spotlight']) }}>
        <div @class(['clearfix' => isset($actions)])>
            <h4 @class(['metas-tab-spotlight__title', 'pull-left' => isset($actions)])>{{ $title }}</h4>
            @isset($actions)
                <div class="pull-right metas-tab-spotlight__actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
        @if ($description)
            <p class="metas-tab-spotlight__description">{{ $description }}</p>
        @endif
    </div>
@endif
