@extends('voyager::master')

@section('css')

    @include('voyager::compass.includes.styles')
    <style>
        h1.page-title {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: wrap !important;
        }

        .comges-toolbar {
            float: none !important;
            display: inline-flex !important;
            flex-wrap: wrap;
            align-items: center;
        }

        .comges-toolbar>* {
            margin-left: 0.5rem;
            margin-bottom: 0.2rem;
        }

        .comges-toolbar>*:first-child {
            margin-left: 0;
        }
    </style>

    @if (!$dataType->server_side && config('dashboard.data_tables.responsive'))
        <link rel="stylesheet" href="{{ voyager_asset('lib/css/responsive.dataTables.min.css') }}">
    @endif
@stop

@section('page_title', __('voyager::generic.viewing') . ' ' . $dataType->getTranslatedAttribute('display_name_plural'))

@section('page_header')
    @php
        $indicatorTypeSetting = app('App\Models\IndicatorTypeSetting')::getData(1);
        $user = Auth::user();
        $roleName = $user && $user->role ? $user->role->name : null;

        $canSeeMassiveActions = in_array($roleName, ['control-gestion', 'admin'], true);
        $canSeeConfig = Illuminate\Support\Facades\Gate::check('edit', app('App\Models\IndicatorTypeSetting'));
        $canSeeActionsButton = in_array($roleName, ['control-gestion', 'admin'], true);

        $hasDropdownActions = $canSeeMassiveActions || $canSeeConfig;

        $useScoreScale = $useScoreScale ?? \App\Support\Comges\ComgesYearMode::currentUsesScoreScale();
        $canSeeMassResults = $useScoreScale;
    @endphp

    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ $dataType->getTranslatedAttribute('display_name_plural') }}

        <span class="comges-toolbar">
            @can('add', app($dataType->model_name))
                <a href="{{ route('voyager.' . $dataType->slug . '.create') }}" class="btn btn-success">
                    <i class="voyager-plus"></i> Agregar compromiso
                </a>
            @endcan

            @can('edit', app(\App\Models\ComIndicator::class))
                @if (Route::has('voyager.compromisos.structure-order'))
                    <a href="{{ route('voyager.compromisos.structure-order') }}" class="btn btn-success">
                        <i class="voyager-list"></i> Ordenar COMGES
                    </a>
                @endif
            @endcan

            @if ($canSeeActionsButton)
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#ComgesActionsModal">
                        <i class="voyager-list"></i> Acciones
                    </button>
                </div>
            @endif
        </span>
    </h1>
    @if ($indicatorTypeSetting->texto_enriquecido)
        <div style="position: relative; z-index: 1; margin: 0 50px 0 50px;">{!! $indicatorTypeSetting->texto_enriquecido !!}</div>
    @endif
@stop

@section('content')
    @php
        $useScoreScale = $useScoreScale ?? \App\Support\Comges\ComgesYearMode::currentUsesScoreScale();
        $canAccessTabs =
            $indicatorTypeSetting->habilitado ||
            in_array(Auth::user()->role->name, ['admin', 'parametrizador', 'control-gestion'], true);
    @endphp
    <div class="page-content compass container-fluid">
        @include('voyager::alerts')
        @if ($canAccessTabs)
            <ul class="nav nav-tabs">
                <li class="active"><a data-toggle="tab" href="#compromisos"><i class="voyager-dashboard"></i>Compromisos</a>
                </li>
                @if (Auth::user()->role->name == 'referente')
                    <li><a data-toggle="tab" href="#indicadores"><i class="voyager-thumb-tack"></i>Mis indicadores</a></li>
                @endif
                <li><a data-toggle="tab" href="#resultados"><i class="voyager-trophy"></i>Resultados globales</a></li>
                <li><a data-toggle="tab" href="#documentos"><i class="voyager-book"></i>Documentos</a></li>
            </ul>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="compromisos">
                    @livewire('search-commitment-component')
                </div>
                @if (Auth::user()->role->name == 'referente')
                    <div role="tabpanel" class="tab-pane" id="indicadores" showButtons="true">
                        @include('centinela.comges.compromisos.partials.referente-indicators-table', [
                            'useScoreScale' => $useScoreScale,
                        ])
                    </div>
                @endif
                <div role="tabpanel" class="tab-pane" id="resultados">
                    @include('centinela.comges.compromisos.partials.global-results')
                    <h3 class="text-center">
                        @if (!is_null($indicatorTypeSetting->panel_minsal))
                            <a href="{{ $indicatorTypeSetting->panel_minsal }}" target="blank">
                                Acceso a Panel MINSAL
                            </a>
                        @endif
                    </h3>
                </div>
                <div role="tabpanel" class="tab-pane" id="documentos">
                    @include('partials.documents.tab-add-document-button', [
                        'hub' => 'comges',
                        'indicatorTypeId' => $indicatorTypeSetting->indicator_type_id,
                        'metasRoute' => null,
                    ])
                    <x-browse-view-component table="documents" :scopeWhere="['indicator_type_id' => $indicatorTypeSetting->indicator_type_id]" :documentHubContext="['hub' => 'comges', 'indicator_type_id' => $indicatorTypeSetting->indicator_type_id]" />
                </div>
            </div>
        @else
            <h3 class="text-center"><i class="voyager-lightbulb"></i> Este indicador aun no ha sido habilitado para el año
                {{ Helper::showCurrentYear()->ano }}</h3>
        @endif
    </div>
    <x-delete-model-component :dataType="$dataType" />
    <x-compromisos.massive-download-component />
    <x-compromisos.massive-date-cutoff-component />
    <x-compromisos.massive-editable-tabs-component :cutoffs="$cutoffs" />
    @if ($useScoreScale)
        <x-compromisos.massive-clear-evaluations-component />
    @endif

    {{-- Fuera de Livewire y al final del contenido: evita quedar bajo .modal-backdrop (custom.css z-index en .side-body) --}}
    @if ($canSeeActionsButton)
        <div class="modal fade" id="ComgesActionsModal" tabindex="-1" role="dialog"
            aria-labelledby="ComgesActionsModalTitle">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span
                                aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="ComgesActionsModalTitle">Acciones</h4>
                    </div>
                    <div class="modal-body">
                        @if ($canSeeConfig)
                            @php
                                $configurarTipoIndicadorUrl = \Illuminate\Support\Facades\Route::has(
                                    'voyager.configurar-tipo-indicador.edit',
                                )
                                    ? route('voyager.configurar-tipo-indicador.edit', $indicatorTypeSetting->id)
                                    : null;
                            @endphp
                            @if ($configurarTipoIndicadorUrl)
                                <div class="text-muted" style="margin-bottom: 6px;">Configuración</div>
                                <p style="margin: 0 0 10px;">
                                    <a class="btn btn-default btn-block" href="{{ $configurarTipoIndicadorUrl }}">
                                        <i class="voyager-settings"></i> Configurar COMGES
                                    </a>
                                </p>
                            @endif
                        @endif

                        @if ($canSeeMassiveActions)
                            <div class="text-muted" style="margin-bottom: 6px;">Acciones masivas</div>
                            <p style="margin: 0 0 8px;">
                                <button type="button" class="btn btn-default btn-block" data-dismiss="modal"
                                    data-toggle="modal" data-target="#MassiveDownload">
                                    <i class="glyphicon glyphicon-download-alt"></i> Descarga masiva
                                </button>
                            </p>
                            <p style="margin: 0 0 8px;">
                                @if ($canSeeMassResults)
                                    <a href="{{ route('comges.carga-masiva-resultados.index') }}"
                                        class="btn btn-default btn-block">
                                        <i class="voyager-upload"></i> Carga masiva de resultados
                                    </a>
                                @endif
                            </p>
                            <p style="margin: 0 0 8px;">
                                <button type="button" class="btn btn-default btn-block" data-dismiss="modal"
                                    data-toggle="modal" data-target="#MassiveDateCutoff">
                                    <i class="glyphicon glyphicon-calendar"></i> Asignación masiva de fechas
                                </button>
                            </p>
                            <p style="margin: 0 0 8px;">
                                <button type="button" class="btn btn-default btn-block" data-dismiss="modal"
                                    data-toggle="modal" data-target="#MassiveEditableTabs">
                                    <i class="glyphicon glyphicon-check"></i> Pestañas editables (referente)
                                </button>
                            </p>
                            @if ($useScoreScale)
                                <p style="margin: 0 0 8px;">
                                    <button type="button" class="btn btn-danger btn-block" data-dismiss="modal"
                                        data-toggle="modal" data-target="#MassiveClearEvaluations">
                                        <i class="glyphicon glyphicon-trash"></i> Limpiar autoevaluaciones
                                    </button>
                                </p>
                            @endif
                        @endif

                        @if ($canSeeActionsButton && !$hasDropdownActions)
                            <p class="text-muted" style="margin: 0;">No hay acciones disponibles</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

@stop

@section('javascript')
    {{-- SCRIPTS DE LA VISTA BROWSE --}}
    <!-- DataTables -->
    @if (!$dataType->server_side && config('dashboard.data_tables.responsive'))
        <script src="{{ voyager_asset('lib/js/dataTables.responsive.min.js') }}"></script>
    @endif
    <script>
        $(document).ready(function() {
            @if (!$dataType->server_side)
                var table = $('#dataTable').DataTable({!! json_encode(
                    array_merge(
                        [
                            'order' => $orderColumn,
                            'language' => __('voyager::datatable'),
                            'columnDefs' => [['targets' => 'dt-not-orderable', 'searchable' => false, 'orderable' => false]],
                        ],
                        config('voyager.dashboard.data_tables', []),
                    ),
                    true,
                ) !!});
            @else
                $('#search-input select').select2({
                    minimumResultsForSearch: Infinity
                });
            @endif

            @if ($isModelTranslatable)
                $('.side-body').multilingual();
                //Reinitialise the multilingual features when they change tab
                $('#dataTable').on('draw.dt', function() {
                    $('.side-body').data('multilingual').init();
                })
            @endif
            $('.select_all').on('click', function(e) {
                $('input[name="row_id"]').prop('checked', $(this).prop('checked')).trigger('change');
            });

        });


        var deleteFormAction;
        $('div').on('click', '.delete', function(e) {
            $('#delete_form')[0].action = '{{ route('voyager.' . $dataType->slug . '.destroy', '__id') }}'.replace(
                '__id', $(this).data('id'));
            $('#delete_modal').modal('show');
        });

        @if ($usesSoftDeletes)
            @php
                $params = [
                    's' => $search->value,
                    'filter' => $search->filter,
                    'key' => $search->key,
                    'order_by' => $orderBy,
                    'sort_order' => $sortOrder,
                ];
            @endphp
            $(function() {
                $('#show_soft_deletes').change(function() {
                    if ($(this).prop('checked')) {
                        $('#dataTable').before(
                            '<a id="redir" href="{{ route('voyager.' . $dataType->slug . '.index', array_merge($params, ['showSoftDeleted' => 1]), true) }}"></a>'
                        );
                    } else {
                        $('#dataTable').before(
                            '<a id="redir" href="{{ route('voyager.' . $dataType->slug . '.index', array_merge($params, ['showSoftDeleted' => 0]), true) }}"></a>'
                        );
                    }

                    $('#redir')[0].click();
                })
            })
        @endif
        $('input[name="row_id"]').on('change', function() {
            var ids = [];
            $('input[name="row_id"]').each(function() {
                if ($(this).is(':checked')) {
                    ids.push($(this).val());
                }
            });
            $('.selected_ids').val(ids);
        });
    </script>
    @include('partials.voyager.bootstrap-modal-stack', [
        'modalIds' => [
            '#ComgesActionsModal',
            '#MassiveDownload',
            '#MassiveDateCutoff',
            '#MassiveEditableTabs',
            '#MassiveClearEvaluations',
            '#delete_modal',
        ],
    ])
@stop
