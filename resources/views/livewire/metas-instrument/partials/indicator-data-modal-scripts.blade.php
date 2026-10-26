@php
    $attachDownloadGuardsOnLoad = $attachDownloadGuardsOnLoad ?? false;
@endphp

<script>
    @include('partials.download-guard')
    window.METAS_INSTRUMENT_PERIODO_LABELS = @json(\App\Enums\Metas\MetInstrumentPeriodo::cortaPorClave());
    window.METAS_INDICATOR_DATA_MODAL_ATTACH_GUARDS_ON_LOAD = @json($attachDownloadGuardsOnLoad);
</script>
<script
    src="{{ asset('js/metas-indicator-data-modal.js') }}?v={{ filemtime(public_path('js/metas-indicator-data-modal.js')) }}">
</script>
