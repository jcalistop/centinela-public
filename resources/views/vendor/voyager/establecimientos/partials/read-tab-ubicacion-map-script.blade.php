<script>
    (function() {
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function initReadUbicacionMap() {
            var mapEl = document.getElementById('cen-read-ubicacion-map');
            if (!mapEl || typeof L === 'undefined') {
                return;
            }

            if (mapEl.getAttribute('data-map-initialized') === '1') {
                return;
            }

            var lat = parseFloat(mapEl.getAttribute('data-lat'));
            var lng = parseFloat(mapEl.getAttribute('data-lng'));

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            var label = mapEl.getAttribute('data-label') || '';
            var codigo = mapEl.getAttribute('data-codigo') || '';

            var map = L.map(mapEl, {
                scrollWheelZoom: true
            }).setView([lat, lng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            var icon = L.divIcon({
                className: 'cen-map-marker cen-map-marker--default',
                html: '',
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            });

            var popupHtml = '<strong>' + escapeHtml(label) + '</strong>';
            if (codigo) {
                popupHtml += ' (' + escapeHtml(codigo) + ')';
            }

            L.marker([lat, lng], {
                icon: icon
            }).addTo(map).bindPopup(popupHtml);

            mapEl.setAttribute('data-map-initialized', '1');

            function refreshMapSize() {
                map.invalidateSize();
            }

            setTimeout(refreshMapSize, 100);
            setTimeout(refreshMapSize, 400);

            window.addEventListener('load', refreshMapSize);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initReadUbicacionMap);
        } else {
            initReadUbicacionMap();
        }
    })();
</script>
