<button type="button" onclick="addRow()" class="btn btn-sm btn-success"><i class="voyager-list-add"></i></button>
<div id="input-container">
    @foreach (json_decode($dataTypeContent->valores) as $valor)
        <div class="row" id="row-{{ $loop->iteration }}">
            <div class="col-sm-1 text-right" style="padding-top:0.6rem;"><span>{{ $loop->iteration }}</span></div>
            <div class="col-md-2">
                <input type="number" step="0.01" class="form-control"
                    name="valores[{{ $loop->iteration }}][valor_minimo]" placeholder="Valor mínimo"
                    value="{{ $valor->valor_minimo }}" />
            </div>
            <div class="col-sm-1">
                <select class="form-control" name="valores[{{ $loop->iteration }}][operador_minimo]">
                    <option value=">=" {{ ($valor->operador_minimo ?? '>=') === '>=' ? 'selected' : '' }}>&ge;
                    </option>
                    <option value=">" {{ ($valor->operador_minimo ?? '>=') === '>' ? 'selected' : '' }}>&gt;
                    </option>
                </select>
            </div>
            <div class="col-sm-1 text-center" style="padding-top:0.6rem;"> X </div>
            <div class="col-sm-1">
                <select class="form-control" name="valores[{{ $loop->iteration }}][operador_maximo]">
                    <option value="<" {{ ($valor->operador_maximo ?? '<') === '<' ? 'selected' : '' }}>&lt;
                    </option>
                    <option value="<=" {{ ($valor->operador_maximo ?? '<') === '<=' ? 'selected' : '' }}>&le;
                    </option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" step="0.01" class="form-control"
                    name="valores[{{ $loop->iteration }}][valor_maximo]" placeholder="Valor máximo"
                    value="{{ $valor->valor_maximo }}" />
            </div>
            <div class="col-sm-1 text-center" style="padding-top:0.6rem;"> = </div>
            <div class="col-md-2">
                <input type="number" class="form-control" name="valores[{{ $loop->iteration }}][cumplimiento]"
                    placeholder="Cumplimiento" value="{{ $valor->cumplimiento }}" />
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-danger delete-btn"
                    onclick="removeRow({{ $loop->iteration }})"><i class="voyager-trash"></i></button>
            </div>
        </div>
    @endforeach
</div>
<div id="valores-validation-feedback" class="alert alert-danger" style="display:none; margin-top: 10px;"></div>

@push('javascript')
    <script>
        let rowCount = 0;

        function getMetaNumeroInput() {
            return document.querySelector('.form-edit-add input[name="meta_numero"]');
        }

        function getMetaFromHighestScoreRow(rows) {
            const rowsWithScore = rows.filter((row) => row.cumplimiento !== null);

            if (rowsWithScore.length === 0) {
                return null;
            }

            const highestScore = Math.max(...rowsWithScore.map((row) => row.cumplimiento));
            const targetRow = rowsWithScore.find((row) => row.cumplimiento === highestScore);

            if (!targetRow) {
                return null;
            }

            if (targetRow.min !== null && targetRow.max === null) {
                return targetRow.min;
            }

            if (targetRow.max !== null && targetRow.min === null) {
                return targetRow.max;
            }

            if (targetRow.min !== null && targetRow.max !== null) {
                return targetRow.min;
            }

            return null;
        }

        function syncMetaNumeroDefault(rows) {
            const metaInput = getMetaNumeroInput();

            if (!metaInput) {
                return;
            }

            const inferredMeta = getMetaFromHighestScoreRow(rows);
            const shouldAutoUpdate = metaInput.value === '' || metaInput.dataset.autoDefault === '1';

            if (!shouldAutoUpdate) {
                return;
            }

            if (inferredMeta === null) {
                metaInput.value = '';
                metaInput.dataset.autoDefault = '1';
                return;
            }

            metaInput.value = inferredMeta;
            metaInput.dataset.autoDefault = '1';
        }

        function addRow() {
            const container = document.getElementById('input-container');
            const newRow = document.createElement('div');
            newRow.classList.add('row');
            newRow.id = 'row-' + (container.childElementCount);
            newRow.innerHTML = `
                <div class="col-sm-1 text-right" style="padding-top:0.6rem;"><span>${container.childElementCount + 1}</span></div>
                <div class="col-sm-2">
                    <input type="number" step="0.01" class="form-control" name="valores[${container.childElementCount}][valor_minimo]" placeholder="Valor mínimo" />
                </div>
                <div class="col-sm-1">
                    <select class="form-control" name="valores[${container.childElementCount}][operador_minimo]">
                        <option value=">=">&ge;</option>
                        <option value=">">&gt;</option>
                    </select>
                </div>
                <div class="col-sm-1 text-center" style="padding-top:0.6rem;"> X </div>
                <div class="col-sm-1">
                    <select class="form-control" name="valores[${container.childElementCount}][operador_maximo]">
                        <option value="<">&lt;</option>
                        <option value="<=">&le;</option>
                    </select>
                </div>
                <div class="col-sm-2">
                    <input type="number" step="0.01" class="form-control" name="valores[${container.childElementCount}][valor_maximo]" placeholder="Valor máximo" />
                </div>
                <div class="col-sm-1 text-center" style="padding-top:0.6rem;"> = </div>
                <div class="col-sm-2">
                    <input type="number" class="form-control" name="valores[${container.childElementCount}][cumplimiento]" placeholder="Cumplimiento" />
                </div>
                <div class="col-sm-1">
                    <button type="button" class="btn btn-sm btn-danger delete-btn" onclick="removeRow(${container.childElementCount})"><i class="voyager-trash"></i></button>
                </div>
            `;
            container.appendChild(newRow);
            updateRowNumbers();
            syncMetaNumeroDefault(collectRows());
        }

        function removeRow(index) {
            const row = document.getElementById('row-' + index);
            if (row) {
                row.parentNode.removeChild(row);
                updateRowNumbers();
                syncMetaNumeroDefault(collectRows());
            }
        }

        function updateRowNumbers() {
            const rows = document.querySelectorAll('#input-container .row');
            rows.forEach((row, index) => {
                row.id = 'row-' + index;
                row.querySelector('span').textContent = index + 1;
                row.querySelectorAll('input, select').forEach(element => {
                    const name = element.name.split('[')[0];
                    const field = element.name.split('[')[2].split(']')[0];
                    element.name = name + '[' + index + '][' + field + ']';
                });
                const deleteButton = row.querySelector('.delete-btn');
                if (deleteButton) {
                    deleteButton.setAttribute('onclick', `removeRow(${index})`);
                }
            });
            // Asegurarse de que el botón eliminar solo esté presente en filas adicionales
            if (rows.length === 1) {
                const deleteButton = rows[0].querySelector('.delete-btn');
                if (deleteButton) {
                    deleteButton.remove();
                }
            } else {
                rows.forEach((row, index) => {
                    if (index !== 0 && !row.querySelector('.delete-btn')) {
                        const deleteButton = document.createElement('button');
                        deleteButton.classList.add('btn btn-sm btn-danger delete-btn');
                        deleteButton.setAttribute('type', 'button');
                        deleteButton.setAttribute('onclick', `removeRow(${index})`);
                        row.appendChild(deleteButton);
                    }
                });
            }
        }

        function parseNullableFloat(value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }

            const parsed = parseFloat(value);
            return Number.isNaN(parsed) ? null : parsed;
        }

        function collectRows() {
            const rows = document.querySelectorAll('#input-container .row');

            return Array.from(rows).map((row, index) => {
                const minimoInput = row.querySelector('input[name*="[valor_minimo]"]');
                const maximoInput = row.querySelector('input[name*="[valor_maximo]"]');
                const cumplimientoInput = row.querySelector('input[name*="[cumplimiento]"]');
                const operadorMinimo = row.querySelector('select[name*="[operador_minimo]"]')?.value ?? '>=';
                const operadorMaximo = row.querySelector('select[name*="[operador_maximo]"]')?.value ?? '<';

                return {
                    rowNumber: index + 1,
                    min: parseNullableFloat(minimoInput?.value),
                    max: parseNullableFloat(maximoInput?.value),
                    cumplimiento: parseNullableFloat(cumplimientoInput?.value),
                    operadorMinimo,
                    operadorMaximo,
                };
            });
        }

        function isRangeBefore(left, right) {
            if (left.max === null || right.min === null) {
                return false;
            }

            if (left.max < right.min) {
                return true;
            }

            if (left.max > right.min) {
                return false;
            }

            return left.operadorMaximo === '<' || right.operadorMinimo === '>';
        }

        function validateRows(rows) {
            const errors = [];

            rows.forEach((row) => {
                if (row.cumplimiento === null) {
                    errors.push(`Fila ${row.rowNumber}: el puntaje es obligatorio.`);
                }

                if (row.min === null && row.max === null) {
                    errors.push(`Fila ${row.rowNumber}: debe tener mínimo o máximo.`);
                }

                if (row.min !== null && row.max !== null && row.min > row.max) {
                    errors.push(`Fila ${row.rowNumber}: el mínimo no puede ser mayor al máximo.`);
                }

                if (row.min !== null && row.max !== null && row.min === row.max && row.operadorMinimo === '>' && row
                    .operadorMaximo === '<') {
                    errors.push(`Fila ${row.rowNumber}: rango vacío con operadores estrictos (> y <).`);
                }
            });

            for (let i = 0; i < rows.length; i++) {
                for (let j = i + 1; j < rows.length; j++) {
                    const overlap = !isRangeBefore(rows[i], rows[j]) && !isRangeBefore(rows[j], rows[i]);

                    if (overlap) {
                        errors.push(`Filas ${rows[i].rowNumber} y ${rows[j].rowNumber}: tienen solapamiento de rangos.`);
                    }
                }
            }

            const sortedRows = [...rows].sort((a, b) => {
                if (a.min === null && b.min === null) {
                    return 0;
                }

                if (a.min === null) {
                    return -1;
                }

                if (b.min === null) {
                    return 1;
                }

                return a.min - b.min;
            });

            for (let i = 0; i < sortedRows.length - 1; i++) {
                const left = sortedRows[i];
                const right = sortedRows[i + 1];

                if (left.max === null || right.min === null) {
                    continue;
                }

                const hasGap = left.max < right.min || (left.max === right.min && left.operadorMaximo === '<' &&
                    right.operadorMinimo === '>');

                if (hasGap) {
                    errors.push(
                        `Filas ${left.rowNumber} y ${right.rowNumber}: existe un hueco de cobertura entre los rangos.`
                    );
                }
            }

            return errors;
        }

        function renderValidationErrors(errors) {
            const feedback = document.getElementById('valores-validation-feedback');

            if (!feedback) {
                return;
            }

            if (errors.length === 0) {
                feedback.style.display = 'none';
                feedback.innerHTML = '';
                return;
            }

            feedback.style.display = 'block';
            feedback.innerHTML = '<strong>Corrija los siguientes errores:</strong><ul>' +
                errors.map((error) => `<li>${error}</li>`).join('') +
                '</ul>';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.form-edit-add');
            const metaInput = getMetaNumeroInput();

            if (!form) {
                return;
            }

            if (metaInput) {
                metaInput.dataset.autoDefault = metaInput.value === '' ? '1' : '0';
                metaInput.addEventListener('input', function() {
                    metaInput.dataset.autoDefault = '0';
                });
            }

            form.addEventListener('input', function(event) {
                if (!event.target.matches(
                        'input[name*="[cumplimiento]"], input[name*="[valor_minimo]"], input[name*="[valor_maximo]"]'
                    )) {
                    return;
                }

                syncMetaNumeroDefault(collectRows());
            });

            syncMetaNumeroDefault(collectRows());

            form.addEventListener('submit', function(event) {
                const rows = collectRows();
                const errors = validateRows(rows);
                renderValidationErrors(errors);

                if (errors.length > 0) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        });
    </script>
@endpush
