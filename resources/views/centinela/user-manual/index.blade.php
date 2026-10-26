@extends('voyager::master')

@section('page_title', 'Manual de usuario')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title">Manual de usuario</h3>
                    </div>
                    <div class="panel-body">
                        @if (empty($pages))
                            <p>No hay manuales disponibles.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Estado</th>
                                            <th>Actualizado</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pages as $page)
                                            <tr>
                                                <td>{{ $page['title'] }}</td>
                                                <td>{{ $page['status'] ?? '-' }}</td>
                                                <td>{{ $page['updated_at'] ?? '-' }}</td>
                                                <td class="text-right">
                                                    <a class="btn btn-sm btn-primary"
                                                        href="{{ route('user-manual.show', ['id' => $page['id']]) }}">
                                                        Abrir
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

