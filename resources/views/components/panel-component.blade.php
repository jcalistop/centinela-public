@props(['id' => null, 'col', 'panel', 'title' => '', 'body', 'footer' => null, 'collapsable' => false])
@php
    $shouldCollapse = filter_var($collapsable, FILTER_VALIDATE_BOOLEAN);
    $collapseKey = $id ?? 'panel';
    $collapseId = $id !== null ? 'collapse-' . $collapseKey : 'collapse-panel-' . rand(1, 100000);
@endphp
<div class="row">
    <div class="col-md-{{ $col }}">
        <div class="panel panel-{{ $panel }}">
            <div class="panel-heading">
                <h3 class="panel-title"
                    @if ($shouldCollapse) data-toggle="collapse" data-target="#{{ $collapseId }}" style="cursor: pointer;" @endif>
                    {{ $title }}
                </h3>
            </div>
            @isset($static)
                <div class="panel-body"
                    @if ($shouldCollapse) role="button"
                        tabindex="0"
                        data-toggle="collapse"
                        data-target="#{{ $collapseId }}"
                        style="cursor: pointer;" @endif>
                    {{ $static }}
                </div>
            @endisset
            @if ($shouldCollapse)
                <div id="{{ $collapseId }}" class="panel-collapse collapse">
            @endif
            <div class="panel-body">{{ $body }}</div>
            @if (!is_null($footer))
                <div class="panel-footer">{{ $footer }}</div>
            @endif
            @if ($shouldCollapse)
        </div>
        @endif
    </div>
</div>
</div>
