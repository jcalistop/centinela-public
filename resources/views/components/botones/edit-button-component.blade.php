@props(['dataType', 'id', 'documentHubContext' => null])
@can('edit', app($dataType->model_name))
    @php
        $editQuery = [];
        if (is_array($documentHubContext)) {
            $hub = $documentHubContext['hub'] ?? null;
            if (is_string($hub) && $hub !== '') {
                $editQuery['hub'] = $hub;
            }
            if (($documentHubContext['indicator_type_id'] ?? null) !== null) {
                $editQuery['indicator_type_id'] = $documentHubContext['indicator_type_id'];
            }
            if (
                $hub === 'metas' &&
                is_string($documentHubContext['metas_route'] ?? null) &&
                $documentHubContext['metas_route'] !== ''
            ) {
                $editQuery['metas_route'] = $documentHubContext['metas_route'];
            }
        }
        $editUrl = route('voyager.' . $dataType->slug . '.edit', $id);
        if ($editQuery !== []) {
            $editUrl .= '?' . http_build_query($editQuery);
        }
    @endphp
    <a href="{{ $editUrl }}" class="btn btn-sm btn-primary"><i class="glyphicon glyphicon-edit"></i>
        Editar
    </a>
@endcan
