@extends('errors.master')

@section('title', 'Error 503')

@section('content')
    <div class="empty">
        <div class="empty-header">Estamos en mantención</div>
        <p class="empty-title">Trabajando para mejorar el sistema.</p>
        <p class="empty-subtitle text-muted">
            Por favor intenta ingresar en un momento mas.
        </p>
        <div class="empty-action">
            <a href="{{ url()->previous() }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M5 12l14 0" />
                    <path d="M5 12l6 6" />
                    <path d="M5 12l6 -6" />
                </svg>
                Volver atrás
            </a>
            <pre class="text-start my-5">
                        <p>{{ $exception->getMessage() }}</p>
                    </pre>
        </div>
    </div>
@stop
