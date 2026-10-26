@if ($showComplexFormulaVariablesModal)
    <div class="modal fade in metas-instrument-ui admin-dashboard-compass admin-dashboard-compass--modal metas-complex-formula-variables-modal"
        style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1" role="dialog"
        aria-labelledby="metas-complex-formula-variables-title" wire:key="metas-complex-formula-variables-modal">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" wire:click="closeComplexFormulaVariablesModal"
                        aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="metas-complex-formula-variables-title">
                        <i class="voyager-calculator" aria-hidden="true"></i>
                        Variables de cálculo
                    </h4>
                </div>

                <div class="modal-body metas-complex-formula-variables-modal__body">
                    @php
                        $payload = $complexFormulaVariablesPayload ?? [];
                    @endphp

                    @if (!empty($payload))
                        <p class="metas-complex-formula-variables-modal__intro text-muted">
                            <code>{{ $payload['indicator_clave'] ?? '' }}</code>
                            · {{ $payload['indicator_nombre'] ?? '' }}
                            · {{ $payload['center_nombre'] ?? '' }}
                            @if (!empty($payload['center_codigo']))
                                <span class="text-muted">({{ $payload['center_codigo'] }})</span>
                            @endif
                        </p>

                        <p class="metas-complex-formula-variables-modal__meta text-muted">
                            Período: <strong>{{ $payload['periodo_label'] ?? '—' }}</strong>
                            @if (!empty($payload['periodo_note']))
                                <br>{{ $payload['periodo_note'] }}
                            @endif
                        </p>

                        @if (!empty($payload['expression']))
                            <p class="metas-complex-formula-variables-modal__expression">
                                <span class="cen-modal-section-title">Expresión</span>
                                <code
                                    class="metas-complex-formula-variables-modal__expression-code">{{ $payload['expression'] }}</code>
                            </p>
                        @endif

                        @if (!empty($payload['empty_message']) && empty($payload['variables']))
                            <p class="indicator-bitacora-empty">{{ $payload['empty_message'] }}</p>
                        @else
                            <div class="metas-complex-formula-variables-modal__table-wrap table-responsive"
                                wire:loading.class="metas-complex-formula-variables-modal__table-wrap--loading"
                                wire:target="openComplexFormulaVariablesModal">
                                <table
                                    class="table table-bordered table-condensed cen-compass-table metas-complex-formula-variables-table">
                                    <colgroup>
                                        <col class="metas-complex-formula-variables-table__col-variable">
                                        <col class="metas-complex-formula-variables-table__col-campo">
                                        <col class="metas-complex-formula-variables-table__col-valor">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Variable</th>
                                            <th>Campo</th>
                                            <th class="text-right">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($payload['variables'] ?? [] as $row)
                                            <tr wire:key="cfv-{{ $row['variable_key'] }}">
                                                <td><code>{{ $row['variable_key'] }}</code></td>
                                                <td>{{ $row['label'] }}</td>
                                                <td class="text-right metas-complex-formula-variables-table__valor">
                                                    {{ $row['value_display'] }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Sin variables</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr class="metas-complex-formula-variables-table__row--resultado">
                                            <td colspan="2"><strong>Resultado</strong></td>
                                            <td class="text-right metas-complex-formula-variables-table__valor">
                                                @if (($payload['resultado_display'] ?? null) !== null)
                                                    {{ $payload['resultado_display'] }}
                                                    @if (!empty($payload['unidad_medida']))
                                                        <span
                                                            class="text-muted metas-complex-formula-variables-table__unidad">{{ $payload['unidad_medida'] }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            @if (!empty($payload['empty_message']))
                                <p class="metas-complex-formula-variables-modal__empty-note text-muted">
                                    {{ $payload['empty_message'] }}
                                </p>
                            @endif
                        @endif
                    @endif
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" wire:click="closeComplexFormulaVariablesModal">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
