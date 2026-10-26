                        <hr />
                        <h5>Variables y fórmula de cálculo</h5>

                        @php
                            use App\Enums\Metas\DataFieldInputType;
                        @endphp

                        @error('formulaJson')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror

                        @error('fields')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <div class="well well-sm" style="padding:10px;margin-bottom:15px">
                            <strong>Agregar campo</strong>
                            <div class="row" style="margin-top:8px">
                                <div class="col-md-3">
                                    <input wire:model="newFieldLabel" type="text"
                                        class="form-control input-sm @error('newFieldLabel') has-error @enderror"
                                        placeholder="Label del campo" />
                                    @error('newFieldLabel')
                                        <span class="help-block text-danger"
                                            style="font-size:11px">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-2">
                                    <select wire:model="newFieldComponente" class="form-control input-sm">
                                        <option value="numerador">Numerador</option>
                                        <option value="denominador">Denominador</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select wire:model.live="newFieldInputType" class="form-control input-sm">
                                        @foreach ($dataFieldInputTypeOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    @if (DataFieldInputType::tryFrom($newFieldInputType)?->requiresAggregationType())
                                        <select wire:model="newFieldAggregationType" class="form-control input-sm">
                                            @foreach ($dataFieldAggregationTypeOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="col-md-2">
                                    <select wire:model="newFieldFuente" class="form-control input-sm">
                                        <option value="">— Fuente —</option>
                                        @foreach ($dataFieldSourceOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" wire:click="addField" class="btn btn-sm btn-success">
                                        <i class="voyager-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        @if (count($fields) > 0)
                            <table class="table table-bordered table-condensed" style="margin-bottom:10px">
                                <thead>
                                    <tr>
                                        <th style="width:40px">Var</th>
                                        <th>Label</th>
                                        <th style="width:110px">Componente</th>
                                        <th style="width:130px">Tipo entrada</th>
                                        <th style="width:130px">Tipo agregación</th>
                                        <th style="width:140px">Fuente</th>
                                        <th style="width:80px">Orden</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($fields as $index => $field)
                                        <tr wire:key="catalog-field-{{ $index }}">
                                            <td class="text-center"><strong>{{ chr(65 + $index) }}</strong></td>
                                            <td>
                                                <input wire:model="fields.{{ $index }}.label" type="text"
                                                    class="form-control input-sm @error("fields.{$index}.label") has-error @enderror" />
                                                @error("fields.{$index}.label")
                                                    <span class="help-block text-danger"
                                                        style="font-size:11px">{{ $message }}</span>
                                                @enderror
                                            </td>
                                            <td>
                                                <select wire:model="fields.{{ $index }}.componente"
                                                    class="form-control input-sm">
                                                    <option value="numerador">Numerador</option>
                                                    <option value="denominador">Denominador</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select wire:model.live="fields.{{ $index }}.input_type"
                                                    class="form-control input-sm">
                                                    @foreach ($dataFieldInputTypeOptions as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                @if (DataFieldInputType::tryFrom($field['input_type'] ?? '')?->requiresAggregationType())
                                                    <select wire:model="fields.{{ $index }}.aggregation_type"
                                                        class="form-control input-sm">
                                                        @foreach ($dataFieldAggregationTypeOptions as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <select wire:model="fields.{{ $index }}.fuente"
                                                    class="form-control input-sm">
                                                    <option value="">—</option>
                                                    @foreach ($dataFieldSourceOptions as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="text-center" style="white-space:nowrap">
                                                <button type="button" wire:click="moveUp({{ $index }})"
                                                    class="btn btn-xs btn-default"
                                                    @if ($index === 0) disabled @endif title="Subir">
                                                    <i class="glyphicon glyphicon-chevron-up"></i>
                                                </button>
                                                <button type="button" wire:click="moveDown({{ $index }})"
                                                    class="btn btn-xs btn-default"
                                                    @if ($index === count($fields) - 1) disabled @endif title="Bajar">
                                                    <i class="glyphicon glyphicon-chevron-down"></i>
                                                </button>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" wire:click="removeField({{ $index }})"
                                                    wire:confirm="¿Seguro que deseas eliminar este campo? La fórmula actual se vaciará para evitar tokens inconsistentes."
                                                    class="btn btn-xs btn-danger" title="Eliminar">
                                                    <i class="voyager-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-muted" style="margin-bottom:10px">
                                <em>No hay campos definidos. Agregue al menos uno para poder construir la fórmula.</em>
                            </p>
                        @endif

                        @if (count($fields) > 0)
                            @php
                                $formulaTokens = json_decode($formulaJson, true);
                                $formulaTokens = is_array($formulaTokens) ? $formulaTokens : [];
                            @endphp
                            <div id="indicator-catalog-formula-builder" wire:ignore.self
                                wire:key="formula-builder-{{ $editingId ?? 'new' }}-{{ $formulaRevision }}"
                                x-data="formulaBuilder(@js($formulaTokens))">
                                <strong>Constructor de fórmula</strong>
                                <div class="text-muted" style="font-size:11px;margin-top:6px">Vista completa</div>
                                <div class="well well-sm"
                                    style="margin-top:2px;min-height:28px;font-family:monospace;font-size:12px">
                                    <span x-text="previewText" :class="hasPreview ? '' : 'text-muted'"></span>
                                </div>
                                <div class="text-muted" style="font-size:11px;margin-top:8px">Edición: <span
                                        class="label label-info" style="font-size:11px;padding:1px 5px">min(</span>
                                    <span class="label label-info" style="font-size:11px;padding:1px 5px">,</span>
                                    <span class="label label-info" style="font-size:11px;padding:1px 5px">)</span>
                                    son parte fija; el resto son tokens. El cursor <strong
                                        style="color:#337ab7">|</strong> marca dónde se inserta o borra.
                                </div>
                                <div class="well well-sm"
                                    style="margin-top:2px;min-height:32px;display:flex;flex-wrap:wrap;align-items:center;gap:2px;font-family:monospace;font-size:13px">
                                    <template x-for="(seg, sidx) in segments"
                                        :key="'seg-' + sidx + '-' + seg.kind + (seg.gapIx ?? '') + (seg.text ?? '') + (seg
                                            .index ?? '')">
                                        <span style="display:inline-flex;align-items:center;gap:1px">
                                            <span x-show="seg.kind === 'gap' && cursorGapIndex === seg.gapIx"
                                                class="formula-cursor" style="color:#337ab7;font-weight:700"
                                                aria-hidden="true">|</span>
                                            <span x-show="seg.kind === 'chrome'" class="label label-info"
                                                style="margin:0;font-size:12px;padding:2px 6px"
                                                x-text="seg.text"></span>
                                            <span x-show="seg.kind === 'token' && seg.token"
                                                class="label label-default" style="margin:0;font-size:12px"
                                                x-text="describeToken(seg.token)"></span>
                                        </span>
                                    </template>
                                </div>
                                <div
                                    style="display:flex;align-items:center;gap:8px;margin-top:8px;margin-bottom:8px;font-size:12px;flex-wrap:wrap">
                                    <button type="button" class="btn btn-xs btn-default" style="min-width:32px"
                                        x-bind:disabled="!navCanLeft()" x-on:click="navLeft()"
                                        title="Mover el cursor a la posición anterior en la fórmula">&#8592;</button>
                                    <span class="text-muted" x-text="gapNavLabel"></span>
                                    <button type="button" class="btn btn-xs btn-default" style="min-width:32px"
                                        x-bind:disabled="!navCanRight()" x-on:click="navRight()"
                                        title="Mover el cursor a la posición siguiente en la fórmula">&#8594;</button>
                                </div>
                                <div style="margin-bottom:6px">
                                    @foreach ($fields as $i => $field)
                                        <button type="button" class="btn btn-xs btn-info" style="margin:2px"
                                            x-on:click="addToken('var', '{{ chr(65 + $i) }}')"
                                            title="{{ $field['label'] }}">
                                            {{ chr(65 + $i) }}
                                        </button>
                                    @endforeach
                                </div>
                                <div style="margin-bottom:8px">
                                    @foreach (['+', '-', '*', '/'] as $op)
                                        <button type="button" class="btn btn-xs btn-default"
                                            style="margin:2px;min-width:32px"
                                            x-on:click="addToken('op', '{{ $op }}')">{{ $op }}</button>
                                    @endforeach
                                    <button type="button" class="btn btn-xs btn-default"
                                        style="margin:2px;min-width:32px" x-on:click="addToken('paren', '(')">(
                                    </button>
                                    <button type="button" class="btn btn-xs btn-default"
                                        style="margin:2px;min-width:32px" x-on:click="addToken('paren', ')')">
                                        )</button>
                                    <button type="button" class="btn btn-xs btn-primary" style="margin:2px"
                                        x-on:click="addMin()">min()</button>
                                    <button type="button" class="btn btn-xs btn-warning" style="margin:2px"
                                        x-on:click="removeBeforeCursor()"
                                        title="Borrar token a la izquierda del cursor">&#8592;
                                        Borrar</button>
                                    <button type="button" class="btn btn-xs btn-danger" style="margin:2px"
                                        x-on:click="clearFormula">&#10005; Limpiar</button>
                                </div>
                                <div class="input-group input-group-sm" style="max-width:220px;margin-bottom:8px">
                                    <input type="number" step="any" class="form-control" x-model="literalValue"
                                        placeholder="Literal numérico (ej: 100)" />
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default"
                                            x-on:click="addLiteral">Añadir</button>
                                    </span>
                                </div>
                            </div>
                        @endif
