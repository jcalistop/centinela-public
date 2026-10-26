@props(['dataType', 'id', 'documentHubContext' => null])
@can('read', app($dataType->model_name))
    @php
        $showUrl = route('voyager.' . $dataType->slug . '.show', $id);
        if (is_array($documentHubContext)) {
            $showUrl = \App\Support\Voyager\DocumentHubRedirect::appendHubQueryToUrl($showUrl, $documentHubContext);
        }
    @endphp
    <a href="{{ $showUrl }}" class="btn btn-sm btn-warning"><i class="voyager-eye"></i>
        Ver
    </a>
@endcan
