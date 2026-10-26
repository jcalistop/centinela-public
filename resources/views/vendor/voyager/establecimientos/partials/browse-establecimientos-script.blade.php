<script>
    (function() {
        var mapRoot = document.getElementById('cen-est-map-root');
        var mapEl = document.getElementById('cen-est-map');
        var listRoot = document.getElementById('cen-est-list-root');
        var communesHost = mapRoot || document.getElementById('cen-est-filters-root');

        var allMarkers = [];
        var allListItems = [];
        var allCommunes = [];
        var listColumns = [];
        var perPage = 15;
        var currentListPage = 1;
        var listSortKey = 'establecimiento';
        var listSortDir = 'asc';
        var listActionsLabel = 'Acciones';

        if (mapRoot) {
            try {
                allMarkers = JSON.parse(mapRoot.getAttribute('data-markers') || '[]');
            } catch (e) {
                allMarkers = [];
            }
        }

        if (listRoot) {
            try {
                allListItems = JSON.parse(listRoot.getAttribute('data-list-items') || '[]');
                listColumns = JSON.parse(listRoot.getAttribute('data-columns') || '[]');
                perPage = parseInt(listRoot.getAttribute('data-per-page') || '15', 10) || 15;
            } catch (e) {
                allListItems = [];
                listColumns = [];
            }
        }

        if (communesHost) {
            try {
                allCommunes = JSON.parse(communesHost.getAttribute('data-communes') || '[]');
            } catch (e) {
                allCommunes = [];
            }
        }

        var form = document.getElementById('cen-est-filters');
        var communeSelect = document.getElementById('cen-est-filter-comuna');
        var provinceSelect = document.getElementById('cen-est-filter-provincia');
        var visibleCountEl = document.getElementById('cen-est-map-visible-count');
        var titleBadge = document.getElementById('cen-est-map-title-badge');
        var listTitleBadge = document.getElementById('cen-est-list-title-badge');
        var listTbody = document.getElementById('cen-est-list-tbody');
        var listEmpty = document.getElementById('cen-est-list-empty');
        var listSummary = document.getElementById('cen-est-list-summary');
        var listPagination = document.getElementById('cen-est-list-pagination');
        var listTableWrap = listRoot ? listRoot.querySelector('.cen-read-table-wrap') : null;
        var listThead = document.getElementById('cen-est-list-thead');
        var establishmentsSelect = document.getElementById('cen-est-filter-establecimientos');

        if (listRoot && listRoot.getAttribute('data-actions-label')) {
            listActionsLabel = listRoot.getAttribute('data-actions-label');
        }

        var map = null;
        var markerLayer = null;

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function escapeAttr(text) {
            return String(text == null ? '' : text)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function getSelectedCodigos() {
            if (!establishmentsSelect) {
                return null;
            }

            var codigos = [];

            if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(establishmentsSelect).data('select2')) {
                var selected = window.jQuery(establishmentsSelect).val();

                if (selected) {
                    codigos = Array.isArray(selected) ? selected : [selected];
                }
            } else {
                codigos = Array.from(establishmentsSelect.selectedOptions).map(function(option) {
                    return option.value;
                });
            }

            return codigos.length > 0 ? codigos : null;
        }

        var pendienteToggles = document.querySelectorAll('.cen-est-pendiente-toggle');

        function getSoloPendiente() {
            return Array.from(pendienteToggles).some(function(toggle) {
                return toggle.getAttribute('aria-pressed') === 'true';
            });
        }

        function setPendienteToggles(active) {
            pendienteToggles.forEach(function(toggle) {
                toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function setPendienteTogglesEnabled(enabled) {
            pendienteToggles.forEach(function(toggle) {
                toggle.disabled = !enabled;

                if (enabled) {
                    toggle.setAttribute('title', 'Mostrar solo establecimientos con tarea pendiente');
                } else {
                    toggle.setAttribute('title',
                        'No hay tareas pendientes entre los establecimientos filtrados');
                    toggle.setAttribute('aria-pressed', 'false');
                }
            });
        }

        function hasPendingInFilteredScope(filters) {
            var baseFilters = {
                codigos: filters.codigos,
                tipo: filters.tipo,
                admDependency: filters.admDependency,
                nivelAtencion: filters.nivelAtencion,
                tieneUrgencia: filters.tieneUrgencia,
                provinceId: filters.provinceId,
                communeId: filters.communeId,
                soloPendiente: false,
            };

            return allListItems.some(function(item) {
                return item.pendiente && matchesFilters(item, baseFilters);
            });
        }

        function getFilterValues() {
            var values = {
                codigos: getSelectedCodigos(),
                soloPendiente: getSoloPendiente(),
            };

            if (!form) {
                return values;
            }
            var fields = form.querySelectorAll('[data-filter]');

            fields.forEach(function(field) {
                var key = field.getAttribute('data-filter');

                if (key === 'codigos') {
                    return;
                }

                var value = field.value;

                if (key === 'provinceId' || key === 'communeId') {
                    values[key] = value === '' ? null : parseInt(value, 10);
                } else {
                    values[key] = value === '' ? null : value;
                }
            });

            return values;
        }

        function matchesFilters(item, filters) {
            if (filters.codigos !== null && filters.codigos.indexOf(item.codigo) === -1) {
                return false;
            }

            if (filters.tipo !== null && item.tipo !== filters.tipo) {
                return false;
            }

            if (filters.admDependency !== null && item.admDependency !== filters.admDependency) {
                return false;
            }

            if (filters.nivelAtencion !== null && item.nivelAtencion !== filters.nivelAtencion) {
                return false;
            }

            if (filters.tieneUrgencia !== null && item.tieneUrgencia !== filters.tieneUrgencia) {
                return false;
            }

            if (filters.provinceId !== null && item.provinceId !== filters.provinceId) {
                return false;
            }

            if (filters.communeId !== null && item.communeId !== filters.communeId) {
                return false;
            }

            if (filters.soloPendiente && !item.pendiente) {
                return false;
            }

            return true;
        }

        function buildPopup(item) {
            var popup = '<strong>' + escapeHtml(item.nombre) + '</strong> (' + escapeHtml(item.codigo) + ')';

            if (item.tipo) {
                popup += '<br><span class="text-muted">' + escapeHtml(item.tipo) + '</span>';
            }

            if (item.communeNombre) {
                popup += '<br><span class="text-muted">' + escapeHtml(item.communeNombre);
                if (item.provinceNombre) {
                    popup += ', ' + escapeHtml(item.provinceNombre);
                }
                popup += '</span>';
            }

            if (item.pendiente) {
                popup += '<br><span class="label label-warning">Tarea pendiente</span>';
            }

            popup += '<br><a href="' + escapeAttr(item.showUrl) + '">Ver ficha</a>';

            return popup;
        }

        function initMap() {
            if (!mapEl || typeof L === 'undefined') {
                return;
            }

            map = L.map(mapEl, {
                scrollWheelZoom: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            markerLayer = L.layerGroup().addTo(map);

            setTimeout(function() {
                map.invalidateSize();
            }, 250);
        }

        function renderMarkers(items) {
            if (!markerLayer || !map) {
                return;
            }

            var defaultIcon = L.divIcon({
                className: 'cen-map-marker cen-map-marker--default',
                html: '',
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            });
            var pendingIcon = L.divIcon({
                className: 'cen-map-marker cen-map-marker--pending',
                html: '',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });

            markerLayer.clearLayers();
            var bounds = [];

            items.forEach(function(item) {
                if (typeof item.lat !== 'number' || typeof item.lng !== 'number') {
                    return;
                }

                var latLng = [item.lat, item.lng];
                bounds.push(latLng);

                L.marker(latLng, {
                    icon: item.pendiente ? pendingIcon : defaultIcon
                }).addTo(markerLayer).bindPopup(buildPopup(item));
            });

            if (bounds.length === 1) {
                map.setView(bounds[0], 12);
            } else if (bounds.length > 1) {
                map.fitBounds(bounds, {
                    padding: [40, 40],
                    maxZoom: 13
                });
            } else {
                map.setView([-33.4489, -70.6693], 6);
            }

            var count = bounds.length;

            if (visibleCountEl) {
                visibleCountEl.textContent = String(count);
            }

            if (titleBadge) {
                titleBadge.textContent = String(count);
            }
        }

        function renderListColumn(item, key) {
            if (key === 'establecimiento') {
                var html = '<div class="cen-est-list-establecimiento">';
                html += '<span class="cen-est-list-establecimiento__nombre">' + escapeHtml(item.nombre || '—');
                if (item.codigo) {
                    html += ' (' + escapeHtml(item.codigo) + ')';
                }
                html += '</span>';

                if (item.pendiente) {
                    html +=
                        '<span class="cen-read-badge cen-read-badge--warning cen-est-list-establecimiento__badge">Pendiente</span>';
                }

                if (item.madreDescripcion) {
                    html += '<span class="cen-est-list-establecimiento__madre">' +
                        escapeHtml(item.madreDescripcion) + '</span>';
                }

                html += '</div>';

                return html;
            }

            if (key === 'codigo') {
                return escapeHtml(item.codigo || '—');
            }

            if (key === 'tipo') {
                return '<span>' + escapeHtml(item.tipo || '—') + '</span>';
            }

            if (key === 'admDependency') {
                return '<span>' + escapeHtml(item.admDependency || '—') + '</span>';
            }

            if (key === 'region') {
                return escapeHtml(item.regionNombre || '—');
            }

            if (key === 'commune') {
                return escapeHtml(item.communeNombre || '—');
            }

            if (key === 'geo') {
                if (item.hasCoordinates) {
                    return '<span class="cen-est-geo cen-est-geo--ok" title="Georeferenciado en mapa">' +
                        '<i class="voyager-location"></i></span>';
                }

                return '<span class="cen-est-geo cen-est-geo--invalid" title="Sin coordenadas válidas">' +
                    '<i class="voyager-location"></i></span>';
            }

            return '—';
        }

        function getListSortValue(item, key) {
            if (key === 'establecimiento') {
                return item.nombre || '';
            }

            if (key === 'codigo') {
                return item.codigo || '';
            }

            if (key === 'tipo') {
                return item.tipo || '';
            }

            if (key === 'admDependency') {
                return item.admDependency || '';
            }

            if (key === 'region') {
                return item.regionNombre || '';
            }

            if (key === 'commune') {
                return item.communeNombre || '';
            }

            if (key === 'geo') {
                return item.hasCoordinates ? 1 : 0;
            }

            return '';
        }

        function compareListItems(a, b) {
            var valueA = getListSortValue(a, listSortKey);
            var valueB = getListSortValue(b, listSortKey);
            var result = 0;

            if (listSortKey === 'geo') {
                result = valueA - valueB;
            } else {
                result = String(valueA).localeCompare(String(valueB), 'es', {
                    sensitivity: 'base',
                    numeric: true
                });
            }

            if (result === 0) {
                result = String(a.codigo || '').localeCompare(String(b.codigo || ''), 'es', {
                    sensitivity: 'base',
                    numeric: true
                });
            }

            return listSortDir === 'desc' ? -result : result;
        }

        function sortListItems(items) {
            return items.slice().sort(compareListItems);
        }

        function renderListTableHead() {
            if (!listThead || listColumns.length === 0) {
                return;
            }

            var rowHtml = '<tr>';

            listColumns.forEach(function(column) {
                var isActive = listSortKey === column.key;
                var thClass = 'cen-est-list-sortable';

                if (column.key === 'geo') {
                    thClass += ' text-center';
                }

                rowHtml += '<th class="' + thClass + '">' +
                    '<button type="button" class="cen-est-list-sort' + (isActive ? ' is-active is-' +
                        listSortDir : '') + '" data-sort-key="' + escapeAttr(column.key) + '" aria-sort="' +
                    (isActive ? (listSortDir === 'asc' ? 'ascending' : 'descending') : 'none') + '">' +
                    '<span class="cen-est-list-sort__label">' + escapeHtml(column.label) + '</span>' +
                    '<span class="cen-est-list-sort__icons" aria-hidden="true">' +
                    '<i class="voyager-angle-up cen-est-list-sort__icon cen-est-list-sort__icon--up"></i>' +
                    '<i class="voyager-angle-down cen-est-list-sort__icon cen-est-list-sort__icon--down"></i>' +
                    '</span></button></th>';
            });

            rowHtml += '<th class="actions text-right dt-not-orderable">' + escapeHtml(listActionsLabel) +
                '</th></tr>';

            listThead.innerHTML = rowHtml;

            listThead.querySelectorAll('.cen-est-list-sort').forEach(function(button) {
                button.addEventListener('click', function() {
                    var key = button.getAttribute('data-sort-key');

                    if (!key) {
                        return;
                    }

                    if (listSortKey === key) {
                        listSortDir = listSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        listSortKey = key;
                        listSortDir = key === 'geo' ? 'desc' : 'asc';
                    }

                    renderListTableHead();
                    renderListPage(window.__cenFilteredListItems || [], 1);
                });
            });
        }

        function renderListPage(items, page) {
            if (!listTbody) {
                return;
            }

            var sortedItems = sortListItems(items);
            var total = sortedItems.length;
            var totalPages = Math.max(1, Math.ceil(total / perPage));
            var safePage = Math.min(Math.max(1, page), totalPages);
            currentListPage = safePage;

            var start = (safePage - 1) * perPage;
            var pageItems = sortedItems.slice(start, start + perPage);
            var rowsHtml = '';

            pageItems.forEach(function(item) {
                rowsHtml += '<tr>';
                listColumns.forEach(function(column) {
                    var cellClass = column.key === 'geo' ? ' class="text-center"' : '';
                    rowsHtml += '<td' + cellClass + '>' + renderListColumn(item, column.key) +
                        '</td>';
                });
                rowsHtml += '<td class="no-sort no-click bread-actions">' +
                    '<a href="' + escapeAttr(item.showUrl) +
                    '" class="btn btn-sm btn-warning view" title="Ver">' +
                    '<i class="voyager-eye"></i> <span class="hidden-xs hidden-sm">Ver</span></a></td>';
                rowsHtml += '</tr>';
            });

            listTbody.innerHTML = rowsHtml;

            var showTable = total > 0;

            if (listTableWrap) {
                listTableWrap.style.display = showTable ? '' : 'none';
            }

            if (listEmpty) {
                listEmpty.style.display = showTable ? 'none' : 'block';
            }

            if (listTitleBadge) {
                listTitleBadge.textContent = String(total);
            }

            if (listSummary) {
                if (total === 0) {
                    listSummary.textContent = 'Sin registros que mostrar';
                } else {
                    var from = start + 1;
                    var to = start + pageItems.length;
                    listSummary.textContent = 'Mostrando ' + from + ' a ' + to + ' de ' + total + ' registros';
                }
            }

            renderListPagination(total, safePage, totalPages);
        }

        function renderListPagination(total, page, totalPages) {
            if (!listPagination) {
                return;
            }

            if (total === 0) {
                listPagination.innerHTML = '';
                return;
            }

            var html = '';
            var prevDisabled = page <= 1 ? ' class="disabled"' : '';
            var nextDisabled = page >= totalPages ? ' class="disabled"' : '';

            html += '<li' + prevDisabled + '><a href="#" data-page="' + (page - 1) +
                '" aria-label="Anterior">&laquo;</a></li>';

            for (var i = 1; i <= totalPages; i++) {
                if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - page) > 1) {
                    if (i === 3 || i === totalPages - 2) {
                        html += '<li class="disabled"><span>…</span></li>';
                    }
                    continue;
                }

                html += '<li' + (i === page ? ' class="active"' : '') +
                    '><a href="#" data-page="' + i + '">' + i + '</a></li>';
            }

            html += '<li' + nextDisabled + '><a href="#" data-page="' + (page + 1) +
                '" aria-label="Siguiente">&raquo;</a></li>';

            listPagination.innerHTML = html;

            listPagination.querySelectorAll('a[data-page]').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    var parent = link.parentElement;

                    if (parent && parent.classList.contains('disabled')) {
                        return;
                    }

                    var targetPage = parseInt(link.getAttribute('data-page'), 10);

                    if (!isNaN(targetPage)) {
                        renderListPage(window.__cenFilteredListItems || [], targetPage);
                    }
                });
            });
        }

        function repopulateCommuneSelect(provinceId, preserveCommuneId) {
            if (!communeSelect) {
                return;
            }

            var previous = preserveCommuneId ? String(preserveCommuneId) : communeSelect.value;
            communeSelect.innerHTML = '<option value="">Todas</option>';

            if (provinceId === null || isNaN(provinceId)) {
                communeSelect.disabled = true;
                communeSelect.value = '';

                return;
            }

            var options = allCommunes.filter(function(c) {
                return c.provinceId === provinceId;
            });

            options.forEach(function(c) {
                var opt = document.createElement('option');
                opt.value = String(c.id);
                opt.textContent = c.nombre;
                communeSelect.appendChild(opt);
            });

            communeSelect.disabled = options.length === 0;

            if (previous && communeSelect.querySelector('option[value="' + previous + '"]')) {
                communeSelect.value = previous;
            } else {
                communeSelect.value = '';
            }
        }

        function applyFilters() {
            var filters = getFilterValues();
            var pendingAvailable = hasPendingInFilteredScope(filters);

            setPendienteTogglesEnabled(pendingAvailable);

            if (!pendingAvailable) {
                setPendienteToggles(false);
                filters.soloPendiente = false;
            } else {
                filters.soloPendiente = getSoloPendiente();
            }

            var filteredMarkers = allMarkers.filter(function(item) {
                return matchesFilters(item, filters);
            });
            var filteredList = allListItems.filter(function(item) {
                return matchesFilters(item, filters);
            });

            window.__cenFilteredListItems = filteredList;

            renderMarkers(filteredMarkers);
            renderListPage(filteredList, 1);
        }

        function initEstablishmentSelect2() {
            if (!window.jQuery || !window.jQuery.fn.select2 || !establishmentsSelect) {
                return;
            }

            var $select = window.jQuery(establishmentsSelect);

            if ($select.data('select2')) {
                $select.select2('destroy');
            }

            $select.select2({
                width: '100%',
                placeholder: $select.data('placeholder') || 'Buscar y seleccionar establecimientos…',
                allowClear: true,
                closeOnSelect: false,
                language: {
                    noResults: function() {
                        return 'Sin resultados';
                    },
                    searching: function() {
                        return 'Buscando…';
                    }
                }
            }).on('change', applyFilters);
        }

        function clearEstablishmentSelect2() {
            if (!establishmentsSelect) {
                return;
            }

            if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(establishmentsSelect).data('select2')) {
                window.jQuery(establishmentsSelect).val(null).trigger('change');
            } else {
                Array.from(establishmentsSelect.options).forEach(function(option) {
                    option.selected = false;
                });
            }
        }

        renderListTableHead();
        initMap();
        initEstablishmentSelect2();

        if (form) {
            form.addEventListener('change', function(event) {
                if (event.target === provinceSelect) {
                    var provinceId = provinceSelect.value === '' ? null : parseInt(provinceSelect.value,
                        10);
                    repopulateCommuneSelect(provinceId, null);
                }

                applyFilters();
            });
        }

        var resetBtn = document.getElementById('cen-est-filters-reset');

        if (resetBtn && form) {
            resetBtn.addEventListener('click', function() {
                form.reset();
                clearEstablishmentSelect2();
                repopulateCommuneSelect(null, null);

                setPendienteToggles(false);
                applyFilters();
            });
        }

        pendienteToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                if (toggle.disabled) {
                    return;
                }

                setPendienteToggles(!getSoloPendiente());
                applyFilters();
            });
        });

        repopulateCommuneSelect(null, null);
        applyFilters();
    })();
</script>
