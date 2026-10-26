@extends('voyager::master')

@section('page_title', 'Versiones liberadas de la plataforma Centinela')

@section('css')
    @include('centinela.acerca-de.partials.compass-shell')
@stop

@section('content')
    <div class="page-content compass container-fluid releases-compass cen-compass-ui">
        @include('voyager::alerts')

        @include('centinela.acerca-de.partials.centinela-title')

        <x-centinela.compass.dashboard-block title="Versiones liberadas" icon="voyager-code">
            @include('centinela.acerca-de.partials.releases-heading')

            @if ($releasesUnavailable)
                <div class="alert alert-info releases-alert" role="alert">
                    No hay historial de versiones disponible en este momento.
                </div>
            @elseif (count($releases) === 0)
                @if ($releasesStale && $releasesSyncedAt)
                    <div class="alert alert-warning releases-alert" role="alert">
                        No se pudo contactar a GitHub en este momento. Se muestran las últimas notas de versión
                        guardadas
                        (sincronizadas el
                        {{ $releasesSyncedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}).
                    </div>
                @endif

                <p class="release-empty">
                    @if (!empty($deployedVersion))
                        No hay notas de versión publicadas hasta la versión en producción
                        (<strong>{{ $deployedVersion }}</strong>).
                    @else
                        No hay historial de versiones para mostrar hasta que exista un deploy exitoso con tag
                        SemVer registrado.
                    @endif
                </p>
            @else
                @if ($releasesStale && $releasesSyncedAt)
                    <div class="alert alert-warning releases-alert" role="alert">
                        No se pudo contactar a GitHub en este momento. Se muestran las últimas notas de versión
                        guardadas
                        (sincronizadas el
                        {{ $releasesSyncedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}).
                    </div>
                @endif

                @foreach ($releases as $release)
                    <details id="{{ \App\Services\Github\GithubReleasesService::anchorIdForRelease($release) }}"
                        class="release-block" @if ($loop->first) open @endif>
                        <summary>
                            {{ \App\Services\Github\GithubReleasesService::labelForRelease($release) }}
                            @if (!empty($release['display_date']))
                                <span class="release-meta">
                                    — {{ $release['display_date'] }}
                                </span>
                            @endif
                        </summary>
                        <div class="release-body">
                            <x-markdown>
                                {!! $release['body'] !!}
                            </x-markdown>
                        </div>
                    </details>
                @endforeach
            @endif
        </x-centinela.compass.dashboard-block>
    </div>
@endsection
