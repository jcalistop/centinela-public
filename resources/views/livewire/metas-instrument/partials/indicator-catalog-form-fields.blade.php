<div class="row">
    <div class="col-md-4">
        <div class="form-group @error('clave') has-error @enderror">
            <label>Clave <span class="text-danger">*</span></label>
            <input wire:model="clave" type="text" class="form-control" placeholder="Ej: MET-001" />
            @error('clave')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group @error('nombre') has-error @enderror">
            <label>Nombre <span class="text-danger">*</span></label>
            <input wire:model="nombre" type="text" class="form-control" />
            @error('nombre')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

@if (($indicatorType->route ?? '') === 'metas-19664')
    <div class="row">
        <div class="col-md-4">
            <div class="form-group @error('prioridad') has-error @enderror">
                <label>Prioridad <span class="text-danger">*</span></label>
                <select wire:model="prioridad" class="form-control">
                    @foreach ($prioridadOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="help-block">Visible como etiqueta en el convenio del establecimiento cuando no es «Sin
                    prioridad».</p>
                @error('prioridad')
                    <span class="help-block">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>
@endif

<div class="form-group">
    <label>Objetivo y/o descripción del indicador</label>
    <div wire:ignore class="metas-objetivo-quill-wrap">
        <div data-metas-objetivo-quill class="metas-objetivo-quill-editor"></div>
    </div>
    <input type="hidden" wire:model="objetivo" data-metas-objetivo-quill-input />
</div>

<hr />
<h5>Meta nacional</h5>

<div class="row">
    <div class="col-md-4">
        <div class="form-group @error('metaValor') has-error @enderror">
            <label>Valor meta <span class="text-danger">*</span></label>
            <input wire:model="metaValor" type="number" step="0.01" class="form-control" />
            @error('metaValor')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group @error('metaOperador') has-error @enderror">
            <label>Operador <span class="text-danger">*</span></label>
            <select wire:model="metaOperador" class="form-control">
                <option value=">=">&ge; (mayor o igual)</option>
                <option value=">">&gt; (mayor que)</option>
                <option value="<=">&le; (menor o igual)</option>
                <option value="<">&lt; (menor que)</option>
                <option value="=">= (igual a)</option>
            </select>
            @error('metaOperador')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-5">
        <div class="form-group">
            <label>Descripción de la meta</label>
            <textarea wire:model="metaAnualDescripcion" class="form-control" rows="2"></textarea>
        </div>
    </div>
</div>

<hr />
<h5>Configuración técnica</h5>

<div class="row">
    <div class="col-md-12">
        <p class="help-block text-muted" style="margin-bottom: 12px;">
            Estos umbrales afectan al <strong>cumplimiento ponderado</strong>: por debajo del inferior → 0&nbsp;%;
            en o por encima del superior → 100&nbsp;% de la ponderación; entre ambos → proporcional al cumplimiento.
            Si inferior y superior son iguales, el corte es binario.
            El cumplimiento mostrado no aplica umbrales (máximo 100&nbsp;%).
        </p>
    </div>
    <div class="col-md-6">
        <div class="form-group @error('umbralInferior') has-error @enderror">
            <label>Umbral inferior (%) <span class="text-danger">*</span></label>
            <input wire:model="umbralInferior" type="number" min="0" max="100" step="0.01"
                class="form-control" />
            <p class="help-block">Por debajo de este umbral el cumplimiento ponderado será 0&nbsp;%.</p>
            @error('umbralInferior')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group @error('umbralSuperior') has-error @enderror">
            <label>Umbral superior (%) <span class="text-danger">*</span></label>
            <input wire:model="umbralSuperior" type="number" min="0" max="100" step="0.01"
                class="form-control" />
            <p class="help-block">Al alcanzar o superar este umbral, el cumplimiento ponderado será igual al
                100&nbsp;% de la ponderación.</p>
            @error('umbralSuperior')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group @error('unidadMedida') has-error @enderror">
            <label>Unidad de medida <span class="text-danger">*</span></label>
            <select wire:model="unidadMedida" class="form-control">
                @foreach ($unidadMedidaOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('unidadMedida')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group @error('decimales') has-error @enderror">
            <label>Decimales <span class="text-danger">*</span></label>
            <input wire:model="decimales" type="number" min="0" max="10" class="form-control" />
            @error('decimales')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group @error('decimalesModo') has-error @enderror">
            <label>Modo decimales <span class="text-danger">*</span></label>
            <select wire:model="decimalesModo" class="form-control">
                <option value="redondear">Redondear</option>
                <option value="truncar">Truncar</option>
            </select>
            @error('decimalesModo')
                <span class="help-block">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="form-group @error('nivelAplicacion') has-error @enderror">
    <label>Nivel de aplicación <span class="text-danger">*</span></label>
    <textarea wire:model="nivelAplicacion" class="form-control" rows="2"></textarea>
    @error('nivelAplicacion')
        <span class="help-block">{{ $message }}</span>
    @enderror
</div>

<div class="form-group @error('exclusiones') has-error @enderror">
    <label>Exclusiones</label>
    <textarea wire:model="exclusiones" class="form-control" rows="2"
        placeholder="Ej: establecimientos sin urgencia"></textarea>
    @error('exclusiones')
        <span class="help-block">{{ $message }}</span>
    @enderror
</div>

<div class="form-group @error('selectedAmbitoIds') has-error @enderror">
    @include('partials.indicators.ambitos-multi-select', [
        'pickerMode' => 'livewire',
        'indicatorAmbitoOptions' => $indicatorAmbitoOptions,
    ])
    @error('selectedAmbitoIds')
        <span class="help-block">{{ $message }}</span>
    @enderror
</div>
