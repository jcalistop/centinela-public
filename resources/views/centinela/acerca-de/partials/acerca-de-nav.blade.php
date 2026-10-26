<nav class="acerca-de-subnav" aria-label="Secciones ¿Qué es Centinela?">
    <ul class="nav nav-tabs" role="tablist">
        @foreach ($navPages as $item)
            <li role="presentation" class="{{ ($currentSlug ?? null) === $item['slug'] ? 'active' : '' }}">
                <a href="{{ $item['route'] }}" role="tab">
                    @if (!empty($item['icon']))
                        <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    @endif
                    @if (!empty($item['label_html']))
                        {!! $item['label'] !!}
                    @else
                        {{ $item['label'] }}
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
