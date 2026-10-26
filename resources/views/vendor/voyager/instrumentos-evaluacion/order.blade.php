@extends('voyager::master')

@section('page_title', $dataType->getTranslatedAttribute('display_name_plural') . ' ' . __('voyager::bread.order'))

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-list"></i>{{ $dataType->getTranslatedAttribute('display_name_plural') }}
        {{ __('voyager::bread.order') }}
    </h1>
    <a href="{{ route('voyager.' . $dataType->slug . '.index') }}" class="btn btn-primary btn-add-new">
        <i class="voyager-angle-left"></i> <span>{{ __('voyager::generic.return_to_list') }}</span>
    </a>
@stop

@section('content')
    <div class="page-content container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <p class="panel-title" style="color:#777">{{ __('voyager::generic.drag_drop_info') }}</p>
                    </div>

                    <div class="panel-body" style="padding:30px;">
                        <div class="dd">
                            <ol class="dd-list">
                                @foreach ($results as $result)
                                    <li class="dd-item" data-id="{{ $result->getKey() }}">
                                        <div class="dd-handle" style="height:inherit">
                                            @if ($dataRow && isset($dataRow->details->view_order))
                                                @include($dataRow->details->view_order, [
                                                    'row' => $dataRow,
                                                    'dataType' => $dataType,
                                                    'dataTypeContent' => $result,
                                                    'view' => 'order',
                                                    'content' => $result->{$display_column},
                                                ])
                                            @elseif ($dataRow && isset($dataRow->details->view))
                                                @include($dataRow->details->view, [
                                                    'row' => $dataRow,
                                                    'dataType' => $dataType,
                                                    'dataTypeContent' => $result,
                                                    'content' => $result->{$display_column},
                                                    'action' => 'order',
                                                ])
                                            @elseif ($dataRow && $dataRow->type == 'image')
                                                <span>
                                                    @php
                                                        $orderImg = $result->{$display_column};
                                                        $orderImgSrc = filter_var($orderImg, FILTER_VALIDATE_URL)
                                                            ? $orderImg
                                                            : Voyager::image($orderImg);
                                                    @endphp
                                                    <img src="{{ $orderImgSrc }}" style="height:100px" alt="">
                                                </span>
                                            @else
                                                <div class="clearfix">
                                                    <strong>{{ $result->nombre_indicador ?? $result->{$display_column} }}</strong>
                                                    <div>
                                                        <small class="text-muted">
                                                            <code>{{ $result->route }}</code>
                                                            @if (isset($result->orden))
                                                                · orden: {{ $result->orden }}
                                                            @endif
                                                            · id {{ $result->getKey() }}
                                                        </small>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function() {
            $('.dd').nestable({
                maxDepth: 1
            });

            $('.dd').on('change', function() {
                $.post("{{ route('voyager.' . $dataType->slug . '.update_order') }}", {
                    order: JSON.stringify($('.dd').nestable('serialize')),
                    _token: "{{ csrf_token() }}"
                }, function() {
                    toastr.success("{{ __('voyager::bread.updated_order') }}");
                });
            });
        });
    </script>
@stop
