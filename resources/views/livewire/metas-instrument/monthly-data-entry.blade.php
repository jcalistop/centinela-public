<div>
    @if ($errors->has('general'))
        <div class="alert alert-danger metas-monthly-save-notice" role="alert">
            {{ $errors->first('general') }}
        </div>
    @endif

    <header class="metas-monthly-context">
        <div class="metas-monthly-context__head">
            <div class="metas-monthly-context__title-row">
                <div class="metas-monthly-context__badges metas-monthly-context__badges--solo">
                    <span class="label label-default">Etapa: {{ $etapaVigente }}</span>
                </div>
            </div>
        </div>

        <div class="row metas-monthly-stats">
            <div @class([
                'col-sm-6' => $indicator->meta_operador && $metaEfectiva !== null,
                'col-sm-12' => !($indicator->meta_operador && $metaEfectiva !== null),
            ])>
                <div class="metas-monthly-stat">
                    <span class="metas-monthly-stat__label">Cálculo preliminar</span>
                    <span class="metas-monthly-stat__value">{{ $preview }}</span>
                </div>
            </div>
            @if ($indicator->meta_operador && $metaEfectiva !== null)
                <div class="col-sm-6">
                    <div class="metas-monthly-stat">
                        <span class="metas-monthly-stat__label">Meta efectiva</span>
                        <span class="metas-monthly-stat__value">
                            {{ $indicator->meta_operador }}
                            {{ number_format($metaEfectiva, $indicator->decimales ?? 2) }}
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </header>

    @if (!empty($effectiveFieldTypeRows))
        <x-metas.field-types-readonly :rows="$effectiveFieldTypeRows" variant="flat" />
    @endif

    @if ($indicator->formula?->description)
        <section class="metas-monthly-formula-block metas-ficha-section">
            <h4 class="metas-ficha-section__heading">
                <i class="voyager-calculator"></i> Fórmula de cálculo
            </h4>
            <p class="text-muted small metas-monthly-formula-block__text">{{ $indicator->formula->description }}</p>
        </section>
    @endif

    @if (!$canEdit && $accessMessage)
        <p class="metas-monthly-access-notice text-muted">
            <i class="voyager-lock"></i> {{ $accessMessage }}
        </p>
    @endif

    @if (
        $monthlyFields->isEmpty() &&
            $trimestralFields->isEmpty() &&
            $semestralFields->isEmpty() &&
            $anualFields->isEmpty() &&
            $baselineFields->isEmpty())
        <div class="alert alert-warning">
            Este indicador no tiene campos configurados en su maqueta.
        </div>
    @else
        @php
            $monthAbbrevs = [
                1 => 'Ene',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Abr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Ago',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dic',
            ];
            $periodicSections = [
                ['title' => 'Carga mensual (Ene a Dic)', 'fields' => $monthlyFields, 'months' => range(1, 12)],
                [
                    'title' => 'Carga trimestral (Mar, Jun, Sep, Dic)',
                    'fields' => $trimestralFields,
                    'months' => [3, 6, 9, 12],
                ],
                ['title' => 'Carga semestral (Jun y Dic)', 'fields' => $semestralFields, 'months' => [6, 12]],
                ['title' => 'Carga anual (Dic)', 'fields' => $anualFields, 'months' => [12]],
            ];
        @endphp

        <section class="metas-monthly-capture-section metas-ficha-section">
            <h4 class="metas-ficha-section__heading">
                <i class="voyager-calendar"></i> Carga mensual
            </h4>
            <p class="metas-monthly-capture-hint text-muted">
                <strong>Cómo guardar:</strong> use <em>Guardar mes</em> en cada fila. Complete <strong>todas</strong>
                las variables del mes (<code>0</code> es válido) o deje <strong>todas</strong> vacías para vaciar el
                período.<br><br>
                <strong>Estado:</strong>
                <span class="text-muted">Vacío</span>: El periodo no tiene datos. |
                <span class="label label-success metas-monthly-capture-hint__badge">Listo</span> El periodo ha sido
                cargado correctamente |
                <span class="label label-warning metas-monthly-capture-hint__badge">Incompleto</span> Al menos uno de
                las variables del periodo no tiene datos |
                <span class="label label-danger metas-monthly-capture-hint__badge">Error</span> El cálculo del periodo
                presentó errores (p. ej. divisor cero).
            </p>
        </section>

        <div class="metas-instrument-periodic-entry">
            @foreach ($periodicSections as $section)
                @if ($section['fields']->isNotEmpty())
                    <h4 @class([
                        'metas-monthly-section-title',
                        'metas-monthly-section-title--first' => $loop->first,
                    ])>{{ $section['title'] }}</h4>

                    <div class="table-responsive metas-monthly-period-table-wrap">
                        <table
                            class="table table-bordered table-condensed metas-instrument-periodic-grid metas-monthly-period-table">
                            <thead>
                                <tr>
                                    <th class="metas-monthly-col--mes">Mes</th>
                                    <th class="metas-monthly-col--estado text-center">Estado</th>
                                    @foreach ($section['fields'] as $field)
                                        <th class="text-center metas-monthly-col--variable" title="{{ $field->label }}">
                                            <code>{{ $field->variable_key }}</code>
                                        </th>
                                    @endforeach
                                    <th class="text-center metas-monthly-col--accion">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($section['months'] as $monthNumber)
                                    @php
                                        $sectionInputType = $section['fields']->first()->input_type;
                                        $rowStatus = $this->periodRowStatus($monthNumber, $sectionInputType);
                                        $rowClass = match (true) {
                                            $errors->has("month.{$monthNumber}") => 'metas-monthly-period-row--error',
                                            $rowStatus['kind'] === 'draft_incomplete'
                                                => 'metas-monthly-period-row--draft-incomplete',
                                            $rowStatus['kind'] === 'persisted_incomplete'
                                                => 'metas-monthly-period-row--persisted-incomplete',
                                            $rowStatus['kind'] === 'calculation_error'
                                                => 'metas-monthly-period-row--calc-error',
                                            default => '',
                                        };
                                    @endphp
                                    <tr wire:key="month-row-{{ $monthNumber }}-section-{{ $loop->parent->index }}"
                                        @class(['metas-monthly-period-row', $rowClass])>
                                        <td class="metas-monthly-col--mes">
                                            <strong>{{ $monthAbbrevs[$monthNumber] }}</strong>
                                        </td>
                                        <td class="text-center metas-monthly-col--estado">
                                            <x-metas.period-row-status :status="$rowStatus" />
                                        </td>
                                        @foreach ($section['fields'] as $field)
                                            <td class="metas-monthly-period-cell">
                                                <input type="number" step="any"
                                                    wire:model.live.debounce.500ms="monthlyValues.{{ $monthNumber }}.{{ $field->id }}"
                                                    class="form-control input-sm metas-instrument-period-input"
                                                    placeholder="—" @if (!$canEdit) disabled @endif>
                                                @error("monthlyValues.{$monthNumber}.{$field->id}")
                                                    <span
                                                        class="text-danger metas-monthly-field-error">{{ $message }}</span>
                                                @enderror
                                            </td>
                                        @endforeach
                                        <td class="text-center metas-monthly-action-cell">
                                            <button type="button" class="btn btn-xs btn-primary"
                                                wire:click="saveRow({{ $monthNumber }})" wire:loading.attr="disabled"
                                                wire:target="saveRow({{ $monthNumber }})"
                                                @if (!$canEdit) disabled @endif>
                                                <span wire:loading.remove
                                                    wire:target="saveRow({{ $monthNumber }})">Guardar mes</span>
                                                <span wire:loading
                                                    wire:target="saveRow({{ $monthNumber }})">Guardando…</span>
                                            </button>
                                            @error("month.{$monthNumber}")
                                                <div class="text-danger small metas-monthly-row-error">{{ $message }}
                                                </div>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($baselineFields->isNotEmpty())
            <h4>Línea base</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            @foreach ($baselineFields as $field)
                                <th>
                                    {{ $field->variable_key }} — {{ $field->label }}
                                    <br>
                                    <small class="text-muted">{{ ucfirst($field->componente->value) }}</small>
                                </th>
                            @endforeach
                            <th class="metas-monthly-baseline-col--accion">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @foreach ($baselineFields as $field)
                                <td>
                                    <input type="number" step="any"
                                        wire:model.live.debounce.500ms="baselineValues.{{ $field->id }}"
                                        class="form-control input-sm"
                                        @if (!$canEdit) disabled @endif>
                                    @error("baselineValues.{$field->id}")
                                        <span
                                            class="help-block text-danger metas-monthly-baseline-error">{{ $message }}</span>
                                    @enderror
                                </td>
                            @endforeach
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-primary" wire:click="saveBaselineRow"
                                    wire:loading.attr="disabled" wire:target="saveBaselineRow"
                                    @if (!$canEdit) disabled @endif>
                                    <span wire:loading.remove wire:target="saveBaselineRow">Guardar línea</span>
                                    <span wire:loading wire:target="saveBaselineRow">Guardando…</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
