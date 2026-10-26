@extends('centinela.publico.layout')

@section('title', '¿Qué es Centinela?')

@section('content')
    <a class="cen-publico__back" href="{{ route('centinela.publico.landing') }}">← Volver al dashboard público</a>

    <div class="cen-publico__section" style="margin-bottom:1.25rem;">
        <h1 style="margin:0 0 0.75rem;font-size:1.4rem;color:#1a3a5c;">¿Qué es Centinela?</h1>
        <p class="cen-publico__empty" style="margin:0 0 0.75rem;color:#333;font-size:15px;line-height:1.5;">
            Centinela es una <strong>plataforma web de gestión</strong> para el seguimiento de
            <strong>instrumentos de evaluación</strong> en los Servicios de Salud (indicadores, cargas,
            revisión y colaboración entre equipos). Quienes trabajan con ella lo hacen con
            <strong>cuenta e inicio de sesión</strong>: no es solo este sitio público.
        </p>
        <p class="cen-publico__empty" style="margin:0 0 0.75rem;color:#333;font-size:15px;line-height:1.5;">
            Lo que estás viendo ahora es el <strong>dashboard público</strong>: una vitrina abierta con
            paneles de información nacional y complementa a Centinela;
            <strong>no reemplaza</strong> la plataforma autenticada donde ocurre el trabajo operativo diario.
        </p>
        <p class="cen-publico__empty" style="margin:0;color:#333;font-size:15px;line-height:1.5;">
            La plataforma la desarrolla el <strong>Servicio de Salud de Reloncaví</strong> y está
            puesta a disposición de otros territorios (Servicios de Salud). Cada adoptante la opera
            en su territorio con sus propios datos de manera independiente, manteniendo la
            <strong>confidencialidad territorial</strong>.
        </p>
    </div>

    <div class="cen-publico__section" style="margin-bottom:1.25rem;">
        <h2 style="margin:0 0 0.5rem;font-size:1.15rem;color:#1a3a5c;">Módulos de la plataforma</h2>
        <p class="cen-publico__empty" style="margin:0 0 0.65rem;color:#333;font-size:15px;line-height:1.5;">
            En la plataforma autenticada (con cuenta) los módulos disponibles dependen del Servicio de Salud
            y del año indicador. A modo general:
        </p>
        <p style="margin:0 0 0.35rem;font-weight:600;color:#1a3a5c;font-size:14px;">Habilitados / en uso operativo</p>
        <ul style="margin:0 0 0.85rem;padding-left:1.25rem;font-size:15px;line-height:1.55;color:#333;">
            <li><strong>COMGES</strong> — Compromisos de gestión (configuración, cortes, medios y evaluaciones).</li>
            <li><strong>Metas Ley 18.834</strong> — Seguimiento de metas por establecimiento.</li>
            <li><strong>Metas Ley 19.664</strong> — Metas por establecimiento, con prioridad y ponderaciones cuando aplica.
            </li>
            <li><strong>Metas Ley 20.707</strong> — Metas con granularidad por unidad interna además del establecimiento.
            </li>
            <li><strong>Soporte</strong> — Tickets internos de consultas e incidencias.</li>
            <li><strong>Establecimientos (SNSS)</strong> — Apoyo operativo al universo de establecimientos del Servicio.
            </li>
            <li><strong>Administración</strong> — Usuarios, perfiles, catálogos y configuración del sistema.</li>
        </ul>
        <p style="margin:0 0 0.35rem;font-weight:600;color:#1a3a5c;font-size:14px;">En desarrollo (no liberados para uso
            operativo)</p>
        <ul style="margin:0;padding-left:1.25rem;font-size:15px;line-height:1.55;color:#333;">
            <li><strong>ADP</strong> (Alta Dirección Pública) — En construcción; no disponible para carga ni cierre de
                periodos en producción.</li>
        </ul>
    </div>

    <div class="cen-publico__section" style="margin-bottom:1.25rem;">
        <h2 style="margin:0 0 0.5rem;font-size:1.15rem;color:#1a3a5c;">Para quién es</h2>
        <ul style="margin:0;padding-left:1.25rem;font-size:15px;line-height:1.55;color:#333;">
            <li><strong>Control de gestión</strong> y equipos de seguimiento institucional en los Servicios de Salud.</li>
            <li><strong>Referentes técnicos</strong> y responsables de establecimientos que cargan o revisan datos.</li>
            <li><strong>Jefaturas y directivos</strong> de establecimientos y Servicios de Salud que necesitan información
                para tomar decisiones.</li>
            <li><strong>Funcionarios</strong> de los Servicios de Salud y establecimientos que necesitan conocer el avance
                de los instrumentos de evaluación.</li>
        </ul>
    </div>

    <div class="cen-publico__section" style="margin-bottom:1.25rem;">
        <h2 style="margin:0 0 0.5rem;font-size:1.15rem;color:#1a3a5c;">Objetivos</h2>
        <ul style="margin:0;padding-left:1.25rem;font-size:15px;line-height:1.55;color:#333;">
            <li><strong>Estandarizar</strong> el registro, la revisión y el seguimiento de indicadores y compromisos.</li>
            <li><strong>Mejorar la trazabilidad</strong> de datos y medios de verificación para auditorías y planes de
                mejora.</li>
            <li><strong>Facilitar la coordinación</strong> entre quienes cargan información y quienes consolidan resultados.
            </li>
            <li><strong>Disponibilizar información</strong> para análisis y reportes alineados a los instrumentos vigentes.
            </li>
            <li><strong>Reducir la fricción operativa</strong> al centralizar flujos en un entorno único por Servicio de
                Salud.</li>
        </ul>
    </div>

    <div class="cen-publico__section" style="margin-bottom:1.25rem;">
        <p class="cen-publico__empty" style="margin:0;color:#333;font-size:15px;line-height:1.5;">
            Si pertenece a un Servicio de Salud y necesita trabajar con instrumentos de evaluación en Centinela,
            puede <a href="{{ route('centinela.access-request.create') }}"
                style="color:#1a3a5c;font-weight:600;">{{ 'solicitar acceso' }}</a> desde el encabezado o con este vínculo.
            Un administrador revisará su solicitud.
        </p>
    </div>
@endsection
