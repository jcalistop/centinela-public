@php
    $loginUrl = route('voyager.login');
    $loginBgUrl = asset('images/comges_wide.jpg');
    $loginBgColor = Voyager::setting('admin.bg_color', '#FFFFFF');
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Centinela')</title>
    <link rel="stylesheet" href="{{ voyager_asset('css/app.css') }}">
    <style>
        html {
            min-height: 100%;
            background-color: {{ $loginBgColor }};
        }

        body.cen-publico {
            margin: 0;
            min-height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
            background: transparent;
            font-family: "Open Sans", Helvetica, Arial, sans-serif;
            color: #333;
        }

        body.cen-publico::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-color: {{ $loginBgColor }};
            background-image: url('{{ $loginBgUrl }}');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
        }

        body.cen-publico::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: rgba(255, 255, 255, 0.78);
        }

        body.cen-publico>* {
            position: relative;
            z-index: 1;
        }

        .cen-publico__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1.5rem;
            /* Shell conjunto público/Filament — azul medio institucional */
            background: linear-gradient(90deg, #4b7396 0%, #3e6485 50%, #355a78 100%);
            color: #fff;
        }

        .cen-publico__brand {
            display: flex;
            align-items: center;
            flex: 1 1 auto;
            min-width: 0;
        }

        .cen-publico__brand img {
            height: 36px;
            width: auto;
            object-fit: contain;
            flex-shrink: 0;
            display: block;
            background: #fff;
            border-radius: 8px;
            padding: 4px 8px;
            box-sizing: content-box;
        }

        .cen-publico__actions {
            flex-shrink: 0;
            white-space: nowrap;
        }

        .cen-publico__actions a {
            color: #fff;
            margin-left: 0.75rem;
            text-decoration: none;
            font-size: 14px;
        }

        .cen-publico__actions .btn-login {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 4px;
        }

        .cen-publico__main {
            flex: 1 0 auto;
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.5rem;
            box-sizing: border-box;
        }

        .cen-publico__footer {
            flex-shrink: 0;
            width: 100%;
            padding: 0.85rem 1.5rem;
            box-sizing: border-box;
            background: #3e6485;
            color: rgba(255, 255, 255, 0.85);
            font-size: 13px;
            line-height: 1.45;
            text-align: center;
        }

        .cen-publico__footer p {
            margin: 0;
        }

        .cen-publico__footer a {
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }

        .cen-publico__footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 767px) {
            .cen-publico__actions {
                white-space: normal;
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 0.35rem 0.75rem;
            }

            .cen-publico__actions a {
                margin-left: 0;
            }
        }

        .cen-publico__main--wide {
            max-width: min(1600px, 100%);
            width: 100%;
            padding-left: clamp(1rem, 2.5vw, 2rem);
            padding-right: clamp(1rem, 2.5vw, 2rem);
        }

        .cen-publico__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.25rem;
            justify-content: center;
            justify-items: center;
            max-width: 960px;
            margin: 0 auto;
        }

        .cen-publico__card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
            max-width: 240px;
            min-height: 220px;
            padding: 1.35rem 1rem;
            background: #fff;
            border: 1px solid #dde3ea;
            border-radius: 8px;
            text-decoration: none;
            color: #1a3a5c;
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .cen-publico__card:hover {
            border-color: #1a3a5c;
            box-shadow: 0 2px 8px rgba(26, 58, 92, 0.12);
        }

        .cen-publico__card img {
            width: 128px;
            height: 128px;
            object-fit: contain;
            margin-bottom: 1rem;
        }

        .cen-publico__card span {
            font-size: 14px;
            line-height: 1.35;
            font-weight: 600;
        }

        .cen-publico__card span.cen-publico__card-version {
            display: block;
            margin-top: 0.25rem;
            font-size: 12px;
            font-weight: 500;
            color: #6c757d;
        }

        .cen-publico__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .cen-publico__section.cen-publico__meta {
            margin-bottom: 1.25rem;
        }

        .cen-publico__section.cen-publico__meta h1 {
            margin-top: 0;
        }

        .cen-publico__meta img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }

        .cen-publico__section {
            background: #fff;
            border: 1px solid #dde3ea;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
        }

        .cen-publico__section h2 {
            margin-top: 0;
            font-size: 1.15rem;
            color: #1a3a5c;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .cen-publico__metas-card h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 0 0 0.65rem;
            font-size: 1.05rem;
            color: #1a3a5c;
            line-height: 1.35;
        }

        .cen-publico .semaforo {
            min-width: 4.25rem;
            width: auto;
            padding: 0 0.4rem;
            height: 2rem;
            border: 1px solid grey;
            border-radius: 0.9rem;
            margin: 0;
            line-height: 1.9rem;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .cen-publico .semaforo.red {
            background-color: red;
            color: #fff;
        }

        .cen-publico .semaforo.green {
            background-color: forestgreen;
            color: #fff;
        }

        .cen-publico .semaforo.orange {
            background-color: orange;
            color: #fff;
        }

        .cen-publico .semaforo.error {
            background-color: black;
            color: #fff;
        }

        .cen-publico .semaforo.nulo {
            background-color: transparent;
            color: #6c757d;
        }

        .cen-publico .semaforo-legend-wrap {
            display: block;
            width: 100%;
            margin: 0 0 0.75rem;
        }

        .cen-publico .semaforo-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem 0.85rem;
            padding: 0;
            margin: 0;
            font-size: 11px;
            line-height: 1.3;
            justify-content: flex-start;
        }

        .cen-publico .semaforo-legend__item {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
        }

        .cen-publico .semaforo.semaforo-legend__dot {
            box-sizing: border-box;
            min-width: 0.85rem;
            width: 0.85rem;
            height: 0.85rem;
            padding: 0;
            line-height: 0.85rem;
            border-radius: 50%;
            font-size: 0;
            overflow: hidden;
            flex-shrink: 0;
        }

        .cen-publico__legend .semaforo-legend {
            font-size: 13px;
            gap: 0.75rem 1.25rem;
        }

        .cen-publico__table {
            width: 100%;
            border-collapse: collapse;
        }

        .cen-publico__table th,
        .cen-publico__table td {
            padding: 0.5rem 0.4rem;
            border-bottom: 1px solid #e8edf2;
            text-align: left;
            font-size: 14px;
        }

        .cen-publico__widgets {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
        }

        .cen-publico__widget {
            border: 1px solid #dde3ea;
            border-radius: 6px;
            padding: 0.85rem;
            background: #fafbfc;
        }

        .cen-publico__widget strong {
            display: block;
            margin-bottom: 0.35rem;
            color: #1a3a5c;
        }

        .cen-publico__metas {
            margin-bottom: 1.25rem;
        }

        .cen-publico__metas-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            align-items: start;
        }

        .cen-publico__metas-card {
            margin-bottom: 0;
            min-width: 0;
        }

        .cen-publico__metas-card .cen-publico__table th,
        .cen-publico__metas-card .cen-publico__table td {
            font-size: 13px;
            word-break: break-word;
        }

        @media (max-width: 992px) {
            .cen-publico__metas-grid {
                grid-template-columns: 1fr;
            }
        }

        .cen-publico__empty {
            color: #6c757d;
            font-size: 14px;
        }

        .cen-publico__back {
            display: inline-block;
            margin-bottom: 1rem;
            color: #1a3a5c;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
    @stack('styles')
</head>

<body class="cen-publico">
    <header class="cen-publico__header">
        <div class="cen-publico__brand">
            <a href="{{ route('centinela.publico.landing') }}" aria-label="Centinela — inicio">
                <img src="{{ asset('images/logo_centinela.svg') }}" alt="Centinela">
            </a>
        </div>
        <div class="cen-publico__actions">
            <a href="{{ route('centinela.publico.plataforma') }}">¿Qué es Centinela?</a>
            <a href="{{ route('centinela.access-request.create') }}">Solicitar acceso</a>
            <a class="btn-login" href="{{ $loginUrl }}">Iniciar sesión</a>
        </div>
    </header>
    <main class="cen-publico__main @yield('main_class')">
        @yield('content')
    </main>
    <footer class="cen-publico__footer" role="contentinfo">
        <p>
            Desarrollado por Departamento de Control de Gestión,
            <a href="https://ssreloncavi.redsalud.gob.cl/" target="_blank" rel="noopener noreferrer">Servicio de Salud
                del Reloncaví</a>.
        </p>
    </footer>
</body>

</html>
