{{-- Modal de confirmación — eliminación en cascada indicador COMGES (SDD-142) --}}
<div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"
                    aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="voyager-trash"></i> ¿Está seguro?</h4>
            </div>
            <div class="modal-body">
                <p class="lead" style="margin-top: 0;">
                    Va a eliminar el indicador
                    <strong>{{ $dataTypeContent->clave }}</strong> —
                    {{ $dataTypeContent->nombre }}.
                </p>
                <p>Esta acción es <strong>irreversible</strong> y eliminará permanentemente los siguientes registros:
                </p>
                <ul class="list-unstyled comges-indicator-deletion-preview" style="margin-bottom: 0;">
                    @foreach ($deletionPreview['items'] as $item)
                        @if ($item['count'] > 0)
                            <li>
                                <i class="voyager-dot"></i>
                                {{ $item['label'] }}:
                                <strong>{{ number_format($item['count'], 0, ',', '.') }}</strong>
                            </li>
                        @endif
                    @endforeach
                </ul>
                @if (($deletionPreview['total'] ?? 0) > 1)
                    <p class="text-muted" style="margin-top: 1rem; margin-bottom: 0;">
                        Total de registros afectados:
                        <strong>{{ number_format($deletionPreview['total'], 0, ',', '.') }}</strong>
                    </p>
                @endif
            </div>
            <div class="modal-footer">
                <form action="{{ route('voyager.' . $dataType->slug . '.destroy', $dataTypeContent->getKey()) }}"
                    id="delete_form" method="POST">
                    {{ method_field('DELETE') }}
                    {{ csrf_field() }}
                    <button type="button" class="btn btn-default pull-left"
                        data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                    <input type="submit" class="btn btn-danger pull-right delete-confirm"
                        value="Eliminar definitivamente">
                </form>
            </div>
        </div>
    </div>
</div>
