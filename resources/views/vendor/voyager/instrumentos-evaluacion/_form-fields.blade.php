@php
    $isEdit = $isEdit ?? false;
    $g = old('measurement_granularity', $indicatorType->measurement_granularity ?? 'establishment');
    $e = old('estado', isset($indicatorType) ? (int) $indicatorType->estado : 0);
    $iconVal = old('icon_class', $indicatorType->icon_class ?? 'voyager-book');
    $metasRouteSlugs = $metasRouteSlugs ?? \App\Models\IndicatorType::configMetasRouteSlugs();
    $routesInUse = is_array($routesInUse ?? null) ? $routesInUse : [];
    $selectableMetasSlugs = array_values(array_filter(
        $metasRouteSlugs,
        static fn (string $slug): bool => ! in_array($slug, $routesInUse, true)
    ));
    $iconPickerClasses = array_values(array_unique(array_filter(
        config('centinela.instrument_voyager_icon_picker_classes', []),
        static fn ($c) => is_string($c) && $c !== ''
    )));
@endphp

{{-- Identidad --}}
<div class="it-type-edit__section">
    <h4><i class="voyager-tag"></i>Identidad y texto</h4>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group @error('clave_indicador') has-error @enderror">
                <label for="clave_indicador">Clave <span class="text-danger" title="Requerido">*</span></label>
                <input type="text" name="clave_indicador" id="clave_indicador" class="form-control" autocomplete="off"
                    value="{{ old('clave_indicador', $indicatorType->clave_indicador ?? '') }}" required
                    placeholder="p. ej. metas-19664">
                <p class="help-block">Nombre que aparecerá en el menú.</p>
                @error('clave_indicador')
                    <span class="help-block text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group @error('nombre_indicador') has-error @enderror">
                <label for="nombre_indicador">Nombre visible <span class="text-danger" title="Requerido">*</span></label>
                <input type="text" name="nombre_indicador" id="nombre_indicador" class="form-control" autocomplete="off"
                    value="{{ old('nombre_indicador', $indicatorType->nombre_indicador ?? '') }}" required
                    placeholder="Nombre en menú y pantallas">
                <p class="help-block">Nombre que aparecerá en los títulos.</p>
                @error('nombre_indicador')
                    <span class="help-block text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>
    <div class="form-group @error('descripcion_indicador') has-error @enderror">
        <label for="descripcion_indicador">Descripción <span class="text-muted" style="font-weight:normal">(opcional)</span></label>
        <textarea name="descripcion_indicador" id="descripcion_indicador" class="form-control" rows="3"
            placeholder="Contexto normativo o notas internas (no afecta rutas)">{{ old('descripcion_indicador', $indicatorType->descripcion_indicador ?? '') }}</textarea>
        @error('descripcion_indicador')
            <span class="help-block text-danger">{{ $message }}</span>
        @enderror
    </div>
</div>

{{-- URL y presentación en admin --}}
<div class="it-type-edit__section">
    <h4><i class="voyager-compass"></i> URL y apariencia en el admin</h4>
    <div class="form-group @error('route') has-error @enderror">
        <label for="route">Slug de URL bajo <code>/admin/</code> <span class="text-danger" title="Requerido">*</span></label>
        @if ($isEdit)
            <input type="hidden" name="route" value="{{ old('route', $indicatorType->route) }}">
            <p class="form-control-static" style="font-family:monospace; margin-bottom:0; padding:8px 0">
                <span class="text-muted">/admin/</span><code>{{ $indicatorType->route }}</code>
            </p>
            <p class="help-block" style="margin-top:4px">El slug no se puede cambiar aquí. Es el segmento de la URL y
                clave de resolución del instrumento.</p>
        @else
            <div class="input-group">
                <span class="input-group-addon" style="font-family:monospace; font-size:12px">/admin/</span>
                <select name="route" id="route" class="form-control" required style="font-family:monospace">
                    <option value="" @selected(old('route', '') === '')>—
                        Elegir instrumento —</option>
                    @foreach ($metasRouteSlugs as $slug)
                        @php $inUse = in_array($slug, $routesInUse, true); @endphp
                        <option value="{{ $slug }}" @selected(old('route', '') === $slug) @if ($inUse) disabled @endif>
                            {{ $slug }}{{ $inUse ? ' (en uso)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <p class="help-block">Opciones fijas desde <code>config('centinela.metas_indicator_type_route_slugs')</code>
                (Metas por ley y <strong>ADP</strong>) para evitar errores de escritura. Las ya asignadas a otro
                instrumento aparecen deshabilitadas. Rutas Laravel: Metas usan <code>metas.instrument.*</code>; ADP
                usará <code>adp.instrument.*</code> (no el prefijo dinámico <code>metas-[0-9]+</code> de
                <code>routes/web.php</code>).</p>
            @if (count($metasRouteSlugs) === 0)
                <p class="help-block text-warning"><i class="voyager-warning"></i> No hay slugs configurados en
                    <code>centinela.metas_indicator_type_route_slugs</code>.</p>
            @elseif (count($selectableMetasSlugs) === 0)
                <p class="help-block text-warning"><i class="voyager-warning"></i> Todos los slugs de la lista ya están
                    en uso. Elimina o corrige un duplicado o amplía la configuración.</p>
            @endif
        @endif
        @error('route')
            <span class="help-block text-danger">{{ $message }}</span>
        @enderror
    </div>
    <div class="form-group @error('icon_class') has-error @enderror">
        <label for="icon_class">Icono en menú <span class="text-muted" style="font-weight:normal">(Voyager)</span></label>
        <div class="input-group">
            <input type="text" name="icon_class" id="icon_class" class="form-control"
                value="{{ $iconVal }}" placeholder="voyager-book" autocomplete="off"
                @if (count($iconPickerClasses) > 0) list="it-icon-class-suggestions" @endif>
            <span class="input-group-addon" id="it-icon-preview" style="min-width:40px" title="Vista previa">
                <i class="{{ $iconVal }}" style="position:relative; top:1px" aria-hidden="true"></i>
            </span>
        </div>
        @if (count($iconPickerClasses) > 0)
            <datalist id="it-icon-class-suggestions">
                @foreach ($iconPickerClasses as $ic)
                    <option value="{{ $ic }}"></option>
                @endforeach
            </datalist>
            <p class="help-block" style="margin-bottom:6px">Galería (clic para aplicar; puedes seguir escribiendo otra
                clase en el campo si no está en la lista):</p>
            <div class="it-icon-picker__grid" role="group" aria-label="Iconos Voyager sugeridos">
                @foreach ($iconPickerClasses as $ic)
                    <button type="button" class="btn btn-default btn-sm it-icon-pick" data-class="{{ $ic }}"
                        title="{{ $ic }}" aria-label="{{ $ic }}">
                        <i class="{{ $ic }}" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>
        @endif
        <p class="help-block">Se muestra junto al nombre del instrumento en el menú lateral del admin.</p>
        @error('icon_class')
            <span class="help-block text-danger">{{ $message }}</span>
        @enderror
    </div>
</div>

{{-- Medición y publicación --}}
<div class="it-type-edit__section">
    <h4><i class="voyager-lab"></i> Medición y publicación</h4>
    <div class="form-group @error('measurement_granularity') has-error @enderror">
        <label for="measurement_granularity">Granularidad de medición <span class="text-danger" title="Requerido">*</span></label>
        <select name="measurement_granularity" id="measurement_granularity" class="form-control" required>
            @foreach (\App\Models\IndicatorType::measurementGranularityOptions() as $val => $label)
                <option value="{{ $val }}" @selected($g === $val)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="help-block">En <strong>Metas 20.707</strong> (<code>internal_unit</code>) la UI expone unidades,
            Excel y carga por unidad interna. <strong>Compromiso de Gestión</strong> y <strong>ADP</strong> usan estos
            valores para documentar el modelo de medición; el comportamiento específico de cada módulo se implementa
            aparte.</p>
        @error('measurement_granularity')
            <span class="help-block text-danger">{{ $message }}</span>
        @enderror
    </div>
    <div class="form-group @error('estado') has-error @enderror">
        <label for="estado">Visibilidad <span class="text-danger" title="Requerido">*</span></label>
        <select name="estado" id="estado" class="form-control" required>
            <option value="0" @selected((int) $e === 0)>Inactivo (oculto para no admin)</option>
            <option value="1" @selected((int) $e === 1)>Activo (visible según permisos)</option>
        </select>
        <p class="help-block">Los administradores suelen ver todos; el resto solo si el instrumento está
            <em>activo</em> y con permisos adecuados. El orden en el menú lateral se gestiona con el componente de menú,
            no desde este formulario.</p>
        @error('estado')
            <span class="help-block text-danger">{{ $message }}</span>
        @enderror
    </div>
</div>
