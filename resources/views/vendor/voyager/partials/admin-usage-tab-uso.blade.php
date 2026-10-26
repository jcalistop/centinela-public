<div class="row">
    @if (empty($usageEnabled))
        <div class="col-md-12">
            <p class="text-muted text-center" style="padding: 1.5rem;">
                Sin datos aún (debes habilitar `CENTINELA_USAGE_TRACKING_ENABLED=true`).
            </p>
        </div>
    @else
        <div class="col-md-8">
            <x-centinela.compass.section title="Últimos 30 días: usuarios vs hits (por día)" icon="voyager-bar-chart">
                <div id="chart-usage-30d" class="cen-chart-host" aria-label="Gráfico de uso últimos 30 días"></div>
                <div id="chart-usage-30d-empty" class="cen-chart-empty" style="display:none;">Sin datos aún.</div>
                <div id="chart-usage-30d-error" class="cen-chart-empty" style="display:none;">No se pudo cargar el
                    gráfico.</div>
            </x-centinela.compass.section>
        </div>
        <div class="col-md-4">
            <x-centinela.compass.section title="Últimos 7 días: hits por área (stack)" icon="voyager-pie-chart">
                <div id="chart-area-stack-7d" class="cen-chart-host" aria-label="Gráfico hits por área últimos 7 días">
                </div>
                <div id="chart-area-stack-7d-empty" class="cen-chart-empty" style="display:none;">Sin datos aún.</div>
                <div id="chart-area-stack-7d-error" class="cen-chart-empty" style="display:none;">No se pudo cargar el
                    gráfico.</div>
            </x-centinela.compass.section>
        </div>
    @endif
</div>
