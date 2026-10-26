<nav class="navbar navbar-default navbar-fixed-top navbar-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button class="hamburger btn-link">
                <span class="hamburger-inner"></span>
            </button>
            @section('breadcrumbs')
                <ol class="breadcrumb hidden-xs">
                    @php
                        $segments = array_filter(
                            explode('/', str_replace(route('voyager.dashboard'), '', Request::url())),
                        );
                        $url = route('voyager.dashboard');
                    @endphp
                    @if (count($segments) == 0)
                        <li class="active"><i class="voyager-home"></i> {{ __('voyager::generic.dashboard') }}</li>
                    @else
                        <li class="active">
                            <a href="{{ route('voyager.dashboard') }}"><i class="voyager-home"></i>
                                {{ __('voyager::generic.dashboard') }}</a>
                        </li>
                        @foreach ($segments as $segment)
                            @php
                                $url .= '/' . $segment;
                            @endphp
                            @if ($loop->last)
                                <li>{{ ucfirst(urldecode($segment)) }}</li>
                            @else
                                <li>
                                    <a href="{{ $url }}">{{ ucfirst(urldecode($segment)) }}</a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                </ol>
            @show
        </div>
        <ul class="nav navbar-nav @if (__('voyager::generic.is_rtl') == 'true') navbar-left @else navbar-right @endif">
            <li class="dropdown profile">
                <a href="#" class="dropdown-toggle text-right" data-toggle="dropdown" role="button"
                    aria-expanded="false"><img src="{{ $user_avatar }}" class="profile-img"> <span
                        class="caret"></span></a>
                <ul class="dropdown-menu dropdown-menu-animated">
                    <li class="profile-img">
                        <img src="{{ $user_avatar }}" class="profile-img">
                        <div class="profile-body">
                            <h5>{{ Auth::user()->name }}</h5>
                            <h6>{{ Auth::user()->email }}</h6>
                        </div>
                    </li>
                    <li class="divider"></li>
                    <?php $nav_items = config('voyager.dashboard.navbar_items'); ?>
                    @if (is_array($nav_items) && !empty($nav_items))
                        @foreach ($nav_items as $name => $item)
                            <li {!! isset($item['classes']) && !empty($item['classes']) ? 'class="' . $item['classes'] . '"' : '' !!}>
                                @if (isset($item['route']) && $item['route'] == 'voyager.logout')
                                    <form action="{{ route('voyager.logout') }}" method="POST">
                                        {{ csrf_field() }}
                                        <button type="submit" class="btn btn-danger btn-block">
                                            @if (isset($item['icon_class']) && !empty($item['icon_class']))
                                                <i class="{!! $item['icon_class'] !!}"></i>
                                            @endif
                                            {{ __($name) }}
                                        </button>
                                    </form>
                                @else
                                    @php
                                        $navbarHref = '#';
                                        if (!empty($item['route'])) {
                                            if (Route::has($item['route'])) {
                                                $navbarHref = route($item['route']);
                                            } elseif (
                                                str_starts_with($item['route'], 'http://') ||
                                                str_starts_with($item['route'], 'https://')
                                            ) {
                                                $navbarHref = $item['route'];
                                            } else {
                                                $navbarHref = url($item['route']);
                                            }
                                        }
                                    @endphp
                                    <a href="{{ $navbarHref }}" {!! isset($item['target_blank']) && $item['target_blank'] ? 'target="_blank"' : '' !!}>
                                        @if (isset($item['icon_class']) && !empty($item['icon_class']))
                                            <i class="{!! $item['icon_class'] !!}"></i>
                                        @endif
                                        {{ __($name) }}
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                </ul>
            </li>
        </ul>
        <div
            style="position: absolute;
        top: 0.4rem;
        right: 0;
        margin-right: 6rem;
        line-height: 2rem;
        ">
            @php
                $navbarUser = Auth::user();
                $canOpenConfigurationPanel =
                    $navbarUser instanceof \App\Models\User &&
                    $navbarUser->hasRole('admin') &&
                    \App\Support\Configuration\FilamentChasisGate::enabled();
            @endphp
            @if ($canOpenConfigurationPanel)
                <a href="{{ \App\Support\Filament\FilamentPanelRegistry::panelUrl(\App\Support\Filament\FilamentPanelRegistry::CONFIGURACION) }}"
                    id="cen-filament-configuration-link" class="btn btn-action" role="button"
                    style="border-radius: 0.3em; margin: 0.45rem 0.5rem 0 0.45rem;" title="Panel Configuración (Filament)">
                    <i class="voyager-settings"></i> Configuración
                </a>
            @endif
            @if (config('centinela.user_manual.enabled') && \App\Helpers\ModuleAccess::canAccess('manual_usuario'))
                <a href="#" id="user-manual-open" class="navbar-text" role="button" aria-expanded="false"
                    title="Ayuda">
                    <i class="voyager-question"></i>
                    <span class="hidden-xs">Ayuda</span>
                </a>
            @endif
            <span class="navbar-text" style="margin: 4px;">
                <x-dropdown-from-model-component model="App\Models\Year" field="ano" route="setIndicatorYear" />
            </span>
            <h4 class="navbar-text" style="margin: 6px;">

                <span class="label label-action" style="display:inline-block; line-height:28px;">
                    {{ Auth::user()->cenHieDependency->nombre }}
                </span>
            </h4>
        </div>
    </div>
</nav>
