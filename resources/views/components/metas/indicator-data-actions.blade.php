<div {{ $attributes->class(['metas-results-detail-data-actions']) }}>
    <button type="button" class="btn btn-xs btn-warning" wire:click="{{ $wireClick }}">
        <i class="voyager-bar-chart"></i> Visualizar datos
    </button>
    @if ($downloadUrl)
        <a href="{{ $downloadUrl }}" class="btn btn-xs btn-primary centinela-download-guard-link">
            <i class="voyager-download"></i> Descargar datos
        </a>
    @endif
</div>
