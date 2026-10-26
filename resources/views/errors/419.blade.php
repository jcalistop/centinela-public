@extends('errors.master')

@section('title', __('auth.page_title_session_expired'))

@section('content')
    <div class="empty">
        <div class="empty-header">{{ __('auth.page_title_session_expired') }}</div>
        <p class="empty-title">{{ __('http-statuses.419') }}</p>
        <p class="empty-subtitle text-muted">
            {{ __('auth.page_expired_help') }}
        </p>
        <p class="empty-subtitle text-muted small">
            {{ __('auth.page_expired_code_hint', ['code' => '419']) }}
        </p>
        <div class="empty-action">
            <a href="{{ route('voyager.login', ['session_expired' => 1]) }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M5 12l14 0" />
                    <path d="M5 12l6 6" />
                    <path d="M5 12l6 -6" />
                </svg>
                {{ __('auth.go_to_login') }}
            </a>
            @if (config('app.debug'))
                <pre class="text-start my-5 small text-muted"><code>{{ $exception->getMessage() }}</code></pre>
            @endif
        </div>
    </div>
@stop
