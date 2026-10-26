@php
    use App\Support\Voyager\DocumentBread;
@endphp

@can('add', \App\Models\Document::class)
    @php
        $breadSlug = DocumentBread::slug();
        $hubName = $hub ?? '';
        $query = ['hub' => $hubName];
        if (($indicatorTypeId ?? null) !== null) {
            $query['indicator_type_id'] = $indicatorTypeId;
        }
        if ($hubName === 'metas' && is_string($metasRoute ?? null) && ($metasRoute ?? '') !== '') {
            $query['metas_route'] = $metasRoute;
        }
        $createUrl = route('voyager.' . $breadSlug . '.create', $query);
    @endphp

    <p style="margin:0 0 12px">
        <a href="{{ $createUrl }}" class="btn btn-success btn-sm">
            <i class="voyager-plus"></i> Agregar documento
        </a>
    </p>
@endcan
