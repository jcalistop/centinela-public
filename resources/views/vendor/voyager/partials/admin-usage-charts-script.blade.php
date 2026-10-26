<script>
    (function() {
        var charts = {
            usage30: null,
            stack7: null
        };

        function show(el) {
            if (el) {
                el.style.display = '';
            }
        }

        function hide(el) {
            if (el) {
                el.style.display = 'none';
            }
        }

        function loadApexCharts(callback) {
            if (typeof ApexCharts !== 'undefined') {
                callback();
                return;
            }

            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min.js';
            script.onload = callback;
            script.onerror = function() {
                show(document.getElementById('chart-usage-30d-error'));
                show(document.getElementById('chart-area-stack-7d-error'));
                console.error('[Centinela] No se pudo cargar ApexCharts desde CDN.');
            };
            document.head.appendChild(script);
        }

        var usage30 = @json($usageDashboard['dailySummary30'] ?? []);
        var stack7 = @json($usageDashboard['areaStack7'] ?? ['categories' => [], 'series' => []]);
        var nf = new Intl.NumberFormat('es-CL');

        var AREA_COLORS = {
            compromisos: '#1f77b4',
            adp: '#ff7f0e',
            metas_18834: '#2ca02c',
            metas_19664: '#d62728',
            metas_20707: '#9467bd',
            metas_other: '#8c564b',
            other: '#7f7f7f'
        };

        function renderCharts() {
            var el30 = document.querySelector('#chart-usage-30d');
            var elStack = document.querySelector('#chart-area-stack-7d');
            var empty30 = document.getElementById('chart-usage-30d-empty');
            var emptyStack = document.getElementById('chart-area-stack-7d-empty');
            var err30 = document.getElementById('chart-usage-30d-error');
            var errStack = document.getElementById('chart-area-stack-7d-error');

            hide(empty30);
            hide(emptyStack);
            hide(err30);
            hide(errStack);

            if (el30 && Array.isArray(usage30) && usage30.length > 0) {
                try {
                    var categories30 = usage30.map(function(r) {
                        return r.day;
                    });
                    var series30 = [{
                        name: 'Usuarios',
                        type: 'line',
                        data: usage30.map(function(r) {
                            return r.users;
                        })
                    }, {
                        name: 'Hits',
                        type: 'column',
                        data: usage30.map(function(r) {
                            return r.hits;
                        })
                    }];

                    charts.usage30 = new ApexCharts(el30, {
                        chart: {
                            type: 'line',
                            height: 320,
                            toolbar: {
                                show: false
                            }
                        },
                        stroke: {
                            width: [3, 0]
                        },
                        dataLabels: {
                            enabled: false
                        },
                        tooltip: {
                            y: {
                                formatter: function(val, opts) {
                                    var label = opts && opts.seriesIndex === 0 ? ' usuarios' : ' hits';
                                    return nf.format(val) + label;
                                }
                            }
                        },
                        series: series30,
                        xaxis: {
                            categories: categories30
                        },
                        yaxis: [{
                            title: {
                                text: 'Usuarios'
                            }
                        }, {
                            opposite: true,
                            title: {
                                text: 'Hits'
                            }
                        }],
                        legend: {
                            position: 'top'
                        }
                    });

                    charts.usage30.render();
                } catch (e) {
                    show(err30);
                }
            } else if (el30) {
                show(empty30);
            }

            if (
                elStack &&
                stack7 &&
                Array.isArray(stack7.categories) &&
                Array.isArray(stack7.series) &&
                stack7.categories.length > 0 &&
                stack7.series.length > 0
            ) {
                try {
                    charts.stack7 = new ApexCharts(elStack, {
                        chart: {
                            type: 'bar',
                            height: 320,
                            stacked: true,
                            toolbar: {
                                show: false
                            }
                        },
                        plotOptions: {
                            bar: {
                                horizontal: false
                            }
                        },
                        dataLabels: {
                            enabled: false
                        },
                        colors: stack7.series.map(function(s) {
                            return AREA_COLORS[s.name] || '#999999';
                        }),
                        tooltip: {
                            y: {
                                formatter: function(val) {
                                    return nf.format(val) + ' hits';
                                }
                            }
                        },
                        series: stack7.series,
                        xaxis: {
                            categories: stack7.categories
                        },
                        legend: {
                            position: 'bottom'
                        }
                    });
                    charts.stack7.render();
                } catch (e) {
                    show(errStack);
                }
            } else if (elStack) {
                show(emptyStack);
            }
        }

        loadApexCharts(renderCharts);
    })();
</script>
