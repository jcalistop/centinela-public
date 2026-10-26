@can('delete', app($dataType->model_name))
    <a href="#" onclick="confirmDelete('delete-{{ $dataType->slug }}-{{ $id }}')"
        class="btn btn-sm btn-danger">
        <i class="voyager-trash"></i> {{ $etiqueta }}
    </a>
    <form action="{{ route('voyager.' . $dataType->slug . '.destroy', $id) }}" method="POST"
        id="delete-{{ $dataType->slug }}-{{ $id }}">
        {{ method_field('DELETE') }}
        {{ csrf_field() }}
        @if (is_array($documentHubContext) && is_string($documentHubContext['hub'] ?? null) && $documentHubContext['hub'] !== '')
            <input type="hidden" name="centinela_document_hub" value="{{ $documentHubContext['hub'] }}">
        @endif
        @if (is_array($documentHubContext) &&
                is_string($documentHubContext['metas_route'] ?? null) &&
                $documentHubContext['metas_route'] !== '')
            <input type="hidden" name="centinela_metas_route" value="{{ $documentHubContext['metas_route'] }}">
        @endif
    </form>
@endcan
@push('javascript')
    <script>
        function confirmDelete(formId) {
            const displayName = "{{ $dataType->display_name_plural }}";
            const deleteId = "{{ $id }}"
            const confirmed = window.confirm(
                `¿Estás seguro de que deseas eliminar este registro de ${displayName}? \n (ID: ${deleteId})`);
            if (confirmed) {
                document.getElementById(formId).submit();
            }
        }
    </script>
@endpush
