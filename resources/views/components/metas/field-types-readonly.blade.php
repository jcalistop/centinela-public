@props(['rows' => [], 'variant' => 'panel'])

@if (count($rows) > 0)
    @if ($variant === 'flat')
        <section class="metas-field-types-flat">
            <h4 class="metas-ficha-section__heading">
                <i class="voyager-info-circled"></i> Configuración de carga para su Servicio de Salud
            </h4>
            <p class="text-muted metas-field-types-flat__intro small">
                Tipos de entrada y agregación definidos por Control de Gestión para su Servicio de Salud.
            </p>
            <div class="table-responsive">
                <table class="table table-condensed table-hover cen-compass-table metas-field-types-flat-table">
                    <thead>
                        <tr>
                            <th class="metas-field-types-readonly-col--variable">Variable</th>
                            <th>Etiqueta</th>
                            <th class="metas-field-types-readonly-col--tipo">Tipo de entrada</th>
                            <th class="metas-field-types-readonly-col--tipo">Agregación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><code>{{ $row['variable_key'] }}</code></td>
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['input_type_label'] }}</td>
                                <td>{{ $row['aggregation_type_label'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="metas-section-block metas-field-types-readonly-wrap">
            <x-metas.bordered-panel variant="default" icon="voyager-info-circled"
                class="metas-field-types-readonly-panel">
                <x-slot:heading>
                    Configuración de carga para su Servicio de Salud
                </x-slot:heading>

                <p class="text-muted metas-field-types-readonly-intro small">
                    Tipos de entrada y agregación definidos por Control de Gestión para su Servicio de Salud.
                </p>

                <div class="table-responsive">
                    <table
                        class="table table-bordered table-condensed metas-instrument-table metas-field-types-readonly-table">
                        <thead>
                            <tr>
                                <th class="metas-field-types-readonly-col--variable">Variable</th>
                                <th>Etiqueta</th>
                                <th class="metas-field-types-readonly-col--tipo">Tipo de entrada</th>
                                <th class="metas-field-types-readonly-col--tipo">Agregación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td><code>{{ $row['variable_key'] }}</code></td>
                                    <td>{{ $row['label'] }}</td>
                                    <td>{{ $row['input_type_label'] }}</td>
                                    <td>{{ $row['aggregation_type_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metas.bordered-panel>
        </div>
    @endif
@endif
