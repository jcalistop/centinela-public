<div class="panel panel-bordered metas-indicator-catalog-sort-panel">
    <div class="panel-heading" style="background:#f5f5f5; border-bottom:1px solid #e5e5e5;">
        <p class="metas-indicator-catalog-sort-hint" style="margin:0; color:#555;">
            {{ __('voyager::generic.drag_drop_info') }}
        </p>
    </div>
    <div class="panel-body" style="padding:30px;">
        <div class="dd" wire:ignore id="metas-indicator-catalog-sort">
            <ol class="dd-list">
                @foreach ($indicators as $indicator)
                    <li class="dd-item" data-id="{{ $indicator->id }}">
                        <div class="dd-handle" style="height:inherit">
                            <div class="clearfix">
                                <strong><code>{{ $indicator->clave }}</code> {{ $indicator->nombre }}</strong>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
        @if ($indicators->isEmpty())
            <p class="text-muted" style="margin-top:1rem;">No hay indicadores para ordenar.</p>
        @endif
    </div>
</div>
