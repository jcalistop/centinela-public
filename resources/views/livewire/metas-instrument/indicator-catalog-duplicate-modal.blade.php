<div>
    {{-- Modal Duplicar indicador --}}
    @if ($showDuplicateModal)
        <div id="centinela-metas-modal-duplicate" class="modal fade in centinela-metas-catalog-modal" tabindex="-1"
            role="dialog" wire:key="metas-catalog-modal-duplicate"
            style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;">
            <div class="modal-dialog" role="document" style="max-width: 520px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeDuplicateModal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">
                            <i class="voyager-copy"></i> Duplicar indicador
                        </h4>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted" style="margin-bottom: 1rem;">
                            Origen: <strong>{{ $duplicateSourceLabel }}</strong>
                        </p>

                        {{-- Año destino --}}
                        <div class="form-group">
                            <label for="dup-year">Año destino <span class="text-danger">*</span></label>
                            <select id="dup-year" wire:model="duplicateYearId" class="form-control">
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y->id }}">{{ $y->ano }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tipo de meta destino --}}
                        <div class="form-group">
                            <label for="dup-type">Tipo de meta destino <span class="text-danger">*</span></label>
                            <select id="dup-type" wire:model="duplicateIndicatorTypeId" class="form-control">
                                @foreach ($indicatorTypeOptions as $t)
                                    <option value="{{ $t->id }}">{{ $t->nombre_indicador }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Código (clave) --}}
                        <div class="form-group @error('duplicateClave') has-error @enderror">
                            <label for="dup-clave">Código (clave) <span class="text-danger">*</span></label>
                            <input id="dup-clave" type="text" wire:model.live.debounce.300ms="duplicateClave"
                                class="form-control" placeholder="Ej: MET-NUEVO-001" autocomplete="off" />
                            @if ($duplicateClaveExists)
                                <p class="help-block text-danger">
                                    <i class="voyager-warning"></i> {{ $duplicateClaveError }}
                                </p>
                            @endif
                            @error('duplicateClave')
                                <p class="help-block text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <p class="help-block">
                            <i class="voyager-info-circled"></i>
                            Se copiarán los atributos del indicador, campos de datos, fórmula y ámbitos.
                            No se copian datos operativos (carga mensual, configuraciones, referentes).
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" wire:click="closeDuplicateModal">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="duplicateIndicator"
                            wire:loading.attr="disabled" @if ($duplicateClaveExists || trim($duplicateClave) === '') disabled @endif>
                            <span wire:loading wire:target="duplicateIndicator">
                                <i class="voyager-loader"></i>
                            </span>
                            Duplicar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
