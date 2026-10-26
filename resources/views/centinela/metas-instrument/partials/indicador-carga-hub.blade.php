@if ($allowsDataEntryHub)
    <section class="metas-carga-hub">
        <section class="metas-carga-hub-masiva">
            <h4 class="metas-ficha-section__heading">
                <i class="voyager-upload"></i> Carga masiva de datos
            </h4>

            @if ($setting->etapa_vigente === \App\Enums\IndicatorType\EtapaVigente::Autoevaluacion)
                <form method="POST"
                    action="{{ route('metas.instrument.indicador.importar', ['indicatorType' => $indicatorType, 'indicator' => $indicator]) }}"
                    enctype="multipart/form-data" class="metas-carga-hub-masiva__form">
                    @csrf
                    <div class="metas-carga-hub-masiva__controls">
                        <a href="{{ route('metas.instrument.indicador.plantilla.download', ['indicatorType' => $indicatorType, 'indicator' => $indicator]) }}"
                            class="btn btn-warning btn-sm centinela-download-guard-link"
                            data-centinela-download-fallback="detalle-datos.xlsx">
                            <i class="voyager-download"></i> Descargar plantilla XLSX
                        </a>
                        <input type="file" id="import-archivo" name="archivo" accept=".xlsx"
                            class="form-control input-sm metas-carga-hub-masiva__file @error('archivo') has-error @enderror"
                            data-max-upload-bytes="{{ \App\Support\Upload\UploadSizeLimit::importSpreadsheetMaxKilobytes() * 1024 }}"
                            data-max-upload-label="{{ \App\Support\Upload\UploadSizeLimit::humanLabelForKilobytes(\App\Support\Upload\UploadSizeLimit::importSpreadsheetMaxKilobytes()) }}">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="voyager-check"></i> Procesar archivo
                        </button>
                    </div>
                    <x-upload-size-hint :kilobytes="\App\Support\Upload\UploadSizeLimit::importSpreadsheetMaxKilobytes()" />
                    @error('archivo')
                        <span class="help-block text-danger metas-carga-hub-masiva__error">{{ $message }}</span>
                    @enderror
                </form>
            @else
                <p class="metas-carga-hub-import-notice text-muted small">
                    La importación XLSX solo está disponible en etapa
                    <strong>AUTOEVALUACION</strong> (etapa actual: {{ $setting->etapa_vigente->value }}).
                </p>
            @endif
        </section>

        @if (session('import_errors') && count(session('import_errors')) > 0)
            <div class="alert alert-danger metas-carga-hub-import-errors" role="alert">
                <strong>
                    <i class="voyager-warning"></i>
                    {{ count(session('import_errors')) }} error(es) en la última importación
                </strong>
                <ul class="metas-carga-hub-import-errors__list">
                    @foreach (session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($centros->isEmpty())
            <div class="alert alert-warning metas-carga-hub-empty">
                No hay establecimientos con este indicador marcado como <strong>aplica</strong> en la configuración
                del convenio para su Servicio de Salud (año activo), o ninguno cumple las reglas de inclusión en la
                medición. Los establecimientos <strong>adosados</strong> aparecen cuando la madre tiene el
                indicador en <strong>aplica</strong> y existe la relación en <strong>met_center_adosados</strong>.
            </div>
        @else
            <section class="metas-carga-hub-manual">
                <h4 class="metas-ficha-section__heading">
                    <i class="voyager-edit"></i> Carga manual
                </h4>
                <div class="table-responsive metas-carga-hub-table-wrap">
                    <table
                        class="table table-condensed table-hover cen-compass-table metas-carga-hub-table metas-carga-table">
                        <thead>
                            <tr>
                                <th class="metas-carga-col--establecimiento">Establecimiento</th>
                                <th class="metas-carga-col--actualizado">Actualizado a</th>
                                @if ($erroresCalculoPorCentro->isNotEmpty())
                                    <th class="metas-carga-col--resultados">Resultados</th>
                                @endif
                                @if ($periodosIncompletosPorCentro->isNotEmpty())
                                    <th class="metas-carga-col--captura">Captura</th>
                                @endif
                                <th class="text-center metas-carga-col--acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($centros as $centro)
                                @php
                                    $ultimoMes = $ultimoMesPorCentro[$centro->id] ?? null;
                                    $metaAdosado = $adosadoCargaMeta[$centro->id] ?? null;
                                    $errorCalculo = $erroresCalculoPorCentro->get($centro->id);
                                    $periodoIncompleto = $periodosIncompletosPorCentro->get($centro->id);
                                @endphp
                                <tr @class(['metas-carga-fila-adosado' => $metaAdosado])>
                                    <td>
                                        <i class="voyager-hospital text-muted"></i>
                                        {{ $centro->nombre_corto }} ({{ $centro->codigo }})
                                        @if ($metaAdosado)
                                            <div class="small text-muted metas-carga-adosado-meta">
                                                <span class="label label-info">Adosado</span>
                                                @if (!empty($metaAdosado['madre_codigo']))
                                                    — madre {{ $metaAdosado['madre_codigo'] }}
                                                    @if (!empty($metaAdosado['madre_nombre']))
                                                        ({{ $metaAdosado['madre_nombre'] }})
                                                    @endif
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($ultimoMes)
                                            <span
                                                class="label label-success">{{ app(\App\Services\Metas\MetIndicatorLastLoadedMonthService::class)->etiquetaMes($ultimoMes) }}</span>
                                        @else
                                            <span class="label label-default">Sin datos</span>
                                        @endif
                                    </td>
                                    @if ($erroresCalculoPorCentro->isNotEmpty())
                                        <td>
                                            @if ($errorCalculo)
                                                <x-metas.calculation-error-alert variant="center" :period-count="$errorCalculo['count']"
                                                    :message="$errorCalculo['message']" />
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    @if ($periodosIncompletosPorCentro->isNotEmpty())
                                        <td>
                                            @if ($periodoIncompleto)
                                                <x-metas.incomplete-period-alert variant="center" :period-count="$periodoIncompleto['count']"
                                                    :period-labels="$periodoIncompleto['periodos']" :message="$periodoIncompleto['message']" />
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="text-center">
                                        @if ($indicatorType->usesInternalUnitMeasurement())
                                            <a href="{{ route('metas.instrument.indicador.center.units', ['indicatorType' => $indicatorType, 'indicator' => $indicator, 'center' => $centro]) }}"
                                                class="btn btn-xs btn-primary">
                                                <i class="voyager-list"></i> Unidades / carga
                                            </a>
                                        @else
                                            <a href="{{ route('metas.instrument.indicador.show', ['indicatorType' => $indicatorType, 'indicator' => $indicator, 'center' => $centro]) }}"
                                                class="btn btn-xs btn-primary">
                                                <i class="voyager-edit"></i> Carga manual
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </section>
@endif
