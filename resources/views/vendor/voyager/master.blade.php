<!DOCTYPE html>
<html lang="{{ config('app.locale') }}" dir="{{ __('voyager::generic.is_rtl') == 'true' ? 'rtl' : 'ltr' }}">

<head>
    <title>@yield('page_title', setting('admin.title') . ' - ' . setting('admin.description'))</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="assets-path" content="{{ route('voyager.voyager_assets') }}" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,700" rel="stylesheet">

    <!-- Favicon -->
    <?php $admin_favicon = Voyager::setting('admin.favicon_image', ''); ?>
    @if ($admin_favicon == '')
        <link rel="shortcut icon" href="{{ voyager_asset('images/logo-icon.png') }}" type="image/png">
    @else
        <link rel="shortcut icon" href="{{ Voyager::image($admin_favicon) }}" type="image/png">
    @endif

    <!-- App CSS -->
    <link rel="stylesheet" href="{{ voyager_asset('css/app.css') }}">

    @yield('css')
    @if (__('voyager::generic.is_rtl') == 'true')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-rtl/3.4.0/css/bootstrap-rtl.css">
        <link rel="stylesheet" href="{{ voyager_asset('css/rtl.css') }}">
    @endif

    <!-- Few Dynamic Styles -->
    <style type="text/css">
        .voyager .side-menu .navbar-header {
            background: {{ config('voyager.primary_color', '#22A7F0') }};
            border-color: {{ config('voyager.primary_color', '#22A7F0') }};
        }

        .widget .btn-primary {
            border-color: {{ config('voyager.primary_color', '#22A7F0') }};
        }

        .widget .btn-primary:focus,
        .widget .btn-primary:hover,
        .widget .btn-primary:active,
        .widget .btn-primary.active,
        .widget .btn-primary:active:focus {
            background: {{ config('voyager.primary_color', '#22A7F0') }};
        }

        .voyager .breadcrumb a {
            color: {{ config('voyager.primary_color', '#22A7F0') }};
        }

        :root {
            --imagen-de-fondo: url('{{ $adminBackgroundImageUrl }}');
        }
    </style>

    @if (config('centinela.user_manual.enabled'))
        <style>
            .user-manual-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.35);
                z-index: 1040;
            }

            .user-manual-drawer {
                position: fixed;
                top: 0;
                right: 0;
                height: 100%;
                width: min(560px, 92vw);
                background: #fff;
                border-left: 1px solid #e5e5e5;
                z-index: 1050;
                transform: translateX(100%);
                transition: transform 150ms ease-out;
                display: flex;
                flex-direction: column;
            }

            .user-manual-drawer.is-open {
                transform: translateX(0);
            }

            .user-manual-drawer__header {
                padding: 12px 14px;
                border-bottom: 1px solid #e5e5e5;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .user-manual-drawer__title {
                font-weight: 600;
                font-size: 14px;
            }

            .user-manual-drawer__actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .user-manual-drawer__link {
                font-size: 12px;
            }

            .user-manual-drawer__close {
                background: transparent;
                border: 0;
                font-size: 22px;
                line-height: 1;
                padding: 0 6px;
            }

            .user-manual-drawer__body {
                padding: 14px;
                overflow: auto;
            }

            .user-manual-content img {
                display: block;
                max-width: 100%;
                height: auto;
                margin: 12px 0;
            }

            .user-manual-content img[src*="/docs/diagrams/"] {
                border: 1px solid #e5e5e5;
                border-radius: 4px;
                background: #fff;
            }

            .user-manual-content h1,
            .user-manual-content h2,
            .user-manual-content h3 {
                margin-top: 16px;
            }

            .um-admonition {
                border: 1px solid #e5e5e5;
                border-left-width: 4px;
                border-radius: 6px;
                padding: 10px 12px;
                margin: 10px 0;
                background: #fafafa;
            }

            .um-admonition--info {
                border-left-color: #22A7F0;
            }

            .um-admonition--warning {
                border-left-color: #f0ad4e;
            }

            .um-admonition--tip {
                border-left-color: #5cb85c;
            }

            .um-admonition--danger {
                border-left-color: #d9534f;
            }
        </style>
    @endif

    @if (!empty(config('voyager.additional_css')))<!-- Additional CSS -->
        @foreach (config('voyager.additional_css') as $css)
            <link rel="stylesheet" type="text/css" href="{{ asset($css) }}">
        @endforeach
    @endif

    @yield('head')
</head>

<body class="voyager @if (isset($dataType) && isset($dataType->slug)) {{ $dataType->slug }} @endif">

    <div id="voyager-loader">
        <?php $admin_loader_img = Voyager::setting('admin.loader', ''); ?>
        @if ($admin_loader_img == '')
            <img src="{{ voyager_asset('images/logo-icon.png') }}" alt="Voyager Loader">
        @else
            <img src="{{ Voyager::image($admin_loader_img) }}" alt="Voyager Loader">
        @endif
    </div>

    <?php
    //Comentada solución de bug Auth:user() is null, para probarla cuando suceda
    if (Auth::user()) {
        if (\Illuminate\Support\Str::startsWith(Auth::user()->avatar, 'http://') || \Illuminate\Support\Str::startsWith(Auth::user()->avatar, 'https://')) {
            $user_avatar = Auth::user()->avatar;
        } else {
            $user_avatar = Voyager::image(Auth::user()->avatar);
        }
    } else {
        header('Location: ' . route('voyager.login'));
        exit();
    }
    ?>

    <div class="app-container">
        <div class="fadetoblack visible-xs"></div>
        <div class="row content-container">
            @include('voyager::dashboard.navbar')
            @include('voyager::dashboard.sidebar')
            <script>
                (function() {
                    var appContainer = document.querySelector('.app-container'),
                        sidebar = appContainer.querySelector('.side-menu'),
                        navbar = appContainer.querySelector('nav.navbar.navbar-top'),
                        loader = document.getElementById('voyager-loader'),
                        hamburgerMenu = document.querySelector('.hamburger'),
                        sidebarTransition = sidebar.style.transition,
                        navbarTransition = navbar.style.transition,
                        containerTransition = appContainer.style.transition;

                    sidebar.style.WebkitTransition = sidebar.style.MozTransition = sidebar.style.transition =
                        appContainer.style.WebkitTransition = appContainer.style.MozTransition = appContainer.style.transition =
                        navbar.style.WebkitTransition = navbar.style.MozTransition = navbar.style.transition = 'none';

                    if (window.innerWidth > 768 && window.localStorage && window.localStorage['voyager.stickySidebar'] ==
                        'true') {
                        appContainer.className += ' expanded no-animation';
                        loader.style.left = (sidebar.clientWidth / 2) + 'px';
                        hamburgerMenu.className += ' is-active no-animation';
                    }

                    navbar.style.WebkitTransition = navbar.style.MozTransition = navbar.style.transition = navbarTransition;
                    sidebar.style.WebkitTransition = sidebar.style.MozTransition = sidebar.style.transition = sidebarTransition;
                    appContainer.style.WebkitTransition = appContainer.style.MozTransition = appContainer.style.transition =
                        containerTransition;
                })();
            </script>
            <!-- Main Content -->
            <div class="container-fluid">
                <div class="side-body padding-top">
                    @yield('page_header')
                    <div id="voyager-notifications"></div>
                    @hasSection('hide_gradient_bg')
                    @else
                        @include('voyager::partials.gradient-bg')
                    @endif
                    @yield('content')
                </div>
            </div>
        </div>
        @include('voyager::partials.app-footer')
    </div>

    @php $chatIaAccess = \App\Helpers\ModuleAccess::canAccess('chat_ia'); @endphp
    @if (config('centinela.chat_ia.enabled') && $chatIaAccess)
        @livewire('chat-ia.drawer')
    @endif

    @if (config('centinela.user_manual.enabled') && \App\Helpers\ModuleAccess::canAccess('manual_usuario'))
        <div id="user-manual-drawer" class="user-manual-drawer" aria-hidden="true">
            <div class="user-manual-drawer__header">
                <div class="user-manual-drawer__title" id="user-manual-title">Ayuda</div>
                <div class="user-manual-drawer__actions">
                    <a href="{{ route('user-manual.index') }}" class="user-manual-drawer__link"
                        id="user-manual-index-link">
                        Índice
                    </a>
                    <a href="#" target="_blank" rel="noopener" class="user-manual-drawer__link"
                        id="user-manual-full-link" style="display:none;">
                        Abrir
                    </a>
                    <button type="button" class="user-manual-drawer__close" id="user-manual-close" aria-label="Cerrar">
                        ×
                    </button>
                </div>
            </div>
            <div class="user-manual-drawer__body user-manual-content" id="user-manual-body">
                <p>Cargando…</p>
            </div>
        </div>
        <div id="user-manual-backdrop" class="user-manual-backdrop" style="display:none;"></div>
    @endif


    <!-- Javascript Libs -->


    <script type="text/javascript" src="{{ voyager_asset('js/app.js') }}"></script>
    @include('partials.prevent-number-input-wheel-scroll')

    @if (config('centinela.user_manual.enabled'))
        @php
            $userManualPageRouteName = Route::currentRouteName();
        @endphp
        <script>
            (function() {
                let mermaidPromise = null;

                function loadMermaid() {
                    if (window.mermaid) return Promise.resolve(window.mermaid);
                    if (mermaidPromise) return mermaidPromise;

                    mermaidPromise = new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js';
                        script.defer = true;
                        script.onload = () => resolve(window.mermaid);
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });

                    return mermaidPromise;
                }

                async function renderMermaid(rootEl) {
                    if (!rootEl) return;
                    const nodes = rootEl.querySelectorAll('.mermaid');
                    if (!nodes || nodes.length === 0) return;

                    try {
                        const mermaid = await loadMermaid();
                        mermaid.initialize({
                            startOnLoad: false,
                            theme: 'neutral',
                            securityLevel: 'strict',
                        });
                        await mermaid.run({
                            nodes: Array.from(nodes),
                        });
                    } catch (err) {
                        console.error('[user-manual] Error al renderizar Mermaid:', err);
                    }
                }

                function escapeHtml(str) {
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function setupUserManualDrawer() {
                    const openBtn = document.getElementById('user-manual-open');
                    const drawer = document.getElementById('user-manual-drawer');
                    const backdrop = document.getElementById('user-manual-backdrop');
                    const closeBtn = document.getElementById('user-manual-close');
                    const body = document.getElementById('user-manual-body');
                    const title = document.getElementById('user-manual-title');
                    const fullLink = document.getElementById('user-manual-full-link');

                    if (!openBtn || !drawer || !backdrop || !closeBtn || !body || !title || !fullLink) {
                        return;
                    }

                    function setOpen(isOpen) {
                        drawer.classList.toggle('is-open', isOpen);
                        drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                        backdrop.style.display = isOpen ? 'block' : 'none';
                    }

                    async function loadContext() {
                        body.innerHTML = '<p>Cargando…</p>';
                        title.textContent = 'Ayuda';
                        fullLink.style.display = 'none';

                        const url = new URL('{{ route('user-manual.context') }}', window.location.origin);
                        url.searchParams.set('path', window.location.pathname);
                        @if (is_string($userManualPageRouteName) && $userManualPageRouteName !== '')
                            url.searchParams.set('route_name', @json($userManualPageRouteName));
                        @endif

                        try {
                            const res = await fetch(url.toString(), {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            const data = await res.json();

                            title.textContent = data.title ? String(data.title) : 'Ayuda';
                            body.innerHTML = data.html ? String(data.html) : '<p>No hay manual disponible.</p>';

                            if (data.url) {
                                fullLink.href = data.url;
                                fullLink.style.display = 'inline';
                            }

                            renderMermaid(body);
                        } catch (e) {
                            body.innerHTML =
                                '<p><strong>Error:</strong> no se pudo cargar la ayuda.</p><p style="font-size:12px;color:#666;">' +
                                escapeHtml(e && e.message ? e.message : e) + '</p>';
                        }
                    }

                    openBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        setOpen(true);
                        loadContext();
                    });

                    closeBtn.addEventListener('click', function() {
                        setOpen(false);
                    });

                    backdrop.addEventListener('click', function() {
                        setOpen(false);
                    });

                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            setOpen(false);
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', function() {
                    setupUserManualDrawer();
                    renderMermaid(document.querySelector('.user-manual-content'));
                });
            })();
        </script>
    @endif

    <script>
        @if (Session::has('alerts'))
            let alerts = {!! json_encode(Session::get('alerts')) !!};
            helpers.displayAlerts(alerts, toastr);
        @endif

        @if (Session::has('message'))

            // TODO: change Controllers to use AlertsMessages trait... then remove this
            var alertType = {!! json_encode(Session::get('alert-type', 'info')) !!};
            var alertMessage = {!! json_encode(Session::get('message')) !!};
            var alerter = toastr[alertType];

            if (alerter) {
                alerter(alertMessage);
            } else {
                toastr.error("toastr alert-type " + alertType + " is unknown");
            }
        @endif
    </script>
    @include('voyager::media.manager')
    @yield('javascript')
    {{-- SDD-260: registra todos los .modal de /admin (constructor BREAD, database, etc.) --}}
    @include('partials.voyager.bootstrap-modal-stack', ['autoRegisterAll' => true])
    @stack('javascript')
    @if (!empty(config('voyager.additional_js')))<!-- Additional Javascript -->
        @foreach (config('voyager.additional_js') as $js)
            <script type="text/javascript" src="{{ asset($js) }}"></script>
        @endforeach
    @endif
    <script>
        document.addEventListener('livewire:init', function() {
            Livewire.hook('request', ({
                fail
            }) => {
                if (fail && (fail.status === 401 || fail.status === 419)) {
                    window.location.href = '{{ route('login', ['session_expired' => 1]) }}';
                }
            });

            Livewire.on('toast', ({
                type,
                message
            }) => {
                if (typeof toastr === 'undefined') {
                    return;
                }

                const t = typeof type === 'string' ? type : 'info';
                const msg = typeof message === 'string' ? message : '';

                if (!msg) {
                    return;
                }

                if (typeof toastr[t] === 'function') {
                    toastr[t](msg);
                } else {
                    toastr.info(msg);
                }
            });
        });
    </script>

    <x-upload-size-guard-script />

</body>

</html>
