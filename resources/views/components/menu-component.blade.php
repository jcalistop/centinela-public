<ul class="nav navbar-nav">
    @foreach ($dataTypeContent as $item)
        @php
            $href = '';
            if ($item->route) {
                if ($item->view === null && (string) $item->route === 'compromisos') {
                    $href = route('voyager.compromisos.index');
                } elseif ($item->view === null && (string) $item->route === 'adp') {
                    $href = route('adp.instrument.index');
                } elseif ($item->view === null && str_starts_with((string) $item->route, 'metas-')) {
                    $href = route('metas.instrument.index', $item);
                } elseif (str_ends_with((string) $item->route, '.index')) {
                    $href = route($item->route);
                } else {
                    $href = route($item->route, ['id' => $item->id]);
                }
            }
        @endphp
        <li><a href="{{ $href }}">
                <span class="icon {{ $item->icon_class }}"></span>
                <span class="title">{{ $item->{$itemField} }}</span>
            </a>
        </li>
    @endforeach
</ul>
