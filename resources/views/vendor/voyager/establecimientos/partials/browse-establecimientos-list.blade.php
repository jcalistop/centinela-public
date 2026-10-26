@php
    $listItems = $browseContext['listItems'] ?? [];
    $listPerPage = (int) ($browseContext['listPerPage'] ?? 15);
    $listColumns = $browseContext['listColumns'] ?? [];
    $exportUrl = \Illuminate\Support\Facades\Route::has('voyager.establecimientos.export')
        ? route('voyager.establecimientos.export')
        : url('/admin/establecimientos/export');
@endphp
@if (count($listItems) > 0)
    <div class="cen-read-section cen-browse-establecimientos-list-section">
        <div class="cen-read-section__head cen-read-section__head--with-actions">
            <i class="voyager-shop"></i>
            <h4 class="cen-read-section__title">
                Listado de establecimientos
                <span class="cen-read-count-badge" id="cen-est-list-title-badge">{{ count($listItems) }}</span>
            </h4>
            <div class="pull-right cen-est-list-head-actions">
                <a href="{{ $exportUrl }}" class="btn btn-sm btn-success centinela-download-guard-link"
                    data-centinela-download-fallback="establecimientos.xlsx"
                    title="Descargar todos los campos registrados en la base de datos">
                    <i class="voyager-download" style="color: #fff;"></i>
                    <span class="hidden-xs">Descargar establecimientos</span>
                </a>
            </div>
        </div>

        <div id="cen-est-list-root" class="cen-browse-establecimientos-list" data-list-items='@json($listItems)'
            data-columns='@json($listColumns)' data-per-page="{{ $listPerPage }}"
            data-actions-label="{{ __('voyager::generic.actions') }}">
            <div class="cen-read-table-wrap">
                <table id="cen-est-list-table" class="table table-hover cen-read-data-table">
                    <thead id="cen-est-list-thead"></thead>
                    <tbody id="cen-est-list-tbody"></tbody>
                </table>
            </div>
            <div id="cen-est-list-empty" class="cen-read-empty" style="display:none;">
                <i class="voyager-shop"></i>
                <p><strong>Sin resultados</strong></p>
                <p style="font-size:13px;">Ningún establecimiento coincide con los filtros aplicados.</p>
            </div>
            <div class="cen-browse-footer" id="cen-est-list-footer">
                <div role="status" class="show-res" id="cen-est-list-summary" aria-live="polite"></div>
                <nav aria-label="Paginación del listado">
                    <ul class="pagination" id="cen-est-list-pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
@endif
