@extends('voyager::bread.browse')

@section('page_header')
    <style>
        .centinela-users-browse-header {
            position: relative;
        }

        .centinela-users-browse-header #centinela-users-browse-mensajeria {
            position: absolute;
            right: 15px;
            top: 30px;
        }
    </style>
    <div class="container-fluid centinela-users-browse-header">
        <h1 class="page-title">
            <i class="{{ $dataType->icon }}"></i> {{ $dataType->getTranslatedAttribute('display_name_plural') }}
        </h1>
        @can('add', app($dataType->model_name))
            <a href="{{ route('voyager.' . $dataType->slug . '.create') }}" class="btn btn-success btn-add-new">
                <i class="voyager-plus"></i> <span>{{ __('voyager::generic.add_new') }}</span>
            </a>
        @endcan
        @can('delete', app($dataType->model_name))
            @include('voyager::partials.bulk-delete')
        @endcan
        @can('edit', app($dataType->model_name))
            @if (!empty($dataType->order_column) && !empty($dataType->order_display_column))
                <a href="{{ route('voyager.' . $dataType->slug . '.order') }}" class="btn btn-primary btn-add-new">
                    <i class="voyager-list"></i> <span>{{ __('voyager::bread.order') }}</span>
                </a>
            @endif
        @endcan
        @can('delete', app($dataType->model_name))
            @if ($usesSoftDeletes)
                <input type="checkbox" @if ($showSoftDeleted) checked @endif id="show_soft_deletes"
                    data-toggle="toggle" data-on="{{ __('voyager::bread.soft_deletes_off') }}"
                    data-off="{{ __('voyager::bread.soft_deletes_on') }}">
            @endif
        @endcan
        @foreach ($actions as $action)
            @if (method_exists($action, 'massAction'))
                @include('voyager::bread.partials.actions', ['action' => $action, 'data' => null])
            @endif
        @endforeach
        @include('voyager::multilingual.language-selector')
        @php
            $actor = Auth::user();
        @endphp
        @if ($actor instanceof \App\Models\User && \App\Support\Messaging\MessagingComposeScope::canCompose($actor))
            <a id="centinela-users-browse-mensajeria" href="{{ route('centinela.admin.mensajeria') }}"
                class="btn btn-primary">
                <i class="voyager-mail"></i> <span>Mensajería</span>
            </a>
        @endif
    </div>
@endsection
