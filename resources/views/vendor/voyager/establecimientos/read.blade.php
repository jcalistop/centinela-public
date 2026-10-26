@extends('voyager::master')

@section('page_title', __('voyager::generic.view') . ' ' . $dataType->getTranslatedAttribute('display_name_singular'))

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ $dataTypeContent->nombre_corto ?? $dataTypeContent->codigo }}
        <small class="text-muted">({{ $dataTypeContent->codigo }})</small>
        &nbsp;

        @can('edit', $dataTypeContent)
            @if ($dataTypeContent->origen?->value !== 'api')
                <a href="{{ route('voyager.' . $dataType->slug . '.edit', $dataTypeContent->getKey()) }}" class="btn btn-info">
                    <i class="glyphicon glyphicon-pencil"></i>
                    <span class="hidden-xs hidden-sm">{{ __('voyager::generic.edit') }}</span>
                </a>
            @endif
        @endcan
        @can('delete', $dataTypeContent)
            @if ($isSoftDeleted)
                <a href="{{ route('voyager.' . $dataType->slug . '.restore', $dataTypeContent->getKey()) }}"
                    title="{{ __('voyager::generic.restore') }}" class="btn btn-default restore"
                    data-id="{{ $dataTypeContent->getKey() }}" id="restore-{{ $dataTypeContent->getKey() }}">
                    <i class="voyager-trash"></i>
                    <span class="hidden-xs hidden-sm">{{ __('voyager::generic.restore') }}</span>
                </a>
            @else
                <a href="javascript:;" title="{{ __('voyager::generic.delete') }}" class="btn btn-danger delete"
                    data-id="{{ $dataTypeContent->getKey() }}" id="delete-{{ $dataTypeContent->getKey() }}">
                    <i class="voyager-trash"></i>
                    <span class="hidden-xs hidden-sm">{{ __('voyager::generic.delete') }}</span>
                </a>
            @endif
        @endcan
        @can('browse', $dataTypeContent)
            <a href="{{ route('voyager.' . $dataType->slug . '.index') }}" class="btn btn-warning">
                <i class="glyphicon glyphicon-list"></i>
                <span class="hidden-xs hidden-sm">{{ __('voyager::generic.return_to_list') }}</span>
            </a>
        @endcan
    </h1>
    @include('voyager::multilingual.language-selector')
@stop

@section('css')
    @if (($activeReadTab ?? '') === 'ubicacion')
        @php
            $addrForMap = $dataTypeContent->cenAddress ?? null;
            $showUbicacionMap = $addrForMap !== null && $addrForMap->latitud !== null && $addrForMap->longitud !== null;
            if ($showUbicacionMap) {
                $latMap = (float) $addrForMap->latitud;
                $lngMap = (float) $addrForMap->longitud;
                $showUbicacionMap = $latMap >= -56.0 && $latMap <= -17.0 && $lngMap >= -76.0 && $lngMap <= -66.0;
            }
        @endphp
        @if (!empty($showUbicacionMap))
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        @endif
    @endif
@stop

@section('content')
    @php
        $tabUrl = fn(string $tab): string => ($showUrl ??
            route('voyager.' . $dataType->slug . '.show', $dataTypeContent->getKey())) .
            '?tab=' .
            $tab;
        $activeReadTab = $activeReadTab ?? 'basicos';
    @endphp
    <div class="page-content read compass establecimientos-compass cen-compass-ui container-fluid">
        @include('voyager::establecimientos.partials.browse-compass-styles')
        @include('voyager::establecimientos.partials.read-detail-styles')
        @include('voyager::alerts')

        <ul class="nav nav-tabs" role="tablist" style="margin-bottom:0">
            <li role="presentation" class="{{ $activeReadTab === 'basicos' ? 'active' : '' }}">
                <a href="{{ $tabUrl('basicos') }}" role="tab">
                    <i class="voyager-shop"></i> Datos básicos
                </a>
            </li>
            <li role="presentation" class="{{ $activeReadTab === 'dependencia' ? 'active' : '' }}">
                <a href="{{ $tabUrl('dependencia') }}" role="tab">
                    <i class="voyager-company"></i> Dependencia
                </a>
            </li>
            <li role="presentation" class="{{ $activeReadTab === 'ubicacion' ? 'active' : '' }}">
                <a href="{{ $tabUrl('ubicacion') }}" role="tab">
                    <i class="voyager-location"></i> Ubicación
                </a>
            </li>
            <li role="presentation" class="{{ $activeReadTab === 'operacionales' ? 'active' : '' }}">
                <a href="{{ $tabUrl('operacionales') }}" role="tab">
                    <i class="voyager-params"></i> Datos operacionales
                </a>
            </li>
            @if (!empty($canViewBitacora))
                <li role="presentation" class="{{ $activeReadTab === 'bitacora' ? 'active' : '' }}">
                    <a href="{{ $tabUrl('bitacora') }}" role="tab">
                        <i class="voyager-book"></i> Bitácora
                    </a>
                </li>
            @endif
            @if (!empty($canViewHistorial))
                <li role="presentation" class="{{ $activeReadTab === 'historial' ? 'active' : '' }}">
                    <a href="{{ $tabUrl('historial') }}" role="tab">
                        <i class="voyager-cloud-download"></i> Historia sincronización
                    </a>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div role="tabpanel" class="tab-pane {{ $activeReadTab === 'basicos' ? 'active' : '' }}" id="tab-read-basicos">
                <div class="row">
                    <div class="col-md-12">
                        @include('voyager::establecimientos.partials.read-tab-basicos')
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane {{ $activeReadTab === 'dependencia' ? 'active' : '' }}"
                id="tab-read-dependencia">
                <div class="row">
                    <div class="col-md-12">
                        @include('voyager::establecimientos.partials.read-tab-dependencia')
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane {{ $activeReadTab === 'ubicacion' ? 'active' : '' }}"
                id="tab-read-ubicacion">
                <div class="row">
                    <div class="col-md-12">
                        @include('voyager::establecimientos.partials.read-tab-ubicacion')
                    </div>
                </div>
            </div>
            <div role="tabpanel" class="tab-pane {{ $activeReadTab === 'operacionales' ? 'active' : '' }}"
                id="tab-read-operacionales">
                <div class="row">
                    <div class="col-md-12">
                        @include('voyager::establecimientos.partials.read-tab-operacionales')
                    </div>
                </div>
            </div>
            @if (!empty($canViewBitacora))
                <div role="tabpanel" class="tab-pane {{ $activeReadTab === 'bitacora' ? 'active' : '' }}"
                    id="tab-read-bitacora">
                    <div class="row">
                        <div class="col-md-12">
                            @include('voyager::establecimientos.partials.read-tab-bitacora')
                        </div>
                    </div>
                </div>
            @endif
            @if (!empty($canViewHistorial))
                <div role="tabpanel" class="tab-pane {{ $activeReadTab === 'historial' ? 'active' : '' }}"
                    id="tab-read-historial">
                    <div class="row">
                        <div class="col-md-12">
                            @include('voyager::establecimientos.partials.read-tab-historial')
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager::generic.delete_question') }}
                        {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('voyager.' . $dataType->slug . '.index') }}" id="delete_form" method="POST">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm"
                            value="{{ __('voyager::generic.delete_confirm') }} {{ strtolower($dataType->getTranslatedAttribute('display_name_singular')) }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right"
                        data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    @if ($isModelTranslatable)
        <script>
            $(document).ready(function() {
                $('.side-body').multilingual();
            });
        </script>
    @endif
    @if (($activeReadTab ?? '') === 'ubicacion')
        @php
            $addrForMapJs = $dataTypeContent->cenAddress ?? null;
            $showUbicacionMapJs =
                $addrForMapJs !== null && $addrForMapJs->latitud !== null && $addrForMapJs->longitud !== null;
            if ($showUbicacionMapJs) {
                $latMapJs = (float) $addrForMapJs->latitud;
                $lngMapJs = (float) $addrForMapJs->longitud;
                $showUbicacionMapJs =
                    $latMapJs >= -56.0 && $latMapJs <= -17.0 && $lngMapJs >= -76.0 && $lngMapJs <= -66.0;
            }
        @endphp
        @if (!empty($showUbicacionMapJs))
            @include('voyager::establecimientos.partials.browse-establecimientos-leaflet')
            @include('voyager::establecimientos.partials.read-tab-ubicacion-map-script')
        @endif
    @endif
    <script>
        var deleteFormAction;
        $('.delete').on('click', function(e) {
            var form = $('#delete_form')[0];

            if (!deleteFormAction) {
                deleteFormAction = form.action;
            }

            form.action = deleteFormAction.match(/\/[0-9]+$/) ?
                deleteFormAction.replace(/([0-9]+$)/, $(this).data('id')) :
                deleteFormAction + '/' + $(this).data('id');

            $('#delete_modal').modal('show');
        });
    </script>
    @include('partials.voyager.bootstrap-modal-stack', ['modalIds' => ['#delete_modal']])
@stop
