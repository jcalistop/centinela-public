@extends('errors.master')

@section('title', 'Error 403')

@section('content')
    <div class="empty">
        <div class="empty-header">Error 403</div>
        <p class="empty-title">Oops… No puedes entrar aquí.</p>
        <p class="empty-subtitle text-muted">
            No estás autorizado para ver esta página.
        </p>
        <div class="empty-action">
            <a href="{{ route('voyager.login') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M5 12l14 0" />
                    <path d="M5 12l6 6" />
                    <path d="M5 12l6 -6" />
                </svg>
                Volver al inicio
            </a>
            <pre class="text-start my-5">
                        <p>{{ $exception->getMessage() }}</p>
                    </pre>
        </div>
    </div>
@stop
