@extends('voyager::master')

@section('page_title', 'Ordenar estructura COMGES')

@section('content')
    <div class="page-content compass container-fluid cen-compass-ui comges-structure-order-page">
        @include('voyager::alerts')

        <h1 class="page-title">
            <i class="voyager-list"></i> Ordenar estructura COMGES
        </h1>

        <div class="comges-structure-order-toolbar">
            <a href="{{ route('voyager.compromisos.index') }}" class="btn btn-success btn-sm">
                <i class="voyager-angle-left"></i> Volver a compromisos
            </a>
            <button type="button" class="btn btn-default btn-sm" id="comges-structure-expand-all">
                <i class="voyager-angle-down"></i> Expandir todos
            </button>
            <button type="button" class="btn btn-default btn-sm" id="comges-structure-collapse-all">
                <i class="voyager-angle-right"></i> Colapsar todos
            </button>
        </div>

        @livewire('comges.structure-order-component')
    </div>
@endsection

@push('javascript')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        (function() {
            const SCROLL_EDGE_PX = 80;
            const SCROLL_MAX_SPEED = 22;

            let edgeScrollFrame = null;
            let lastPointerY = 0;
            let isDraggingStructure = false;

            function resolveStructureOrderComponent() {
                const root = document.getElementById('comges-structure-sort-root');

                if (!root) {
                    return null;
                }

                const wireRoot = root.closest('[wire\\:id]');

                if (!wireRoot) {
                    return null;
                }

                const wireId = wireRoot.getAttribute('wire:id');

                return wireId && window.Livewire ? window.Livewire.find(wireId) : null;
            }

            function scrollableAncestors() {
                const ancestors = [];
                const sideBody = document.querySelector('.voyager .side-body');

                if (sideBody instanceof HTMLElement) {
                    ancestors.push(sideBody);
                }

                ancestors.push(document.scrollingElement || document.documentElement);

                return ancestors.filter((element, index, list) => element && list.indexOf(element) === index);
            }

            function trackPointer(event) {
                if (event.touches && event.touches.length > 0) {
                    lastPointerY = event.touches[0].clientY;
                } else if (typeof event.clientY === 'number') {
                    lastPointerY = event.clientY;
                }
            }

            function tickEdgeScroll() {
                if (!isDraggingStructure) {
                    edgeScrollFrame = null;

                    return;
                }

                const viewportHeight = window.innerHeight;
                let scrollDelta = 0;

                if (lastPointerY < SCROLL_EDGE_PX) {
                    scrollDelta = -Math.ceil(((SCROLL_EDGE_PX - lastPointerY) / SCROLL_EDGE_PX) * SCROLL_MAX_SPEED);
                } else if (lastPointerY > viewportHeight - SCROLL_EDGE_PX) {
                    scrollDelta = Math.ceil(((lastPointerY - (viewportHeight - SCROLL_EDGE_PX)) / SCROLL_EDGE_PX) *
                        SCROLL_MAX_SPEED);
                }

                if (scrollDelta !== 0) {
                    scrollableAncestors().forEach((element) => {
                        element.scrollTop += scrollDelta;
                    });
                    window.scrollBy(0, scrollDelta);
                }

                edgeScrollFrame = requestAnimationFrame(tickEdgeScroll);
            }

            function startEdgeAutoScroll() {
                isDraggingStructure = true;
                document.body.classList.add('comges-structure-sorting');
                document.addEventListener('mousemove', trackPointer);
                document.addEventListener('touchmove', trackPointer, {
                    passive: true,
                });

                if (!edgeScrollFrame) {
                    edgeScrollFrame = requestAnimationFrame(tickEdgeScroll);
                }
            }

            function stopEdgeAutoScroll() {
                isDraggingStructure = false;
                document.body.classList.remove('comges-structure-sorting');
                document.removeEventListener('mousemove', trackPointer);
                document.removeEventListener('touchmove', trackPointer);

                if (edgeScrollFrame) {
                    cancelAnimationFrame(edgeScrollFrame);
                    edgeScrollFrame = null;
                }
            }

            function persistStructureOrder(root, component) {
                const commitments = [];

                root.querySelectorAll('.comges-structure-sort-list').forEach((container) => {
                    const commitmentId = parseInt(container.dataset.commitmentId, 10);
                    const indicatorIds = [...container.querySelectorAll('.comges-structure-sort-item')]
                        .map((item) => parseInt(item.dataset.id, 10))
                        .filter((id) => !Number.isNaN(id));

                    commitments.push({
                        id: commitmentId,
                        indicator_ids: indicatorIds,
                    });
                });

                syncIndicatorCounts(root);

                component.call('applyStructure', {
                    commitments: commitments,
                });
            }

            function syncIndicatorCounts(root) {
                root.querySelectorAll('.comges-structure-commitment-panel').forEach((panel) => {
                    const count = panel.querySelectorAll('.comges-structure-sort-item').length;
                    const badge = panel.querySelector('.comges-structure-indicator-count');

                    if (badge) {
                        badge.textContent = count + ' indicador' + (count === 1 ? '' : 'es');
                    }
                });
            }

            function bindCollapseHelpers(root) {
                if (root._comgesCollapseBound) {
                    return;
                }

                root._comgesCollapseBound = true;

                root.addEventListener('mouseover', (event) => {
                    if (!isDraggingStructure) {
                        return;
                    }

                    const panel = event.target.closest('.comges-structure-commitment-panel');

                    if (!panel) {
                        return;
                    }

                    const collapseEl = panel.querySelector('.panel-collapse');
                    const $ = window.jQuery;

                    if (collapseEl && $ && $.fn.collapse && !collapseEl.classList.contains('in')) {
                        $(collapseEl).collapse('show');
                    }

                    panel.classList.add('comges-structure-drop-target');
                });

                root.addEventListener('mouseout', (event) => {
                    const panel = event.target.closest('.comges-structure-commitment-panel');
                    const relatedPanel = event.relatedTarget && event.relatedTarget.closest ?
                        event.relatedTarget.closest('.comges-structure-commitment-panel') :
                        null;

                    if (panel && panel !== relatedPanel) {
                        panel.classList.remove('comges-structure-drop-target');
                    }
                });
            }

            function bindToolbarCollapseControls() {
                if (window._comgesStructureToolbarBound) {
                    return;
                }

                window._comgesStructureToolbarBound = true;

                const root = document.getElementById('comges-structure-sort-root');
                const $ = window.jQuery;

                document.getElementById('comges-structure-expand-all')?.addEventListener('click', () => {
                    if (!root || !$ || !$.fn.collapse) {
                        return;
                    }

                    $(root).find('.panel-collapse').collapse('show');
                });

                document.getElementById('comges-structure-collapse-all')?.addEventListener('click', () => {
                    if (!root || !$ || !$.fn.collapse) {
                        return;
                    }

                    $(root).find('.panel-collapse').collapse('hide');
                });
            }

            window.initComgesStructureOrder = function initComgesStructureOrder(attempt = 0) {
                const root = document.getElementById('comges-structure-sort-root');
                const component = resolveStructureOrderComponent();

                if (!root || typeof window.Sortable !== 'function' || !component) {
                    if (attempt < 80) {
                        setTimeout(() => window.initComgesStructureOrder(attempt + 1), 50);
                    }

                    return;
                }

                bindCollapseHelpers(root);
                bindToolbarCollapseControls();

                root.querySelectorAll('.comges-structure-sort-list').forEach((list) => {
                    if (list._comgesSortable) {
                        list._comgesSortable.destroy();
                    }

                    list._comgesSortable = new Sortable(list, {
                        group: 'comges-structure-indicators',
                        animation: 150,
                        draggable: '.comges-structure-sort-item',
                        ghostClass: 'comges-structure-sort-ghost',
                        scroll: scrollableAncestors()[0] || true,
                        forceAutoScrollFallback: true,
                        bubbleScroll: true,
                        scrollSensitivity: SCROLL_EDGE_PX,
                        scrollSpeed: SCROLL_MAX_SPEED,
                        onStart: (event) => {
                            trackPointer(event.originalEvent || event);
                            startEdgeAutoScroll();
                        },
                        onEnd: () => {
                            stopEdgeAutoScroll();
                            root.querySelectorAll('.comges-structure-drop-target').forEach((
                                panel) => {
                                panel.classList.remove('comges-structure-drop-target');
                            });
                            persistStructureOrder(root, component);
                        },
                    });
                });
            };

            document.addEventListener('livewire:init', () => {
                window.initComgesStructureOrder();

                if (window.Livewire) {
                    Livewire.on('structure-order-saved', () => {
                        if (typeof toastr !== 'undefined') {
                            toastr.success(@json(__('voyager::bread.updated_order')));
                        }
                    });
                }
            });

            document.addEventListener('DOMContentLoaded', () => {
                window.initComgesStructureOrder();
            });
        })();
    </script>
@endpush
