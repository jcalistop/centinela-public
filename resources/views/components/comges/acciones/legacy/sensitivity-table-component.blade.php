<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <p class="text-bold">Meta para el SS</p>
            <h4>{{ $comSensitivityTable->meta_numero }}</h4>
        </div>
        <div class="col-md-8" style="display: flex; justify-content: center;">
            <table class="table-bordered">
                <thead style="background-color: #016CBA;color: white;">
                    <tr>
                        <th class="text-center" width="50%">Resultado obtenido</th>
                        <th class="text-center">Porcentaje de cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (json_decode($comSensitivityTable->valores) as $valor)
                        @php
                            $operadorMinimo = $valor->operador_minimo ?? '>=';
                            $operadorMaximo = $valor->operador_maximo ?? '<';
                        @endphp
                        <tr>
                            <td class="text-center">
                                {!! !is_null($valor->valor_minimo)
                                    ? $valor->valor_minimo . ' ' . ($operadorMinimo === '>' ? '&lt;' : '&le;')
                                    : '' !!}
                                X
                                {!! !is_null($valor->valor_maximo)
                                    ? ($operadorMaximo === '<=' ? '&le;' : '&lt;') . ' ' . $valor->valor_maximo
                                    : '' !!}
                            </td>
                            <td class="text-center">{{ $valor->cumplimiento }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
