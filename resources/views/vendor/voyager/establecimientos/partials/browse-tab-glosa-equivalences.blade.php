@php
    use App\Support\Cen\CenCatalogGlosaResolver;
    use App\Support\Cen\CenEstablishmentsUiCopy;

    $catalogEnumOptions = $catalogEnumOptions ?? [];
@endphp

@if (!empty($canManageGlosaEquivalences))
    <div class="cen-read-detail" style="margin-top:24px;">
        <div class="cen-read-spotlight">
            <p class="cen-read-spotlight__label">Equivalencias de catálogo</p>
            <p class="cen-read-spotlight__value">
                Textos del catálogo nacional → valores en Centinela
                @include('voyager::establecimientos.partials.help-icon', [
                    'text' => CenEstablishmentsUiCopy::HELP_EQUIVALENCIAS,
                ])
            </p>
            <p class="cen-read-spotlight__hint">
                Cuando datos.gob.cl usa una redacción distinta a la de Centinela, aquí se define cómo traducirla en la
                próxima sincronización. También existen reglas base del sistema; estas equivalencias las complementan.
            </p>
        </div>

        <div class="cen-read-section">
            <div class="cen-read-section__head cen-read-section__head--split">
                <div class="cen-read-section__head-main">
                    <i class="voyager-warning"></i>
                    <h4 class="cen-read-section__title">Incidencias pendientes de equivalencia</h4>
                </div>
                @if ($unmappedEnumIssues !== null && $unmappedEnumIssues->total() > 0)
                    <span class="cen-read-count-badge cen-read-count-badge--muted pull-right">
                        {{ $unmappedEnumIssues->total() }} pendientes
                    </span>
                @endif
            </div>
            @if ($unmappedEnumIssues === null || $unmappedEnumIssues->isEmpty())
                <div class="cen-read-empty">
                    <p><strong>Sin incidencias pendientes</strong></p>
                    <p style="font-size:13px;max-width:520px;margin:0 auto;">
                        No hay textos del catálogo sin equivalencia global pendiente de registrar. El total de
                        <strong>Incidencias</strong> de una ejecución ya terminada es
                        <em>histórico</em> de esa descarga y no baja al guardar equivalencias.
                        Revise la columna <strong>Pend.</strong> por ejecución o ejecute una
                        <strong>nueva sincronización</strong> para ver el efecto.
                    </p>
                </div>
            @else
                <div class="cen-read-table-wrap">
                    <table class="table cen-read-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Establecimiento</th>
                                <th>Campo</th>
                                <th>
                                    Texto en catálogo nacional
                                    @include('voyager::establecimientos.partials.help-icon', [
                                        'text' => CenEstablishmentsUiCopy::HELP_GLOSA_API,
                                    ])
                                </th>
                                <th>Valor en Centinela</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unmappedEnumIssues as $issue)
                                @php
                                    $ctx = is_array($issue->context) ? $issue->context : [];
                                    $fieldKey = $ctx['field'] ?? '';
                                    $apiGlosa = $ctx['api_glosa'] ?? $issue->message;
                                    $fieldLabel = $catalogFieldLabels[$fieldKey] ?? $fieldKey;
                                    $options =
                                        $fieldKey !== '' ? CenCatalogGlosaResolver::enumOptionsForField($fieldKey) : [];
                                    $estCodigo =
                                        $issue->establecimiento_codigo ??
                                        (is_array($ctx) ? $ctx['establecimiento_codigo'] ?? null : null);
                                    $estNombre =
                                        $issue->establecimiento_nombre_display ??
                                        (is_array($ctx) ? $ctx['establecimiento_nombre'] ?? null : null);
                                @endphp
                                <tr>
                                    <td>{{ $issue->id }}</td>
                                    <td class="cen-equiv-establishment-cell">
                                        @if (is_string($estCodigo) && $estCodigo !== '')
                                            <strong
                                                class="cen-equiv-establishment-cell__codigo">{{ $estCodigo }}</strong>
                                            @if (is_string($estNombre) && $estNombre !== '')
                                                <br><span
                                                    class="cen-equiv-establishment-cell__nombre">{{ $estNombre }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $fieldLabel !== '' ? $fieldLabel : '—' }}</td>
                                    <td>{{ $apiGlosa }}</td>
                                    @if ($fieldKey !== '' && $options !== [])
                                        <form id="cen-promote-issue-{{ $issue->id }}" method="POST"
                                            action="{{ route('voyager.establecimientos.sync-issues.promote-glosa-equivalence', $issue) }}"
                                            class="cen-equiv-promote-form-hidden">
                                            @csrf
                                        </form>
                                        <td>
                                            <select name="target_value" form="cen-promote-issue-{{ $issue->id }}"
                                                class="form-control input-sm cen-equiv-promote-select" required>
                                                <option value="">— Seleccionar —</option>
                                                @foreach ($options as $opt)
                                                    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-right">
                                            <button type="submit" form="cen-promote-issue-{{ $issue->id }}"
                                                class="btn btn-sm btn-primary pull-right">
                                                Registrar equivalencia
                                            </button>
                                        </td>
                                    @else
                                        <td colspan="2">
                                            <span class="text-muted">Falta información del campo en la incidencia</span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('voyager::establecimientos.partials.browse-voyager-pagination', [
                    'paginator' => $unmappedEnumIssues,
                    'pageName' => 'unmapped_page',
                    'queryExcept' => ['tab' => 'historial'],
                ])
            @endif
        </div>

        <div class="cen-read-section">
            <div class="cen-read-section__head">
                <i class="voyager-list-add"></i>
                <h4 class="cen-read-section__title">Registrar equivalencia manualmente</h4>
            </div>
            <form method="POST" action="{{ route('voyager.establecimientos.glosa-equivalences.store') }}"
                class="form-horizontal cen-equiv-manual-form" style="padding:12px 16px;">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="cen-equiv-field">Campo en Centinela</label>
                        <select name="field_key" id="cen-equiv-field" class="form-control" required>
                            <option value="">— Seleccionar —</option>
                            @foreach (CenCatalogGlosaResolver::fieldKeys() as $key)
                                <option value="{{ $key }}" @selected(old('field_key') === $key)>
                                    {{ $catalogFieldLabels[$key] ?? $key }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="cen-equiv-source">
                            Texto en catálogo nacional
                            @include('voyager::establecimientos.partials.help-icon', [
                                'text' => CenEstablishmentsUiCopy::HELP_GLOSA_API,
                            ])
                        </label>
                        <input type="text" name="source_glosa" id="cen-equiv-source" class="form-control"
                            maxlength="512" required placeholder="Ej.: texto exacto de datos.gob.cl"
                            value="{{ old('source_glosa') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="cen-equiv-target">Valor en Centinela</label>
                        <select name="target_value" id="cen-equiv-target" class="form-control" required disabled>
                            <option value="">— Seleccione un campo primero —</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary pull-right">Guardar equivalencia</button>
            </form>
        </div>

        <div class="cen-read-section">
            <div class="cen-read-section__head cen-read-section__head--split">
                <div class="cen-read-section__head-main">
                    <i class="voyager-check"></i>
                    <h4 class="cen-read-section__title">
                        Equivalencias activas
                        @if ($glosaEquivalences && $glosaEquivalences->total() > 0)
                            <span class="cen-read-count-badge">{{ $glosaEquivalences->total() }}</span>
                        @endif
                    </h4>
                </div>
                @if (\Illuminate\Support\Facades\Route::has('voyager.establecimientos.glosa-equivalences.export'))
                    <a href="{{ route('voyager.establecimientos.glosa-equivalences.export') }}"
                        class="btn btn-sm btn-success centinela-download-guard-link pull-right"
                        data-centinela-download-fallback="equivalencias-catalogo.xlsx"
                        title="Descargar todas las equivalencias activas registradas en Centinela">
                        <i class="voyager-download" style="color: #fff;"></i>
                        <span class="hidden-xs">Descargar equivalencias</span>
                    </a>
                @endif
            </div>
            @if ($glosaEquivalences === null || $glosaEquivalences->isEmpty())
                <div class="cen-read-empty">
                    <p><strong>Sin equivalencias registradas</strong></p>
                </div>
            @else
                <div class="cen-read-table-wrap">
                    <table class="table cen-read-data-table">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Texto catálogo nacional</th>
                                <th>Valor Centinela</th>
                                <th>Tipo de regla</th>
                                <th>Revisado por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($glosaEquivalences as $equiv)
                                <tr>
                                    <td>{{ $catalogFieldLabels[$equiv->field_key] ?? $equiv->field_key }}</td>
                                    <td>{{ $equiv->source_glosa }}</td>
                                    <td><strong>{{ $equiv->target_value }}</strong></td>
                                    <td>{{ CenEstablishmentsUiCopy::ruleKindLabel($equiv->rule_kind->value) }}</td>
                                    <td>
                                        {{ $equiv->reviewedBy?->name ?? '—' }}
                                        <br><small>{{ $equiv->updated_at?->format('d/m/Y H:i') }}</small>
                                    </td>
                                    <td class="text-right">
                                        <form method="POST"
                                            action="{{ route('voyager.establecimientos.glosa-equivalences.deactivate', $equiv) }}"
                                            class="pull-right"
                                            onsubmit="return confirm('¿Desactivar esta equivalencia?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-default">Desactivar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('voyager::establecimientos.partials.browse-voyager-pagination', [
                    'paginator' => $glosaEquivalences,
                    'pageName' => 'equiv_page',
                    'queryExcept' => ['tab' => 'historial'],
                ])
            @endif
        </div>
    </div>

    <script>
        (function() {
            var enumOptionsByField = @json($catalogEnumOptions);
            var fieldSelect = document.getElementById('cen-equiv-field');
            var targetSelect = document.getElementById('cen-equiv-target');
            var oldTarget = @json(old('target_value'));

            if (!fieldSelect || !targetSelect) {
                return;
            }

            function repopulateTarget(fieldKey) {
                while (targetSelect.options.length > 0) {
                    targetSelect.remove(0);
                }

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = fieldKey ? '— Seleccionar —' : '— Seleccione un campo primero —';
                targetSelect.appendChild(placeholder);

                if (!fieldKey || !enumOptionsByField[fieldKey]) {
                    targetSelect.disabled = true;
                    return;
                }

                enumOptionsByField[fieldKey].forEach(function(opt) {
                    var option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    if (oldTarget && oldTarget === opt.value) {
                        option.selected = true;
                    }
                    targetSelect.appendChild(option);
                });

                targetSelect.disabled = false;
            }

            fieldSelect.addEventListener('change', function() {
                oldTarget = null;
                repopulateTarget(fieldSelect.value);
            });

            if (fieldSelect.value) {
                repopulateTarget(fieldSelect.value);
            }
        })();
    </script>
@endif
