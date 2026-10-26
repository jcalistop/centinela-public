@extends('voyager::master')

@section('page_title', __('voyager::generic.viewing') . ' ' . $dataType->getTranslatedAttribute('display_name_plural'))

@section('page_header')
    @include('voyager::establecimientos.partials.browse-page-header')
@stop

@section('content')
    @php
        $browseIndexUrl = \Illuminate\Support\Facades\Route::has('voyager.establecimientos.index')
            ? route('voyager.establecimientos.index')
            : url('/admin/establecimientos');
    @endphp
    <div class="page-content browse compass establecimientos-compass cen-compass-ui container-fluid">
        @include('voyager::establecimientos.partials.browse-compass-styles')
        @include('voyager::establecimientos.partials.read-detail-styles')
        @include('voyager::alerts')

        <ul class="nav nav-tabs" role="tablist" style="margin-bottom:0">
            <li role="presentation" class="{{ ($activeTab ?? 'establecimientos') === 'establecimientos' ? 'active' : '' }}">
                <a href="{{ $browseIndexUrl }}?tab=establecimientos" role="tab">
                    <i class="voyager-shop"></i> Establecimientos
                </a>
            </li>
            @if (!empty($canViewSyncHistory))
                <li role="presentation" class="{{ ($activeTab ?? '') === 'historial' ? 'active' : '' }}">
                    <a href="{{ $browseIndexUrl }}?tab=historial" role="tab">
                        <i class="voyager-cloud-download"></i> Historial sincronización
                    </a>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div role="tabpanel"
                class="tab-pane {{ ($activeTab ?? 'establecimientos') === 'establecimientos' ? 'active' : '' }}"
                id="tab-establecimientos">
                @include('voyager::establecimientos.partials.browse-tab-establecimientos')
            </div>

            @if (!empty($canViewSyncHistory))
                <div role="tabpanel" class="tab-pane {{ ($activeTab ?? '') === 'historial' ? 'active' : '' }}"
                    id="tab-historial">
                    @include('voyager::establecimientos.partials.browse-tab-sync-history')
                </div>
            @endif
        </div>
    </div>
@stop

@section('css')
    @if (($activeTab ?? 'establecimientos') === 'establecimientos' && ($browseContext['totalCount'] ?? 0) > 0)
        @if (($browseContext['georeferencedCount'] ?? 0) > 0)
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        @endif
    @endif
@stop

@section('javascript')
    @if (($activeTab ?? '') === 'historial' || !empty($canViewSyncHistory))
        <script>
            (function() {
                if (typeof jQuery === 'undefined') {
                    return;
                }
                jQuery(function($) {
                    $('.establecimientos-compass [data-toggle="tooltip"]').tooltip({
                        container: 'body'
                    });
                });
            })();
        </script>
    @endif
    @if (($activeTab ?? 'establecimientos') === 'establecimientos' && ($browseContext['totalCount'] ?? 0) > 0)
        @if (($browseContext['georeferencedCount'] ?? 0) > 0)
            @include('voyager::establecimientos.partials.browse-establecimientos-leaflet')
        @endif
        @include('voyager::establecimientos.partials.browse-establecimientos-script')
        <script>
            (function() {
                @include('partials.download-guard')

                document.addEventListener('DOMContentLoaded', function() {
                    attachCentinelaDownloadGuards(document.querySelector('.establecimientos-compass'));
                });
            })();
        </script>
    @endif
@stop
