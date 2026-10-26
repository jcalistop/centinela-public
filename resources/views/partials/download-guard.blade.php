            function centinelaDownloadLinkLocksUntilModalClose(link) {
            return !!link.closest('#indicatorDataModal');
            }

            function markCentinelaDownloadLoading(link) {
            link.dataset.centinelaDownloading = '1';
            link.dataset.centinelaDownloadHref = link.getAttribute('href') || '';
            link.style.pointerEvents = 'none';
            link.setAttribute('aria-disabled', 'true');
            link.dataset.centinelaDownloadOriginalHtml = link.innerHTML;
            link.innerHTML = 'Generando…';
            }

            function markCentinelaDownloadCompleted(link) {
            link.dataset.centinelaDownloading = '0';
            link.dataset.centinelaDownloadCompleted = '1';
            link.style.pointerEvents = 'none';
            link.setAttribute('aria-disabled', 'true');
            link.removeAttribute('href');
            link.classList.add('centinela-download-guard-done');
            link.innerHTML =
            '<span style="white-space:nowrap">' +
                '<i class="voyager-check" aria-hidden="true"></i> Archivo descargado</span>';

            if (!centinelaDownloadLinkLocksUntilModalClose(link)) {
            window.setTimeout(function() {
            if (link.dataset.centinelaDownloadCompleted === '1') {
            restoreCentinelaDownloadLink(link);
            }
            }, 5000);
            }
            }

            function showCentinelaDownloadError(link, response) {
            var message = 'No se pudo descargar el archivo. Intente nuevamente.';
            if (response && response.status === 429) {
            message = 'Demasiadas descargas. Espere un minuto e intente de nuevo.';
            }
            link.innerHTML = '<span class="text-danger" style="white-space:nowrap">' + message + '</span>';
            window.setTimeout(function() {
            restoreCentinelaDownloadLink(link);
            }, 4000);
            }

            function restoreCentinelaDownloadLink(link) {
            link.dataset.centinelaDownloading = '0';
            link.dataset.centinelaDownloadCompleted = '0';
            link.style.pointerEvents = '';
            link.removeAttribute('aria-disabled');
            link.classList.remove('centinela-download-guard-done');
            if (link.dataset.centinelaDownloadHref) {
            link.setAttribute('href', link.dataset.centinelaDownloadHref);
            }
            if (link.dataset.centinelaDownloadOriginalHtml) {
            link.innerHTML = link.dataset.centinelaDownloadOriginalHtml;
            }
            }

            function centinelaDownloadFilenameFromDisposition(contentDisposition, fallbackName) {
            if (!contentDisposition) {
            return fallbackName;
            }

            var match = /filename\*?=(?:UTF-8''|")?([^";\n]+)"?/i.exec(contentDisposition);

            if (match && match[1]) {
            try {
            return decodeURIComponent(match[1].trim());
            } catch (error) {
            return match[1].trim();
            }
            }

            return fallbackName;
            }

            function triggerCentinelaBlobDownload(blob, filename) {
            var blobUrl = window.URL.createObjectURL(blob);
            var anchor = document.createElement('a');
            anchor.href = blobUrl;
            anchor.download = filename;
            anchor.style.display = 'none';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.setTimeout(function() {
            window.URL.revokeObjectURL(blobUrl);
            }, 1000);
            }

            function startCentinelaGuardedDownload(link, fallbackFilename) {
            var url = link.dataset.centinelaDownloadHref || link.getAttribute('href');

            if (!url) {
            return;
            }

            markCentinelaDownloadLoading(link);

            window.fetch(url, {
            credentials: 'same-origin',
            headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/octet-stream,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
            }).then(function(response) {
            if (!response.ok) {
            var failedResponse = response;
            throw failedResponse;
            }

            var disposition = response.headers.get('Content-Disposition') || '';

            return response.blob().then(function(blob) {
            return {
            blob: blob,
            filename: centinelaDownloadFilenameFromDisposition(disposition, fallbackFilename),
            };
            });
            }).then(function(result) {
            triggerCentinelaBlobDownload(result.blob, result.filename);
            markCentinelaDownloadCompleted(link);
            }).catch(function(error) {
            var response = error && typeof error.status === 'number' ? error : null;
            showCentinelaDownloadError(link, response);
            });
            }

            function attachCentinelaDownloadGuards(root) {
            if (!root) {
            return;
            }

            root.querySelectorAll('a.centinela-download-guard-link').forEach(function(link) {
            if (link.dataset.centinelaDownloadGuard === '1') {
            return;
            }

            link.dataset.centinelaDownloadGuard = '1';
            link.addEventListener('click', function(event) {
            if (link.dataset.centinelaDownloadCompleted === '1' || link.dataset.centinelaDownloading === '1') {
            event.preventDefault();

            return;
            }

            event.preventDefault();
            var fallback = link.dataset.centinelaDownloadFallback || 'archivo-descargado';
            startCentinelaGuardedDownload(link, fallback);
            });
            });
            }

            function resetCentinelaDownloadGuards(root) {
            if (!root) {
            return;
            }

            root.querySelectorAll('a.centinela-download-guard-link').forEach(function(link) {
            link.dataset.centinelaDownloadGuard = '0';
            restoreCentinelaDownloadLink(link);
            });
            }
