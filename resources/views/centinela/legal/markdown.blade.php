@extends('centinela.layouts.legal-minimal')

@section('title', $pageTitle)

@push('css')
    <style>
        .legal-minimal-body {
            background: #f5f5f5;
            font-family: 'Open Sans', sans-serif;
        }

        .legal-markdown {
            background: #fff;
            border: 1px solid #e1e1e8;
            border-radius: 4px;
            padding: 1.5rem 1.75rem;
        }

        .legal-markdown h1 {
            font-size: 1.75rem;
            margin-top: 0;
            margin-bottom: 1rem;
        }

        .legal-markdown h2 {
            font-size: 1.35rem;
            margin-top: 1.75rem;
            margin-bottom: 0.75rem;
        }

        .legal-markdown h3 {
            font-size: 1.15rem;
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .legal-markdown table {
            width: 100%;
            margin-bottom: 1.5rem;
        }

        .legal-markdown pre.mermaid {
            background: #f7f7f9;
            border: 1px solid #e1e1e8;
            border-radius: 4px;
            padding: 1rem;
            overflow-x: auto;
        }

        .legal-markdown blockquote {
            border-left: 4px solid #22A7F0;
            margin: 1rem 0;
            padding: 0.5rem 1rem;
            color: #555;
            background: #fafafa;
        }
    </style>
@endpush

@section('content')
    <p class="text-muted" style="margin-bottom: 1rem;">
        @auth
            <a href="{{ url('/admin') }}">&larr; Volver al panel</a>
        @else
            <a href="{{ route('voyager.login') }}">&larr; Volver al inicio de sesión</a>
        @endauth
    </p>
    <div class="legal-markdown">
        {!! $markdownHtml !!}
    </div>
@endsection
