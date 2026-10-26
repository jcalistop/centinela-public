<div class="btn-group cen-dropdown-from-model">
    @once
        <style>
            .cen-dropdown-from-model>.btn.btn-action.dropdown-toggle {
                background-color: #006FB3;
                border-color: #006FB3;
                color: #fff;
                border-radius: 0.3em;
            }

            .cen-dropdown-from-model>.btn.btn-action.dropdown-toggle:hover,
            .cen-dropdown-from-model>.btn.btn-action.dropdown-toggle:focus,
            .cen-dropdown-from-model.open>.btn.btn-action.dropdown-toggle {
                background-color: #005a94;
                border-color: #005a94;
                color: #fff;
            }
        </style>
    @endonce
    <button type="button" class="btn btn-action dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
        aria-expanded="false">
        <strong>Año:</strong> {{ \Helper::showCurrentYear()->ano }}<span class="caret"></span>
    </button>
    <ul class="dropdown-menu">
        @foreach ($data as $item)
            <li><a href="{{ route($route, $item->id) }}">{{ $item->$field }}</a></li>
        @endforeach
    </ul>
</div>
