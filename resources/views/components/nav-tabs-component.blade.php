@props(['tabs'])
<div>
    <ul class="nav nav-tabs">
        @foreach ($tabs as $tab)
            <li class="@if ($tab['active']) active @endif">
                <a data-toggle="tab" href="#{{ $tab['id'] }}">
                    <i class="{{ $tab['icon'] }}"></i> {{ $tab['title'] }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($tabs as $tab)
            <div role="tabpanel" class="tab-pane @if ($tab['active']) active @endif" id="{{ $tab['id'] }}">
                {!! $tab['content'] !!}
            </div>
        @endforeach
    </div>
</div>



