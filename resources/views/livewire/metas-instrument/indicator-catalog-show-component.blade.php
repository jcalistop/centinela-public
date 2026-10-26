<div>
    <div class="metas-tab-panel metas-ficha-indicador-panel">

        {{-- KPI summary bar --}}
        <div class="row metas-ficha-kpi-bar">
            <div class="col-sm-3 col-xs-6">
                <span class="metas-ficha-kpi">
                    <i class="voyager-activity metas-ficha-kpi__icon"></i>
                    <span class="metas-ficha-kpi__label">Periodicidad</span>
                    <span
                        class="metas-ficha-kpi__value metas-ficha-kpi__value--dark">{{ $fichaData['periodicidad'] }}</span>
                </span>
            </div>
            @if (($indicatorType->route ?? '') === 'metas-19664')
                <div class="col-sm-3 col-xs-6">
                    <span class="metas-ficha-kpi">
                        <i class="voyager-list metas-ficha-kpi__icon"></i>
                        <span class="metas-ficha-kpi__label">Prioridad</span>
                        <span class="metas-ficha-kpi__value">
                            @php
                                $prioridadFicha = $fichaData['prioridad'] ?? null;
                            @endphp
                            @if ($prioridadFicha instanceof \App\Enums\Metas\MetIndicatorPriority && $prioridadFicha->showsTag())
                                <x-metas.indicator-priority-tag :priority="$prioridadFicha" />
                            @elseif ($prioridadFicha instanceof \App\Enums\Metas\MetIndicatorPriority)
                                {{ $prioridadFicha->label() }}
                            @else
                                Sin prioridad
                            @endif
                        </span>
                    </span>
                </div>
            @endif
            <div class="col-sm-3 col-xs-6">
                <span class="metas-ficha-kpi">
                    <i class="voyager-params metas-ficha-kpi__icon"></i>
                    <span class="metas-ficha-kpi__label">Unidad de medida</span>
                    <span class="metas-ficha-kpi__value">{{ $fichaData['unidad_medida'] ?: '—' }}</span>
                </span>
            </div>
            <div class="col-sm-3 col-xs-6">
                <span class="metas-ficha-kpi">
                    <i class="voyager-wand metas-ficha-kpi__icon"></i>
                    <span class="metas-ficha-kpi__label">Decimales</span>
                    <span class="metas-ficha-kpi__value">{{ $fichaData['decimales'] }} <small
                            class="text-muted">({{ $fichaData['decimales_modo'] ?: '—' }})</small></span>
                </span>
            </div>
            @unless (($indicatorType->route ?? '') === 'metas-19664')
                <div class="col-sm-3 col-xs-6">
                    <span class="metas-ficha-kpi">
                        <i class="voyager-people metas-ficha-kpi__icon"></i>
                        <span class="metas-ficha-kpi__label">Ámbitos</span>
                        <span class="metas-ficha-kpi__value">
                            @if (!empty($fichaData['ambito_tags']))
                                {{ count($fichaData['ambito_tags']) }}
                            @else
                                —
                            @endif
                        </span>
                    </span>
                </div>
            @endunless
        </div>

        {{-- Objective --}}
        <div class="metas-ficha-section">
            <h5 class="metas-ficha-section__heading">
                <i class="voyager-bulb"></i> Objetivo y/o descripción del indicador
            </h5>
            <div class="metas-ficha-section__body">
                @php
                    $objetivoSafeHtml = \App\Support\Metas\ObjetivoHtml::toSafeHtml($fichaData['objetivo'] ?? null);
                @endphp
                @if ($objetivoSafeHtml !== '')
                    <div class="metas-muted-text">{!! $objetivoSafeHtml !!}</div>
                @else
                    <p class="metas-muted-text--light">Sin descripción definida.</p>
                @endif
            </div>
        </div>

        {{-- Ámbitos --}}
        <div class="metas-ficha-section">
            <h5 class="metas-ficha-section__heading">
                <i class="voyager-categories"></i> Ámbitos
            </h5>
            <div class="metas-ficha-section__body">
                @if (!empty($fichaData['ambito_tags']))
                    @include('partials.indicators.ambitos-badges', [
                        'ambitos' => collect($fichaData['ambito_tags']),
                        'linkToDetail' => true,
                    ])
                @elseif (auth()->user()->hasRole(['parametrizador', 'admin']))
                    <div class="alert alert-warning metas-ficha-empty-alert" role="alert">
                        Sin ámbito asignado.
                    </div>
                @else
                    <p class="metas-muted-text--light">Sin ámbito asignado.</p>
                @endif
            </div>
        </div>

        {{-- Referentes --}}
        <div class="metas-ficha-section">
            <div class="metas-ficha-section__head">
                <h5 class="metas-ficha-section__heading metas-ficha-section__heading--toolbar">
                    <i class="voyager-people"></i> Referentes
                </h5>
                @if ($canManageReferentes)
                    <button type="button" wire:click="openReferentes" class="btn btn-xs btn-success"
                        title="Gestionar referentes">
                        <i class="voyager-people"></i> Gestionar referentes
                    </button>
                @endif
            </div>
            <div class="metas-ficha-section__body">
                @if (!empty($fichaData['referentes']))
                    @foreach ($fichaData['referentes'] as $ref)
                        <a href="{{ route('voyager.users.show', $ref['id']) }}"
                            class="label label-default metas-referente-label metas-referente-label-link"
                            title="Ver perfil y correo electrónico">
                            <i class="voyager-person"></i> {{ $ref['name'] }}
                        </a>
                    @endforeach
                @elseif (auth()->user()->hasRole(['control-gestion', 'admin']))
                    <div class="alert alert-warning metas-ficha-empty-alert" role="alert">
                        Sin referentes asignados.
                    </div>
                @else
                    <p class="metas-muted-text--light">Sin referentes asignados.</p>
                @endif
            </div>
        </div>

        {{-- Meta nacional — highlighted panel --}}
        <div class="metas-ficha-section">
            <h5 class="metas-ficha-section__heading">
                <i class="voyager-flag"></i> Meta nacional
            </h5>
            <div class="metas-ficha-meta-card">
                <div class="row">
                    <div class="col-sm-3">
                        <span class="metas-ficha-meta-card__label">Valor meta</span>
                        <span class="metas-ficha-meta-card__value">
                            @if ($fichaData['meta_valor'] !== null)
                                {{ number_format((float) $fichaData['meta_valor'], $fichaData['decimales']) }}
                            @else
                                <span class="text-muted">No definida</span>
                            @endif
                        </span>
                    </div>
                    <div class="col-sm-2">
                        <span class="metas-ficha-meta-card__label">Operador</span>
                        <span class="metas-ficha-meta-card__value">
                            @if ($fichaData['meta_operador'])
                                <span
                                    class="metas-ficha-meta-card__value--dark">{{ $fichaData['meta_operador'] }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </span>
                    </div>
                    <div class="col-sm-7">
                        <span class="metas-ficha-meta-card__label">Descripción de la meta</span>
                        <span class="metas-ficha-meta-card__desc">
                            {{ $fichaData['meta_anual_descripcion'] ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Configuración técnica — grid of mini-cards --}}
        <div class="metas-ficha-section">
            <h5 class="metas-ficha-section__heading">
                <i class="voyager-settings"></i> Configuración técnica
            </h5>

            @php
                $umbralInf = (float) ($fichaData['umbral_inferior'] ?? 0);
                $umbralSup = (float) ($fichaData['umbral_superior'] ?? 100);
                $umbralesIguales = abs($umbralInf - $umbralSup) < 0.001;
                $umbralRojo = max(0, min(100, $umbralInf));
                $umbralAmarillo = $umbralesIguales ? 0 : max(0, min(100, $umbralSup) - $umbralRojo);
                $umbralVerde = max(0, 100 - $umbralRojo - $umbralAmarillo);
            @endphp

            <div class="row metas-ficha-tech-grid">
                <div class="col-sm-12">
                    <div class="metas-ficha-tech-card">
                        <div class="metas-ficha-tech-card__header">
                            <i class="voyager-params"></i> Umbrales de cumplimiento
                        </div>
                        <div class="metas-ficha-tech-card__body">
                            <div class="row metas-ficha-umbrales-row">
                                <div class="col-sm-6 metas-ficha-umbrales-col">
                                    <span class="metas-ficha-tech-card__label">Inferior</span>
                                    <span
                                        class="metas-ficha-tech-card__value">{{ number_format($umbralInf, 2) }}%</span>
                                    <p class="metas-ficha-tech-card__help">Por debajo de este umbral el cumplimiento
                                        ponderado será 0&nbsp;%.</p>
                                </div>
                                <div class="col-sm-6 metas-ficha-umbrales-col metas-ficha-umbrales-col--right">
                                    <span class="metas-ficha-tech-card__label">Superior</span>
                                    <span
                                        class="metas-ficha-tech-card__value">{{ number_format($umbralSup, 2) }}%</span>
                                    <p class="metas-ficha-tech-card__help">Al alcanzar o superar este umbral, el
                                        cumplimiento ponderado será igual al 100&nbsp;% de la ponderación.</p>
                                </div>
                            </div>
                            <p class="metas-ficha-tech-card__help" style="margin-top: 8px;">
                                @if ($umbralesIguales)
                                    Umbrales iguales: corte binario — el cumplimiento ponderado es 0&nbsp;% bajo
                                    {{ number_format($umbralInf, 2) }}&nbsp;% y el 100&nbsp;% de la ponderación desde
                                    ese valor.
                                @else
                                    Entre {{ number_format($umbralInf, 2) }}&nbsp;% y
                                    {{ number_format($umbralSup, 2) }}&nbsp;% el cumplimiento ponderado es proporcional
                                    al cumplimiento.
                                @endif
                                El cumplimiento mostrado no aplica umbrales (máximo 100&nbsp;%).
                            </p>

                            <div class="progress metas-ficha-umbral-bar metas-ficha-umbral-bar--full"
                                style="margin-top:10px; margin-bottom:0; height:8px; background-color:#e9ecef;">
                                <div class="progress-bar progress-bar-danger"
                                    style="width: {{ $umbralRojo }}%; min-width:2px;"></div>
                                <div class="progress-bar progress-bar-warning" style="width: {{ $umbralAmarillo }}%;">
                                </div>
                                <div class="progress-bar progress-bar-success" style="width: {{ $umbralVerde }}%;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Nivel de aplicación --}}
        <div class="metas-ficha-section">
            <h5 class="metas-ficha-section__heading">
                <i class="voyager-location"></i> Nivel de aplicación
            </h5>
            <div class="metas-ficha-section__body">
                @if ($fichaData['nivel_aplicacion'])
                    <p class="metas-muted-text">{{ $fichaData['nivel_aplicacion'] }}</p>
                @else
                    <p class="metas-muted-text--light">No definido.</p>
                @endif
            </div>
        </div>

        {{-- Exclusiones --}}
        <div class="metas-ficha-section">
            <h5 class="metas-ficha-section__heading">
                <i class="voyager-x"></i> Exclusiones
            </h5>
            <div class="metas-ficha-section__body">
                @if (!empty($fichaData['exclusiones']))
                    <p class="metas-muted-text">{{ $fichaData['exclusiones'] }}</p>
                @else
                    <p class="metas-muted-text--light">Sin exclusiones definidas.</p>
                @endif
            </div>
        </div>

        {{-- Data fields table --}}
        @if (!empty($fichaData['campos']))
            <div class="metas-ficha-section">
                <h5 class="metas-ficha-section__heading">
                    <i class="voyager-data"></i> Maqueta de datos y fórmula
                </h5>
                <h6 class="metas-ficha-subsection__heading">Campos de datos</h6>
                <div class="metas-ficha-section__body">
                    <table class="table table-bordered table-condensed metas-instrument-table metas-ficha-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Etiqueta</th>
                                <th>Variable</th>
                                <th class="text-center">Componente</th>
                                <th class="text-center">Tipo entrada</th>
                                <th class="text-center">Agregación</th>
                                <th class="text-center">Fuente</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fichaData['campos'] as $i => $campo)
                                <tr>
                                    <td class="text-center text-muted">{{ $i + 1 }}</td>
                                    <td>{{ $campo['label'] }}</td>
                                    <td><code class="metas-variable-code">{{ $campo['variable_key'] }}</code></td>
                                    <td class="text-center">
                                        <span
                                            class="label {{ $campo['componente'] === 'numerador' ? 'label-primary' : ($campo['componente'] === 'denominador' ? 'label-warning' : 'label-default') }}">
                                            {{ $campo['componente'] }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $campo['input_type'] ?? '—' }}</td>
                                    <td class="text-center">{{ $campo['aggregation_type'] ?? '—' }}</td>
                                    <td class="text-center">{{ $campo['fuente'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <x-metas.field-types-legend />
                </div>
            </div>
        @endif

        {{-- Formula expression --}}
        @if ($fichaData['formula_expression'])
            <div class="metas-ficha-section">
                <h5 class="metas-ficha-section__heading">
                    <i class="voyager-calculator"></i> Expresión de cálculo
                </h5>
                <div class="metas-ficha-section__body">
                    <p class="metas-ficha-formula-expression">{{ $fichaData['formula_expression'] }}</p>
                </div>
            </div>
        @endif

    </div>

    @if ($showReferentesModal)
        <div id="centinela-metas-modal-referentes" class="modal fade in centinela-metas-catalog-modal" tabindex="-1"
            role="dialog" wire:key="metas-show-modal-referentes"
            style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;">
            <div class="modal-dialog" role="document" style="max-width: 560px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeReferentesModal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">
                            <i class="voyager-people"></i> Referentes —
                            <small class="text-muted">{{ $fichaData['clave'] }}: {{ $fichaData['nombre'] }}</small>
                        </h4>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted" style="margin-bottom: 1rem;">
                            Seleccione uno o más referentes para este indicador en su Servicio de Salud.
                            Puede buscar por nombre.
                        </p>

                        @if ($selectedReferentes->isNotEmpty())
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label class="control-label">Seleccionados</label>
                                <div>
                                    @foreach ($selectedReferentes as $selected)
                                        <span class="label label-primary metas-referente-label"
                                            style="margin-right: 4px; margin-bottom: 4px; display: inline-block;">
                                            <i class="voyager-person"></i> {{ $selected->name }}
                                            <button type="button" class="close"
                                                style="float: none; margin-left: 6px; font-size: 14px; line-height: 1;"
                                                wire:click="removeReferente({{ $selected->id }})"
                                                title="Quitar referente">&times;</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="referente-search-input">Buscar referente</label>
                            <input id="referente-search-input" type="search" class="form-control"
                                wire:model.live.debounce.300ms="referenteSearch"
                                placeholder="Escriba el nombre del referente..." autocomplete="off">
                            <small class="text-muted" wire:loading wire:target="referenteSearch">
                                <i class="voyager-loader"></i> Buscando...
                            </small>
                        </div>

                        @if ($availableReferentes->isEmpty())
                            <p class="text-muted text-center" style="margin-top: 0.75rem;">
                                <small>
                                    @if (trim($referenteSearch) !== '')
                                        No se encontraron referentes que coincidan con la búsqueda.
                                    @else
                                        No hay usuarios con rol <em>referente</em> en este Servicio de Salud.
                                    @endif
                                </small>
                            </p>
                        @else
                            <ul class="list-group" style="max-height: 220px; overflow-y: auto; margin-bottom: 0;">
                                @foreach ($availableReferentes as $u)
                                    @php
                                        $isSelected = in_array($u->id, $referentesList, true);
                                    @endphp
                                    <li class="list-group-item {{ $isSelected ? 'active' : '' }}"
                                        wire:key="referente-option-{{ $u->id }}"
                                        style="{{ $isSelected ? 'cursor: default;' : 'cursor: pointer;' }}"
                                        @unless ($isSelected) wire:click="addReferente({{ $u->id }})" @endunless>
                                        <i class="voyager-person"></i> {{ $u->name }}
                                        @if ($isSelected)
                                            <span class="pull-right"><i class="voyager-check"></i></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="form-group" style="margin-top: 1rem; margin-bottom: 0;">
                            <label>
                                <input type="checkbox" wire:model="notifyReferentes" value="1">
                                Notificar a referentes
                            </label>
                            <small class="text-muted d-block" style="margin-top: 8px;">
                                Al guardar, se enviará un correo solo a los referentes recién asignados en esta acción.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" wire:click="closeReferentesModal">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="saveReferentes"
                            wire:loading.attr="disabled">
                            <span wire:loading wire:target="saveReferentes">
                                <i class="voyager-loader"></i>
                            </span>
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
