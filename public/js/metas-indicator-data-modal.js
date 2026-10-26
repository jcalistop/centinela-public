(function () {
    "use strict";

    var chartInstance = null;
    var listenerRegistered = false;

    function periodoLabels() {
        return window.METAS_INSTRUMENT_PERIODO_LABELS || {};
    }

    function periodoLabel(periodo) {
        var labels = periodoLabels();

        return labels[periodo] || periodo;
    }

    function loadApexCharts(callback) {
        if (typeof ApexCharts !== "undefined") {
            callback();

            return;
        }

        var script = document.createElement("script");
        script.src =
            "https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min.js";
        script.onload = callback;
        script.onerror = function () {
            console.error(
                "[Centinela] No se pudo cargar ApexCharts desde CDN.",
            );
        };
        document.head.appendChild(script);
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return "";
        }

        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function numberOrDefault(value, fallback) {
        return value === null || value === undefined ? fallback : Number(value);
    }

    /**
     * Semáforo por fila (SDD-186): verde cuando el indicador aportaría el 100 % de su ponderación,
     * rojo cuando el aporte es 0 y amarillo cuando es proporcional. `cumple_meta` tiene precedencia
     * porque en indicadores donde menor es mejor el cociente sobre la meta queda bajo 100 % aun
     * cumpliéndola.
     */
    function cumplimientoBadgeHtml(cumpl, cumpleMeta, umbrales) {
        if (cumpl === null) {
            return '<span class="text-muted">\u2014</span>';
        }

        var pct = Number(cumpl).toFixed(1) + "%";
        var value = Number(cumpl);
        var superior = numberOrDefault(
            umbrales ? umbrales.superior : null,
            100,
        );
        var inferior = numberOrDefault(umbrales ? umbrales.inferior : null, 0);
        var cls;

        if (cumpleMeta === true || value >= superior) {
            cls = "label-success";
        } else if (value <= 0 || value < inferior) {
            cls = "label-danger";
        } else {
            cls = "label-warning";
        }

        return '<span class="label ' + cls + '">' + pct + "</span>";
    }

    function periodBadgeHtml(label) {
        return (
            '<span class="label label-primary metas-modal-period-badge">' +
            escapeHtml(label) +
            "</span>"
        );
    }

    function formatResultValue(value, decimales, unidadMedida) {
        if (value === null || value === undefined) {
            return "\u2014";
        }

        var numeric = Number(value);
        if (Number.isNaN(numeric)) {
            return "\u2014";
        }

        var formatted = numeric.toFixed(
            decimales !== undefined ? decimales : 2,
        );

        if (!unidadMedida) {
            return formatted;
        }
        if (unidadMedida === "%") {
            return formatted + "%";
        }

        return (
            formatted +
            ' <small class="text-muted">' +
            unidadMedida +
            "</small>"
        );
    }

    function formatCompactNumber(value) {
        if (value === null || value === undefined) {
            return "\u2014";
        }

        var numeric = Number(value);
        if (Number.isNaN(numeric)) {
            return "\u2014";
        }

        var formatted = numeric.toFixed(2);
        if (formatted.indexOf(".") === -1) {
            return formatted;
        }

        return formatted.replace(/\.?0+$/, "") || "0";
    }

    function formulaQuotientHtml(numerador, denominador) {
        return (
            formatCompactNumber(numerador) +
            " / " +
            formatCompactNumber(denominador)
        );
    }

    function buildTable(
        periodos,
        isSumatoria,
        usesComplexPrimaryComponents,
        dataEntryUrl,
        decimales,
        unidadMedida,
        umbrales,
    ) {
        var tbody = document.getElementById("indicatorModalTableBody");
        var thead = document.getElementById("indicatorModalTableHead");
        if (!tbody || !thead) {
            return;
        }

        // Tras SDD-202: 1 columna fórmula (sumatoria: Mes + Acum bajo el mismo header).
        var emptyColspan = isSumatoria ? 6 : 3;
        var complexFormulaColspan = isSumatoria ? 2 : 1;
        var complexFormulaRowspan = periodos.length;
        var complexFormulaRendered = false;
        var formulaHeaderTitle =
            'title="En f\u00f3rmulas simples: numerador \u00f7 denominador del per\u00edodo. Si no hay un par \u00fanico num/den: F\u00f3rmula compleja."';

        if (isSumatoria) {
            thead.innerHTML =
                "<tr>" +
                '<th class="text-center" rowspan="2">Per\u00edodo</th>' +
                '<th class="text-center" colspan="2" ' +
                formulaHeaderTitle +
                ">F\u00f3rmula de c\u00e1lculo</th>" +
                '<th class="text-center" rowspan="2">Resultado<br>mensual</th>' +
                '<th class="text-center" rowspan="2">Cumpl.<br>mensual</th>' +
                '<th class="text-center" rowspan="2">Resultado<br>acumulado</th>' +
                '<th class="text-center" rowspan="2">Cumpl.<br>acumulado</th>' +
                "</tr>" +
                "<tr>" +
                '<th class="text-center">Mes</th>' +
                '<th class="text-center">Acum.</th>' +
                "</tr>";
        } else {
            thead.innerHTML =
                "<tr>" +
                '<th class="text-center">Per\u00edodo</th>' +
                '<th class="text-center" ' +
                formulaHeaderTitle +
                ">F\u00f3rmula de c\u00e1lculo</th>" +
                '<th class="text-center">Resultado</th>' +
                '<th class="text-center">Cumplimiento</th>' +
                "</tr>";
        }

        tbody.innerHTML = "";

        function complexFormulaCellHtml() {
            if (!usesComplexPrimaryComponents || complexFormulaRendered) {
                return "";
            }

            complexFormulaRendered = true;
            var detailLink = dataEntryUrl
                ? '<br><a href="' +
                  dataEntryUrl +
                  '" class="btn btn-xs btn-default centinela-download-guard-link metas-modal-complex-formula-link"><i class="voyager-download"></i> Ver detalle de datos</a>'
                : "";

            return (
                '<td rowspan="' +
                complexFormulaRowspan +
                '" colspan="' +
                complexFormulaColspan +
                '" class="text-center text-muted metas-modal-complex-formula">F\u00f3rmula compleja' +
                detailLink +
                "</td>"
            );
        }

        periodos.forEach(function (p) {
            var tr = document.createElement("tr");
            var label = periodoLabel(p.periodo);

            if (p.error) {
                var missingData = p.error === "N/D";
                var issueLabel = missingData ? "Sin datos" : "Error";
                var escapedTitle = String(p.error).replace(/"/g, "&quot;");
                var issueCell = missingData
                    ? '<span class="text-muted" title="' +
                      escapedTitle +
                      '">' +
                      issueLabel +
                      "</span>"
                    : '<span class="label label-danger" title="' +
                      escapedTitle +
                      '">' +
                      issueLabel +
                      "</span>";

                tr.innerHTML =
                    '<td class="text-center">' +
                    periodBadgeHtml(label) +
                    "</td>" +
                    '<td colspan="' +
                    emptyColspan +
                    '" class="text-center">' +
                    issueCell +
                    "</td>";
            } else if (p.numerador === null && p.resultado === null) {
                tr.innerHTML =
                    '<td class="text-center">' +
                    periodBadgeHtml(label) +
                    "</td>" +
                    '<td colspan="' +
                    emptyColspan +
                    '" class="text-center text-muted metas-modal-empty-row">Sin datos calculados</td>';
            } else {
                var labelClass = p.cumple_meta
                    ? "label-success"
                    : "label-danger";
                var cumpl = cumplimientoBadgeHtml(
                    p.cumplimiento,
                    p.cumple_meta,
                    umbrales,
                );
                var formulaMes = formulaQuotientHtml(
                    p.numerador,
                    p.denominador,
                );
                var resStr = formatResultValue(
                    p.resultado,
                    decimales,
                    unidadMedida,
                );
                var resCell =
                    p.resultado !== null
                        ? '<span class="label ' +
                          labelClass +
                          '">' +
                          resStr +
                          "</span>"
                        : resStr;

                if (isSumatoria) {
                    var formulaAcum = formulaQuotientHtml(
                        p.numerador_acumulado,
                        p.denominador_acumulado,
                    );
                    var resAcumStr = formatResultValue(
                        p.resultado_acumulado,
                        decimales,
                        unidadMedida,
                    );
                    var cumpleMetaAcum =
                        p.cumple_meta_acumulado === true ||
                        p.cumple_meta_acumulado === false
                            ? p.cumple_meta_acumulado
                            : null;
                    var labelClassAcum =
                        cumpleMetaAcum === true
                            ? "label-success"
                            : cumpleMetaAcum === false
                              ? "label-danger"
                              : "label-info";
                    var resAcumCell =
                        p.resultado_acumulado !== null
                            ? '<span class="label ' +
                              labelClassAcum +
                              '">' +
                              resAcumStr +
                              "</span>"
                            : resAcumStr;
                    var cumplAcum = cumplimientoBadgeHtml(
                        p.cumplimiento_acumulado !== undefined
                            ? p.cumplimiento_acumulado
                            : null,
                        cumpleMetaAcum,
                        umbrales,
                    );

                    var trailingCells =
                        '<td class="text-center">' +
                        resCell +
                        "</td>" +
                        '<td class="text-center">' +
                        cumpl +
                        "</td>" +
                        '<td class="text-center">' +
                        resAcumCell +
                        "</td>" +
                        '<td class="text-center">' +
                        cumplAcum +
                        "</td>";

                    if (usesComplexPrimaryComponents) {
                        tr.innerHTML =
                            '<td class="text-center">' +
                            periodBadgeHtml(label) +
                            "</td>" +
                            complexFormulaCellHtml() +
                            trailingCells;
                    } else {
                        tr.innerHTML =
                            '<td class="text-center">' +
                            periodBadgeHtml(label) +
                            "</td>" +
                            '<td class="text-center">' +
                            formulaMes +
                            "</td>" +
                            '<td class="text-center">' +
                            formulaAcum +
                            "</td>" +
                            trailingCells;
                    }
                } else if (usesComplexPrimaryComponents) {
                    tr.innerHTML =
                        '<td class="text-center">' +
                        periodBadgeHtml(label) +
                        "</td>" +
                        complexFormulaCellHtml() +
                        '<td class="text-center">' +
                        resCell +
                        "</td>" +
                        '<td class="text-center">' +
                        cumpl +
                        "</td>";
                } else {
                    tr.innerHTML =
                        '<td class="text-center">' +
                        periodBadgeHtml(label) +
                        "</td>" +
                        '<td class="text-center">' +
                        formulaMes +
                        "</td>" +
                        '<td class="text-center">' +
                        resCell +
                        "</td>" +
                        '<td class="text-center">' +
                        cumpl +
                        "</td>";
                }
            }

            tbody.appendChild(tr);
        });

        if (typeof attachCentinelaDownloadGuards === "function") {
            attachCentinelaDownloadGuards(tbody);
        }
    }

    function normalizePayload(detail) {
        if (Array.isArray(detail)) {
            return detail[0] || {};
        }

        return detail || {};
    }

    function formatMetaEfectivaLabel(
        metaEfectiva,
        metaOperador,
        decimales,
        unidadMedida,
    ) {
        if (metaEfectiva === null || metaEfectiva === undefined) {
            return "";
        }

        var op = metaOperador || ">=";
        var value = formatResultValue(metaEfectiva, decimales, unidadMedida);

        return op + " " + value;
    }

    function handleOpenDataModal(detail) {
        detail = normalizePayload(detail);

        var title = detail.title;
        var clave = detail.clave;
        var centerNombre = detail.centerNombre || "";
        var centerCodigo = detail.centerCodigo || "";
        var metaNacional = detail.metaNacional;
        var metaEfectiva =
            detail.metaEfectiva !== undefined
                ? detail.metaEfectiva
                : metaNacional;
        var metaOperador = detail.metaOperador || ">=";
        var periodos = detail.periodos || [];
        var isSumatoria = !!detail.isSumatoria;
        var usesComplexPrimaryComponents =
            !!detail.usesComplexPrimaryComponents;
        var dataEntryUrl = detail.dataEntryUrl || null;
        var decimales = detail.decimales !== undefined ? detail.decimales : 2;
        var unidadMedida = detail.unidadMedida || null;
        var umbrales = {
            superior: detail.umbralSuperior,
            inferior: detail.umbralInferior,
        };

        var titleEl = document.getElementById("indicatorModalTitleText");
        var claveEl = document.getElementById("indicatorModalClave");
        if (claveEl) {
            if (clave) {
                claveEl.textContent = clave;
                claveEl.hidden = false;
            } else {
                claveEl.textContent = "";
                claveEl.hidden = true;
            }
        }
        if (titleEl) {
            titleEl.textContent = title || "";
        }

        var centerEl = document.getElementById("indicatorModalCenterText");
        var centerLabelEl = document.getElementById(
            "indicatorModalCenterLabel",
        );
        if (centerEl && centerLabelEl) {
            if (centerNombre) {
                centerLabelEl.textContent =
                    centerNombre +
                    (centerCodigo ? " (" + centerCodigo + ")" : "");
                centerEl.hidden = false;
            } else {
                centerLabelEl.textContent = "";
                centerEl.hidden = true;
            }
        }

        var metaWrapEl = document.getElementById("indicatorModalMetaEfectiva");
        var metaValueEl = document.getElementById(
            "indicatorModalMetaEfectivaValue",
        );
        if (metaWrapEl && metaValueEl) {
            var metaLabel = formatMetaEfectivaLabel(
                metaEfectiva,
                metaOperador,
                decimales,
                unidadMedida,
            );
            if (metaLabel) {
                metaValueEl.textContent = metaLabel;
                metaWrapEl.hidden = false;
            } else {
                metaValueEl.textContent = "";
                metaWrapEl.hidden = true;
            }
        }

        var footerEl = document.getElementById("indicatorModalFooter");
        var downloadLinkEl = document.getElementById(
            "indicatorModalDownloadLink",
        );
        if (footerEl && downloadLinkEl) {
            if (dataEntryUrl) {
                downloadLinkEl.href = dataEntryUrl;
                footerEl.hidden = false;
            } else {
                downloadLinkEl.href = "#";
                footerEl.hidden = true;
            }
        }

        buildTable(
            periodos,
            isSumatoria,
            usesComplexPrimaryComponents,
            dataEntryUrl,
            decimales,
            unidadMedida,
            umbrales,
        );

        if (typeof jQuery !== "undefined") {
            jQuery("#indicatorDataModal").modal("show");
            jQuery("#indicatorDataModal")
                .off("hidden.bs.modal.metasDownload")
                .on("hidden.bs.modal.metasDownload", function () {
                    if (typeof resetCentinelaDownloadGuards === "function") {
                        resetCentinelaDownloadGuards(
                            document.getElementById("indicatorModalTableBody"),
                        );
                        resetCentinelaDownloadGuards(
                            document.getElementById("indicatorModalFooter"),
                        );
                    }
                });
        }

        if (typeof attachCentinelaDownloadGuards === "function" && footerEl) {
            attachCentinelaDownloadGuards(footerEl);
        }

        var labels = periodos.map(function (p) {
            return periodoLabel(p.periodo);
        });
        var resultados = periodos.map(function (p) {
            return p.resultado;
        });
        var metas = periodos.map(function () {
            return metaEfectiva;
        });

        loadApexCharts(function () {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }

            var chartEl = document.getElementById("indicatorChart");
            if (!chartEl) {
                return;
            }

            var series = [
                {
                    name: "Resultado mes",
                    type: "bar",
                    data: resultados,
                },
            ];
            var colors = ["#008FFB", "#ff455f"];
            var strokeWidths = [0, 3];

            if (isSumatoria) {
                var resultadosAcum = periodos.map(function (p) {
                    return p.resultado_acumulado !== undefined
                        ? p.resultado_acumulado
                        : null;
                });
                series.push({
                    name: "Resultado acum.",
                    type: "line",
                    data: resultadosAcum,
                });
                series.push({ name: "Meta", type: "line", data: metas });
                colors = ["#008FFB", "#00E396", "#ff455f"];
                strokeWidths = [0, 3, 2];
            } else {
                series.push({ name: "Meta", type: "line", data: metas });
            }

            chartInstance = new ApexCharts(chartEl, {
                chart: {
                    type: "bar",
                    height: 280,
                    toolbar: { show: false },
                    fontFamily: "inherit",
                    animations: { enabled: true, speed: 450 },
                },
                series: series,
                xaxis: {
                    categories: labels,
                    labels: { style: { fontSize: "12px", colors: "#5a6b7d" } },
                },
                colors: colors,
                markers: {
                    size: isSumatoria ? [0, 4, 0] : [0, 0],
                    hover: { sizeOffset: 2 },
                },
                stroke: {
                    width: strokeWidths,
                    curve: "smooth",
                    dashArray: isSumatoria ? [0, 0, 6] : [0, 6],
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        columnWidth: "52%",
                    },
                },
                grid: {
                    borderColor: "#e8edf2",
                    strokeDashArray: 4,
                    padding: { left: 8, right: 8 },
                },
                dataLabels: { enabled: false },
                legend: {
                    position: "top",
                    horizontalAlign: "right",
                    fontSize: "12px",
                    markers: { radius: 12 },
                },
                tooltip: {
                    theme: "light",
                    shared: true,
                    intersect: false,
                },
                yaxis: {
                    labels: {
                        style: { colors: "#5a6b7d" },
                        formatter: function (v) {
                            return v !== null ? Number(v).toFixed(2) : "\u2014";
                        },
                    },
                },
            });
            chartInstance.render();
        });
    }

    function registerListener() {
        if (listenerRegistered || typeof Livewire === "undefined") {
            return;
        }

        listenerRegistered = true;
        Livewire.on("open-data-modal", handleOpenDataModal);
    }

    window.MetasIndicatorDataModal = {
        open: handleOpenDataModal,
        registerListener: registerListener,
    };

    document.addEventListener("livewire:init", registerListener);
    if (typeof Livewire !== "undefined") {
        registerListener();
    }

    if (window.METAS_INDICATOR_DATA_MODAL_ATTACH_GUARDS_ON_LOAD) {
        document.addEventListener("livewire:init", function () {
            Livewire.hook("morph.updated", function (payload) {
                if (
                    payload.el &&
                    typeof attachCentinelaDownloadGuards === "function"
                ) {
                    attachCentinelaDownloadGuards(payload.el);
                }
            });
        });

        document.addEventListener("DOMContentLoaded", function () {
            if (typeof attachCentinelaDownloadGuards === "function") {
                attachCentinelaDownloadGuards(
                    document.querySelector(".page-content"),
                );
            }
        });
    }
})();
