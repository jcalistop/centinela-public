@extends('voyager::master')

@section('page_title', $pageTitle ?? '¿Qué es Centinela?')

@section('css')
    @include('centinela.acerca-de.partials.compass-shell')
@stop

@section('content')
    <div class="page-content compass container-fluid acerca-de-compass cen-compass-ui">
        @include('voyager::alerts')

        @include('centinela.acerca-de.partials.centinela-title')

        @include('centinela.acerca-de.partials.acerca-de-nav', [
            'navPages' => $navPages ?? [],
            'currentSlug' => $currentSlug ?? 'resumen',
        ])

        <x-centinela.compass.dashboard-block :title="$pageTitle" :icon="$pageIcon ?? 'voyager-question'">
            <div class="acerca-de-markdown">
                {!! $markdownHtml !!}
            </div>
        </x-centinela.compass.dashboard-block>
    </div>
@endsection

@push('javascript')
    <script>
        document.addEventListener('DOMContentLoaded', async function() {
            const mermaidBlocks = document.querySelectorAll('pre.mermaid');
            if (mermaidBlocks.length === 0) {
                return;
            }

            const loadMermaid = function() {
                if (window.mermaid) {
                    return Promise.resolve(window.mermaid);
                }

                return new Promise(function(resolve, reject) {
                    const script = document.createElement('script');
                    script.src = @json(asset('vendor/mermaid/mermaid.min.js'));
                    script.onload = function() {
                        resolve(window.mermaid);
                    };
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            };

            const mermaid = await loadMermaid();
            mermaid.initialize({
                startOnLoad: false,
                theme: 'neutral',
                securityLevel: 'strict',
            });
            await mermaid.run({
                querySelector: 'pre.mermaid',
            });
        });
    </script>
@endpush
