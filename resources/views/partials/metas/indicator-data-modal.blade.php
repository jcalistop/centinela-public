<div class="modal fade" id="indicatorDataModal" tabindex="-1" role="dialog" aria-labelledby="indicatorDataModalLabel">
    <div class="modal-dialog modal-lg metas-indicator-data-modal" role="document">
        <div class="modal-content">
            <div class="modal-header metas-indicator-data-modal__header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="metas-indicator-data-modal__heading">
                    <span class="metas-indicator-data-modal__icon" aria-hidden="true">
                        <i class="voyager-bar-chart"></i>
                    </span>
                    <div class="metas-indicator-data-modal__titles">
                        <div class="metas-indicator-data-modal__title-row">
                            <code id="indicatorModalClave" class="metas-indicator-data-modal__clave" hidden></code>
                            <span id="indicatorModalTitleText" class="metas-indicator-data-modal__title">Datos del
                                indicador</span>
                        </div>
                        <small id="indicatorModalCenterText" class="metas-indicator-data-modal__center text-muted"
                            hidden>
                            <i class="voyager-shop"></i>
                            <span id="indicatorModalCenterLabel"></span>
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-body metas-indicator-data-modal__body">
                <section class="metas-indicator-data-modal__section" aria-labelledby="indicatorModalChartHeading">
                    <h5 class="metas-indicator-data-modal__section-title" id="indicatorModalChartHeading">
                        <i class="voyager-bar-chart"></i> Evolución por período
                    </h5>
                    <div class="metas-indicator-data-modal__chart-panel">
                        <div id="indicatorChart" class="metas-indicator-modal-chart"></div>
                    </div>
                </section>

                <section class="metas-indicator-data-modal__section" aria-labelledby="indicatorModalTableHeading">
                    <div class="metas-indicator-data-modal__section-heading clearfix">
                        <h5 class="metas-indicator-data-modal__section-title" id="indicatorModalTableHeading">
                            <i class="voyager-list"></i> Detalle por período
                        </h5>
                        <div id="indicatorModalMetaEfectiva"
                            class="metas-indicator-data-modal__meta-efectiva pull-right text-muted" hidden>
                            Meta efectiva:
                            <strong id="indicatorModalMetaEfectivaValue" class="text-primary"></strong>
                        </div>
                    </div>
                    <div class="table-responsive metas-indicator-data-modal__table-wrap">
                        <table
                            class="table table-bordered table-condensed metas-instrument-table metas-modal-table metas-indicator-data-modal__table"
                            id="indicatorModalTable">
                            <thead id="indicatorModalTableHead" class="metas-indicator-data-modal__thead">
                                <tr>
                                    <th class="text-center">Período</th>
                                    <th class="text-center"
                                        title="En fórmulas simples: numerador ÷ denominador del período. Si no hay un par único num/den: Fórmula compleja.">
                                        Fórmula de cálculo
                                    </th>
                                    <th class="text-center">Resultado</th>
                                    <th class="text-center">Cumplimiento</th>
                                </tr>
                            </thead>
                            <tbody id="indicatorModalTableBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="modal-footer metas-indicator-data-modal__footer" id="indicatorModalFooter" hidden>
                <a href="#" id="indicatorModalDownloadLink"
                    class="btn btn-primary btn-sm centinela-download-guard-link">
                    <i class="voyager-download"></i> Descargar datos
                </a>
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@include('partials.metas.indicator-data-modal-bootstrap-stack')
