@extends('voyager::master')

@section('css')
    @include('voyager::compass.includes.styles')
@stop

@section('page_title', 'ADP — Catálogo de indicadores')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-edit"></i>
        {{ $item->exists ? 'Editar ítem' : 'Crear ítem' }} — Catálogo ADP
    </h1>
@stop

@section('content')
    <div class="page-content compass container-fluid">
        @include('voyager::alerts')

        <p>
            <a href="{{ route('adp.catalog.index') }}">← Volver al catálogo</a>
        </p>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="m-b-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="panel panel-bordered m-t-20">
            <div class="panel-heading">
                <p class="panel-title">Definición</p>
            </div>
            <div class="panel-body">
                <form method="post"
                    action="{{ $item->exists ? route('adp.catalog.update', $item) : route('adp.catalog.store') }}">
                    @csrf
                    @if ($item->exists)
                        @method('put')
                    @endif

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Clave</label>
                                <input type="text" name="clave" class="form-control"
                                    value="{{ old('clave', $item->clave) }}" required>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" name="nombre" class="form-control"
                                    value="{{ old('nombre', $item->nombre) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Objetivo (texto)</label>
                        <textarea name="objetivo" class="form-control" rows="2">{{ old('objetivo', $item->objetivo) }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Meta valor</label>
                                <input type="text" name="meta_valor" class="form-control"
                                    value="{{ old('meta_valor', $item->meta_valor) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Operador</label>
                                <select name="meta_operador" class="form-control">
                                    <option value="">—</option>
                                    @foreach (['>=', '>', '<=', '<', '='] as $op)
                                        <option value="{{ $op }}" @selected(old('meta_operador', $item->meta_operador) === $op)>{{ $op }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Umbral inferior</label>
                                <input type="text" name="umbral_inferior" class="form-control"
                                    value="{{ old('umbral_inferior', $item->umbral_inferior) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Umbral superior</label>
                                <input type="text" name="umbral_superior" class="form-control"
                                    value="{{ old('umbral_superior', $item->umbral_superior) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Método de cálculo</label>
                                <input type="text" name="calculation_method" class="form-control"
                                    value="{{ old('calculation_method', $item->calculation_method ?? 'percent') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Periodicidad</label>
                                <select name="periodicidad" class="form-control">
                                    @foreach (['ANUAL', 'SEMESTRAL', 'TRIMESTRAL'] as $p)
                                        <option value="{{ $p }}" @selected(old('periodicidad', $item->periodicidad ?? 'ANUAL') === $p)>
                                            {{ $p }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Estado</label>
                                <select name="estado" class="form-control">
                                    <option value="1" @selected((string) old('estado', (int) ($item->estado ?? 1)) === '1')>Activo</option>
                                    <option value="0" @selected((string) old('estado', (int) ($item->estado ?? 1)) === '0')>Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Fórmula (expression JSON)</label>
                        <textarea name="expression_json" class="form-control" rows="4" required>{{ old('expression_json', $formula->exists ? json_encode($formula->expression) : '[]') }}</textarea>
                        <p class="text-muted">Debe ser un JSON de tokens (patrón Metas/ADP). Ejemplo:
                            <code>[{\"type\":\"var\",\"key\":\"A\"}]</code></p>
                    </div>
                    <div class="form-group">
                        <label>Descripción fórmula</label>
                        <input type="text" name="formula_description" class="form-control"
                            value="{{ old('formula_description', $formula->description) }}">
                    </div>

                    <button type="submit"
                        class="btn btn-primary">{{ $item->exists ? 'Guardar cambios' : 'Crear' }}</button>
                </form>
            </div>
        </div>
    </div>
@stop
