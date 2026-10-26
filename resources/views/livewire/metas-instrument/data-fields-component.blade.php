{{-- Legacy: la maqueta unificada vive en IndicatorCatalogComponent (SDD-054). --}}
<div>
    @if (session()->has('fields_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('fields_message') }}
        </div>
    @endif

    @if (session()->has('fields_warning'))
        <div class="alert alert-warning alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            <i class="voyager-warning"></i> {{ session('fields_warning') }}
        </div>
    @endif

    @if ($errors->has('fields'))
        <div class="alert alert-danger">{{ $errors->first('fields') }}</div>
    @endif

    <h5>
        <i class="voyager-list"></i>
        Maqueta de campos — <strong>{{ $indicator->clave }}</strong>: {{ $indicator->nombre }}
    </h5>

    @php
        use App\Enums\Metas\DataFieldInputType;
    @endphp

    {{-- Campos existentes --}}
    @if (count($fields) > 0)
        <div style="margin-bottom:8px">
            <button wire:click="saveAll" class="btn btn-primary btn-sm">
                <i class="voyager-check"></i> Guardar maqueta
            </button>
            @if ($formulaDescription)
                <span class="text-muted" style="margin-left:10px;font-size:12px">
                    Fórmula actual: <code>{{ $formulaDescription }}</code>
                </span>
            @endif
        </div>
        <table class="table table-bordered table-condensed" style="margin-bottom:10px">
            <thead>
                <tr>
                    <th style="width:40px">Var</th>
                    <th>Label</th>
                    <th style="width:110px">Componente</th>
                    <th style="width:130px">Tipo entrada</th>
                    <th style="width:130px">Tipo agregación</th>
                    <th style="width:80px">Orden</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fields as $index => $field)
                    <tr wire:key="field-{{ $index }}">
                        <td class="text-center">
                            <strong>{{ chr(65 + $index) }}</strong>
                        </td>
                        <td>
                            <input wire:model="fields.{{ $index }}.label" type="text"
                                class="form-control input-sm @error("fields.{$index}.label") has-error @enderror"
                                placeholder="Label del campo" />
                            @error("fields.{$index}.label")
                                <span class="help-block text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                        </td>
                        <td>
                            <select wire:model="fields.{{ $index }}.componente" class="form-control input-sm">
                                <option value="numerador">Numerador</option>
                                <option value="denominador">Denominador</option>
                            </select>
                        </td>
                        <td>
                            <select wire:model.live="fields.{{ $index }}.input_type"
                                class="form-control input-sm">
                                @foreach ($dataFieldInputTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            @if (DataFieldInputType::tryFrom($field['input_type'] ?? '')?->requiresAggregationType())
                                <select wire:model="fields.{{ $index }}.aggregation_type"
                                    class="form-control input-sm">
                                    @foreach ($dataFieldAggregationTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center" style="white-space:nowrap">
                            <button wire:click="moveUp({{ $index }})" class="btn btn-xs btn-default"
                                @if ($index === 0) disabled @endif title="Subir">
                                <i class="glyphicon glyphicon-chevron-up"></i>
                            </button>
                            <button wire:click="moveDown({{ $index }})" class="btn btn-xs btn-default"
                                @if ($index === count($fields) - 1) disabled @endif title="Bajar">
                                <i class="glyphicon glyphicon-chevron-down"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <button wire:click="removeField({{ $index }})"
                                wire:confirm="¿Seguro que deseas eliminar este campo?" class="btn btn-xs btn-danger"
                                title="Eliminar">
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

    {{-- Constructor de fórmula (Alpine.js) --}}
    @if (count($fields) > 0)
        @error('formulaJson')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
        <div x-data="formulaBuilder" data-formula="{{ e($formulaJson) }}">
            <strong>Constructor de fórmula</strong>

            <div class="text-muted" style="font-size:11px;margin-top:6px">Vista completa</div>
            <div class="well well-sm" style="margin-top:2px;min-height:28px;font-family:monospace;font-size:12px">
                <span x-text="previewText" :class="hasPreview ? '' : 'text-muted'"></span>
            </div>
            <div class="text-muted" style="font-size:11px;margin-top:8px">Edición: <span class="label label-info"
                    style="font-size:11px;padding:1px 5px">min(</span> <span class="label label-info"
                    style="font-size:11px;padding:1px 5px">,</span> <span class="label label-info"
                    style="font-size:11px;padding:1px 5px">)</span> son fijos; el resto son tokens. El cursor <strong
                    style="color:#337ab7">|</strong> marca dónde se inserta o borra.</div>
            <div class="well well-sm"
                style="margin-top:2px;min-height:32px;display:flex;flex-wrap:wrap;align-items:center;gap:2px;font-family:monospace;font-size:13px">
                <template x-for="(seg, sidx) in segments"
                    :key="'seg-' + sidx + '-' + seg.kind + (seg.gapIx ?? '') + (seg.text ?? '') + (seg.index ?? '')">
                    <span style="display:inline-flex;align-items:center;gap:1px">
                        <span x-show="seg.kind === 'gap' && cursorGapIndex === seg.gapIx" class="formula-cursor"
                            style="color:#337ab7;font-weight:700" aria-hidden="true">|</span>
                        <span x-show="seg.kind === 'chrome'" class="label label-info"
                            style="margin:0;font-size:12px;padding:2px 6px" x-text="seg.text"></span>
                        <span x-show="seg.kind === 'token' && seg.token" class="label label-default"
                            style="margin:0;font-size:12px" x-text="describeToken(seg.token)"></span>
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

            {{-- Chips de variables --}}
            <div style="margin-bottom:6px">
                @foreach ($fields as $i => $field)
                    <button type="button" class="btn btn-xs btn-info" style="margin:2px"
                        x-on:click="addToken('var', '{{ chr(65 + $i) }}')" title="{{ $field['label'] }}">
                        {{ chr(65 + $i) }}
                    </button>
                @endforeach
            </div>

            {{-- Botones de operadores --}}
            <div style="margin-bottom:8px">
                @foreach (['+', '-', '*', '/'] as $op)
                    <button type="button" class="btn btn-xs btn-default" style="margin:2px;min-width:32px"
                        x-on:click="addToken('op', '{{ $op }}')">{{ $op }}</button>
                @endforeach
                <button type="button" class="btn btn-xs btn-default" style="margin:2px;min-width:32px"
                    x-on:click="addToken('paren', '(')">( </button>
                <button type="button" class="btn btn-xs btn-default" style="margin:2px;min-width:32px"
                    x-on:click="addToken('paren', ')')"> )</button>
                <button type="button" class="btn btn-xs btn-primary" style="margin:2px"
                    x-on:click="addMin()">min()</button>
                <button type="button" class="btn btn-xs btn-warning" style="margin:2px"
                    x-on:click="removeBeforeCursor()" title="Borrar token a la izquierda del cursor">&#8592;
                    Borrar</button>
                <button type="button" class="btn btn-xs btn-danger" style="margin:2px"
                    x-on:click="clearFormula">&#10005; Limpiar</button>
            </div>

            {{-- Literal numérico --}}
            <div class="input-group input-group-sm" style="max-width:220px;margin-bottom:8px">
                <input type="number" step="any" class="form-control" x-model="literalValue"
                    placeholder="Literal numérico (ej: 100)" />
                <span class="input-group-btn">
                    <button type="button" class="btn btn-default" x-on:click="addLiteral">Añadir</button>
                </span>
            </div>

        </div>
    @endif

    {{-- Agregar nuevo campo --}}
    <div class="well well-sm" style="padding:10px;margin-top:15px;margin-bottom:15px">
        <strong>Agregar campo</strong>
        <div class="row" style="margin-top:8px">
            <div class="col-md-4">
                <input wire:model="newFieldLabel" type="text"
                    class="form-control input-sm @error('newFieldLabel') has-error @enderror"
                    placeholder="Label del campo (ej: Egresos hospitalarios)" />
                @error('newFieldLabel')
                    <span class="help-block text-danger" style="font-size:11px">{{ $message }}</span>
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
                    <select wire:model="newFieldAggregationType" class="form-control input-sm"
                        title="Tipo de agregación">
                        @foreach ($dataFieldAggregationTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('newFieldAggregationType')
                        <span class="help-block text-danger" style="font-size:11px">{{ $message }}</span>
                    @enderror
                @endif
            </div>
            <div class="col-md-2">
                <button wire:click="addField" class="btn btn-sm btn-success">
                    <i class="voyager-plus"></i> Agregar
                </button>
            </div>
        </div>
    </div>

    {{-- Botón guardar --}}
    <div style="margin-top:10px">
        <button wire:click="saveAll" class="btn btn-primary btn-sm">
            <i class="voyager-check"></i> Guardar maqueta
        </button>
        @if ($formulaDescription)
            <span class="text-muted" style="margin-left:10px;font-size:12px">
                Fórmula actual: <code>{{ $formulaDescription }}</code>
            </span>
        @endif
    </div>
</div>

@script
    <script>
        Alpine.data('formulaBuilder', () => ({
            tokens: [],
            literalValue: '',
            segments: [],
            gaps: [],
            cursorGapIndex: 0,

            init() {
                try {
                    const parsed = JSON.parse(this.$el.dataset.formula || '[]');
                    this.tokens = Array.isArray(parsed) ? this.sanitizeTokens(parsed) : [];
                } catch (e) {
                    this.tokens = [];
                }
                this.rebuildLayout();
                this.cursorGapIndex = Math.max(0, this.gaps.length - 1);
                this.$watch('tokens', (value) => {
                    this.$wire.set('formulaJson', JSON.stringify(value));
                }, {
                    deep: true,
                });
            },

            get preview() {
                return this.describeTokens(this.tokens).trim();
            },

            get previewText() {
                const text = this.preview;

                return text !== '' ? text : '— fórmula vacía —';
            },

            get hasPreview() {
                return this.previewText !== '— fórmula vacía —';
            },

            getArrayAtPath(path) {
                if (!Array.isArray(path) || path.length === 0) {
                    return this.tokens;
                }

                let cur = this.tokens;
                for (let p = 0; p < path.length; p += 2) {
                    const ti = path[p];
                    const ai = path[p + 1];
                    const fn = cur[ti];
                    if (!fn || fn.type !== 'fn') {
                        return this.tokens;
                    }
                    if (!Array.isArray(fn.args)) {
                        fn.args = [
                            [],
                            [],
                        ];
                    }
                    if (!Array.isArray(fn.args[ai])) {
                        fn.args[ai] = [];
                    }
                    cur = fn.args[ai];
                }

                return cur;
            },

            computeSegmentsAndGaps(tokensArr) {
                const gaps = [];
                const segments = [];
                const visit = (arr, path) => {
                    if (!Array.isArray(arr)) {
                        return;
                    }
                    for (let i = 0; i <= arr.length; i++) {
                        const gapIx = gaps.length;
                        gaps.push({
                            path: [...path],
                            insertIndex: i,
                        });
                        segments.push({
                            kind: 'gap',
                            gapIx,
                            path: [...path],
                            insertIndex: i,
                        });
                        if (i < arr.length) {
                            const t = arr[i];
                            if (!t || typeof t !== 'object' || !t.type) {
                                continue;
                            }
                            if (t.type === 'fn' && t.name === 'min') {
                                if (!Array.isArray(t.args)) {
                                    t.args = [
                                        [],
                                        [],
                                    ];
                                }
                                if (!Array.isArray(t.args[0])) {
                                    t.args[0] = [];
                                }
                                if (!Array.isArray(t.args[1])) {
                                    t.args[1] = [];
                                }
                                segments.push({
                                    kind: 'chrome',
                                    text: 'min(',
                                });
                                visit(t.args[0], path.concat(i, 0));
                                segments.push({
                                    kind: 'chrome',
                                    text: ',',
                                });
                                visit(t.args[1], path.concat(i, 1));
                                segments.push({
                                    kind: 'chrome',
                                    text: ')',
                                });
                            } else {
                                segments.push({
                                    kind: 'token',
                                    path: [...path],
                                    index: i,
                                    token: t,
                                });
                            }
                        }
                    }
                };
                visit(tokensArr, []);

                return {
                    gaps,
                    segments,
                };
            },

            rebuildLayout() {
                const {
                    gaps,
                    segments,
                } = this.computeSegmentsAndGaps(this.tokens);
                this.gaps = gaps;
                this.segments = segments;
                if (this.gaps.length === 0) {
                    this.gaps = [{
                        path: [],
                        insertIndex: 0,
                    }];
                    this.segments = [{
                        kind: 'gap',
                        gapIx: 0,
                        path: [],
                        insertIndex: 0,
                    }];
                }
                if (this.cursorGapIndex >= this.gaps.length) {
                    this.cursorGapIndex = Math.max(0, this.gaps.length - 1);
                }
            },

            findGapIndex(path, insertIndex) {
                return this.gaps.findIndex((g) => g.insertIndex === insertIndex &&
                    g.path.length === path.length &&
                    g.path.every((v, j) => v === path[j]));
            },

            get gapNavLabel() {
                const g = this.gaps[this.cursorGapIndex];
                if (!g || g.path.length === 0) {
                    return 'Expresión principal';
                }

                return 'Dentro de min()';
            },

            navCanLeft() {
                return this.cursorGapIndex > 0;
            },

            navCanRight() {
                return this.cursorGapIndex < this.gaps.length - 1;
            },

            navLeft() {
                if (this.navCanLeft()) {
                    this.cursorGapIndex--;
                }
            },

            navRight() {
                if (this.navCanRight()) {
                    this.cursorGapIndex++;
                }
            },

            sanitizeTokens(tokens) {
                if (!Array.isArray(tokens)) {
                    return [];
                }

                return tokens
                    .filter((token) => token && typeof token === 'object' && token.type)
                    .map((token) => {
                        if (token.type === 'fn') {
                            const args = Array.isArray(token.args) ? token.args : [];

                            return {
                                ...token,
                                args: [
                                    this.sanitizeTokens(args[0]),
                                    this.sanitizeTokens(args[1]),
                                ],
                            };
                        }

                        return token;
                    });
            },

            describeTokens(tokens) {
                if (!Array.isArray(tokens)) {
                    return '';
                }

                return tokens
                    .map((token) => this.describeToken(token))
                    .join(' ');
            },

            describeToken(token) {
                if (!token || typeof token !== 'object' || !token.type) {
                    return '';
                }

                if (token.type === 'fn') {
                    const args = Array.isArray(token.args) ? token.args : [];
                    const left = Array.isArray(args[0]) ? this.describeTokens(args[0]) : '';
                    const right = Array.isArray(args[1]) ? this.describeTokens(args[1]) : '';

                    return `${token.name ?? 'min'}(${left}, ${right})`;
                }

                if (token.type === 'var') {
                    return token.key ?? '';
                }

                return token.value ?? '';
            },

            syncTokens() {
                this.tokens = [...this.tokens];
                this.rebuildLayout();
            },

            addMin() {
                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                const fnTok = {
                    type: 'fn',
                    name: 'min',
                    args: [
                        [],
                        [],
                    ],
                };
                arr.splice(g.insertIndex, 0, fnTok);
                this.syncTokens();
                const innerPath = [...g.path, g.insertIndex, 0];
                const ix = this.findGapIndex(innerPath, 0);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            addToken(type, value) {
                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                const token = type === 'var' ? {
                    type: 'var',
                    key: value,
                } : {
                    type: type,
                    value: value,
                };
                arr.splice(g.insertIndex, 0, token);
                this.syncTokens();
                const ix = this.findGapIndex(g.path, g.insertIndex + 1);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            addLiteral() {
                const val = this.literalValue.trim();
                if (val === '' || isNaN(parseFloat(val))) {
                    return;
                }

                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                arr.splice(g.insertIndex, 0, {
                    type: 'lit',
                    value: val,
                });
                this.literalValue = '';
                this.syncTokens();
                const ix = this.findGapIndex(g.path, g.insertIndex + 1);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            removeBeforeCursor() {
                const g = this.gaps[this.cursorGapIndex];
                const arr = this.getArrayAtPath(g.path);
                if (g.insertIndex <= 0) {
                    return;
                }

                arr.splice(g.insertIndex - 1, 1);
                this.syncTokens();
                const ix = this.findGapIndex(g.path, g.insertIndex - 1);
                if (ix >= 0) {
                    this.cursorGapIndex = ix;
                }
            },

            clearFormula() {
                this.tokens = [];
                this.cursorGapIndex = 0;
                this.syncTokens();
            },
        }));
    </script>
@endscript
