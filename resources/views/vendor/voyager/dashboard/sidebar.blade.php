<div class="side-menu sidebar-inverse">
    <nav class="navbar navbar-default" role="navigation">
        <div class="side-menu-container">
            <div class="navbar-header">
                <a class="navbar-brand" href="{{ route('voyager.dashboard') }}">
                    <div class="logo-icon-container">
                        <?php $admin_logo_img = Voyager::setting('admin.icon_image', ''); ?>
                        @if ($admin_logo_img == '')
                            <img src="{{ voyager_asset('images/logo-icon-light.png') }}" alt="Logo Icon">
                        @else
                            <img src="{{ Voyager::image($admin_logo_img) }}" alt="Logo Icon">
                        @endif
                    </div>
                    @php
                        if (Session::get('indicator_year') == null) {
                            Session::flush();
                        }
                    @endphp
                    <div class="title">{{ Voyager::setting('admin.title', 'VOYAGER') }}
                        {{ session()->get('indicator_year')->ano }}</div>
                </a>
            </div><!-- .navbar-header -->

            <div class="panel widget center bgimage"
                style="background-image:url({{ $adminBackgroundImageUrl }}); background-size: cover; background-position: 0px;">
                <div class="dimmer"></div>
                <div class="panel-content">
                    <img src="{{ $user_avatar }}" class="avatar" alt="{{ Auth::user()->name }} avatar">
                    <h4>{{ ucwords(Auth::user()->name) }}</h4>
                    <p>{{ Auth::user()->email }}</p>

                    <a href="{{ route('voyager.profile') }}"
                        class="btn btn-primary">{{ __('voyager::generic.profile') }}</a>
                    <div style="clear:both"></div>
                </div>
            </div>

        </div>
        <div id="adminmenu">
            {{ menu('inicio', 'voyager::menu.custom-menu') }}
            <x-menu-component table="indicator_types" itemField="clave_indicador" />
            <admin-menu :items='@json(\App\Support\Voyager\MenuVisualizationTree::adminMenuItems())'></admin-menu>
            @php
                $adminMenuRaw = menu('admin', '_json');
                if ($adminMenuRaw instanceof \Illuminate\Support\Collection) {
                    $adminMenuFromVoyager = json_decode($adminMenuRaw->toJson(), true) ?: [];
                } elseif (is_array($adminMenuRaw)) {
                    $adminMenuFromVoyager = $adminMenuRaw;
                } else {
                    $adminMenuFromVoyager = [];
                }
                $adminMenuFromVoyager = \App\Support\Voyager\AdminMenuFilters::hideSupportWhenDisabled(
                    $adminMenuFromVoyager,
                );
            @endphp
            <admin-menu :items='@json($adminMenuFromVoyager)'></admin-menu>

        </div>
    </nav>
    <div class="fixed-bottom text-center">
        <a href="{{ route('acerca-de.releases') }}" title="Versión desplegada en este servidor">
            @php
                $deployedVersionLabel = trim((string) (config('app.version') ?? session('release_tag', 'N/D')));
                if ($deployedVersionLabel === '') {
                    $deployedVersionLabel = 'N/D';
                }
            @endphp
            <h5><span class="label label-default">{{ $deployedVersionLabel }}</span></h5>
        </a>
    </div>
</div>
