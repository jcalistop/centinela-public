<div class="row">
    <div class="col-md-3">
        <x-centinela.compass.stat-tile accent icon="voyager-person" :value="number_format((int) ($usageDashboard['authSessionsOpen'] ?? 0), 0, ',', '.')" label="Sesiones auth abiertas" />
    </div>
    <div class="col-md-3">
        <x-centinela.compass.stat-tile accent icon="voyager-eye" :value="number_format((int) ($publicDashboardVisitTotal ?? 0), 0, ',', '.')" label="Visitas al dashboard público"
            :data-public-dashboard-visits="(int) ($publicDashboardVisitTotal ?? 0)" />
    </div>
    <div class="col-md-6">
        <x-centinela.compass.section title="Últimos 7 días: actividad por área" icon="voyager-activity" flush>
            <div class="table-responsive">
                <table class="table table-hover cen-compass-table">
                    <thead>
                        <tr>
                            <th>Área</th>
                            <th class="text-right">Hits</th>
                            <th class="text-right">Usuarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($usageDashboard['areaTotals7'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['area'] }}</td>
                                <td class="text-right">
                                    {{ number_format((int) $row['hits'], 0, ',', '.') }}
                                </td>
                                <td class="text-right">
                                    {{ number_format((int) $row['users'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted text-center" style="padding: 1.5rem;">
                                    @if (!$usageEnabled)
                                        Sin datos aún (debes habilitar `CENTINELA_USAGE_TRACKING_ENABLED=true`).
                                    @else
                                        Sin datos aún. Navega por `/admin` (ADP, Metas, COMGES) para generar muestras.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-centinela.compass.section>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <x-centinela.compass.section title="Últimos 30 días: usuarios distintos por día" icon="voyager-calendar" flush>
            <div class="table-responsive">
                <table class="table table-striped cen-compass-table">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th class="text-right">Usuarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($usageDashboard['dailyUsers30'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['day'] }}</td>
                                <td class="text-right">
                                    {{ number_format((int) $row['users'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-muted text-center" style="padding: 1.5rem;">
                                    Sin datos aún.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-centinela.compass.section>
    </div>

    <div class="col-md-6">
        <x-centinela.compass.section title="Top 10 Servicios de Salud (usuarios distintos, últimos 7 días)"
            icon="voyager-company" flush>
            <div class="table-responsive">
                <table class="table table-striped cen-compass-table">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th class="text-right">Usuarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($usageDashboard['topSs7'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">
                                    {{ number_format((int) $row['users'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-muted text-center" style="padding: 1.5rem;">
                                    Sin datos aún.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-centinela.compass.section>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <x-centinela.compass.section title="Últimos usuarios activos (7 días)" icon="voyager-people" flush>
            <div class="table-responsive">
                <table class="table table-striped cen-compass-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Servicio de Salud</th>
                            <th class="text-right">Última actividad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($usageDashboard['recentUsers7'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['ss_name'] ?? '—' }}</td>
                                <td class="text-right">{{ $row['last_seen_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted text-center" style="padding: 1.5rem;">
                                    Sin datos aún.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-centinela.compass.section>
    </div>
</div>
