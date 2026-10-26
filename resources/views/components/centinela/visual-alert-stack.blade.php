@props(['alerts' => [], 'display' => 'banner', 'variant' => 'inline'])

@php
    $alertCount = count($alerts);
    $stackClass =
        $variant === 'corner' ? 'centinela-visual-alert-stack--corner' : 'centinela-visual-alert-stack--inline';
    $menuId = 'centinela-alert-menu-' . md5(json_encode(array_map(fn($a) => $a->id, $alerts)));
@endphp

@if ($alertCount > 0)
    @if ($display === 'icon')
        @if ($alertCount === 1)
            <span class="centinela-visual-alert-stack centinela-visual-alert-stack--icon {{ $stackClass }}"
                role="group" aria-label="1 alerta">
                <x-centinela.visual-alert :alert="$alerts[0]" :display="$display" :variant="$variant" />
            </span>
        @else
            <span
                class="dropdown centinela-visual-alert-dropdown {{ $variant === 'corner' ? 'centinela-visual-alert-dropdown--corner' : '' }}"
                role="group" aria-label="{{ $alertCount }} alertas">
                <button type="button" class="centinela-visual-alert-trigger dropdown-toggle" data-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false" aria-controls="{{ $menuId }}"
                    aria-label="{{ $alertCount }} alertas activas, abrir listado">
                    <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"></span>
                    <span class="centinela-visual-alert-count badge">{{ $alertCount }}</span>
                </button>
                <ul id="{{ $menuId }}" class="dropdown-menu dropdown-menu-right centinela-visual-alert-menu"
                    role="menu">
                    @foreach ($alerts as $alert)
                        @php
                            $itemIcon = match ($alert->severity->value) {
                                'success' => 'glyphicon-ok-sign text-success',
                                'info' => 'glyphicon-info-sign text-info',
                                default => 'glyphicon-exclamation-sign text-warning',
                            };
                        @endphp
                        <li role="presentation">
                            <span class="centinela-visual-alert-menu-item" role="menuitem">
                                <span class="glyphicon {{ $itemIcon }}" aria-hidden="true"></span>
                                <span class="centinela-visual-alert-menu-text">
                                    <strong>{{ $alert->title }}</strong>
                                    @if ($alert->detail)
                                        <span class="text-muted"> — {{ $alert->detail }}</span>
                                    @endif
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </span>
        @endif
    @else
        <div class="centinela-visual-alert-stack centinela-visual-alert-stack--{{ $display }}">
            @foreach ($alerts as $alert)
                <x-centinela.visual-alert :alert="$alert" :display="$display" :variant="$variant" />
            @endforeach
        </div>
    @endif

    @once
        <style>
            .centinela-visual-alert {
                border-left: 4px solid #f0ad4e;
                padding: 0.35rem 0.35rem 0.35rem 0.5rem;
                margin: 0.35rem 0 0;
                background-color: #fcf8f3;
            }

            .centinela-visual-alert .glyphicon {
                margin-right: 0.35rem;
            }

            .centinela-visual-alert--success {
                border-left-color: #5cb85c;
                background-color: #f4fdf4;
            }

            .centinela-visual-alert--info {
                border-left-color: #5bc0de;
                background-color: #f4f9fc;
            }

            .centinela-visual-alert-stack--icon {
                display: inline-flex;
                align-items: center;
                flex-shrink: 0;
                gap: 0.3rem;
                margin-left: 0.45rem;
                vertical-align: middle;
            }

            .centinela-visual-alert-stack--icon.centinela-visual-alert-stack--corner,
            .centinela-visual-alert-dropdown--corner {
                position: absolute;
                top: -0.35rem;
                right: -0.5rem;
                margin: 0;
                z-index: 2;
            }

            .centinela-semaforo-with-alerts {
                position: relative;
                display: inline-block;
                vertical-align: middle;
            }

            .centinela-visual-alert-icon,
            .centinela-visual-alert-trigger {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: help;
                line-height: 1;
                vertical-align: middle;
                padding: 0;
                border: none;
                background: transparent;
            }

            .centinela-visual-alert-icon--corner,
            .centinela-visual-alert-dropdown--corner .centinela-visual-alert-trigger {
                background: #fff;
                border-radius: 50%;
                box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.12);
            }

            .centinela-visual-alert-icon .glyphicon,
            .centinela-visual-alert-trigger>.glyphicon {
                font-size: 1.5em;
                top: 0;
                line-height: 1;
            }

            .centinela-visual-alert-dropdown--corner .centinela-visual-alert-trigger>.glyphicon {
                font-size: 1.1em;
            }

            .centinela-visual-alert-dropdown--corner .caret {
                display: none;
            }

            .centinela-visual-alert-count.badge {
                position: absolute;
                top: -0.4rem;
                right: -0.45rem;
                font-size: 9px;
                min-width: 14px;
                padding: 2px 4px;
                background-color: #f0ad4e;
            }

            .centinela-visual-alert-menu {
                min-width: 16rem;
                max-width: 22rem;
                padding: 0.35rem 0;
            }

            .centinela-visual-alert-menu-item {
                display: flex;
                align-items: flex-start;
                gap: 0.45rem;
                padding: 0.45rem 1rem;
                white-space: normal;
                cursor: default;
            }

            .centinela-visual-alert-menu-item .glyphicon {
                flex-shrink: 0;
                margin-top: 0.15rem;
            }

            .centinela-visual-alert-menu-text {
                flex: 1 1 auto;
                line-height: 1.35;
            }

            .centinela-table-cell-inline {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                flex-wrap: nowrap;
                gap: 0.35rem;
            }

            .centinela-table-cell-inline__body {
                flex: 0 1 auto;
                min-width: 0;
            }

            .centinela-table-cell-inline--score {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-wrap: nowrap;
                gap: 0;
                width: max-content;
                max-width: 100%;
                margin-inline: auto;
            }

            .centinela-table-cell-inline--score .semaforo {
                display: inline-block;
                flex: 0 0 auto;
                margin: 0;
                vertical-align: middle;
            }

            .centinela-table-cell-inline__body .semaforo {
                display: inline-block;
                margin: 0;
                vertical-align: middle;
            }

            .table-results td:has(.centinela-table-cell-inline--score) {
                text-align: center;
            }
        </style>

        @push('javascript')
            <script>
                (function($) {
                    'use strict';

                    function centinelaInitVisualAlertTooltips(root) {
                        if (typeof $ === 'undefined' || !$.fn.tooltip) {
                            return;
                        }

                        var $scope = root ? $(root) : $(document.body);

                        $scope.find('[data-centinela-visual-alert-tooltip]').each(function() {
                            var $el = $(this);

                            if ($el.data('bs.tooltip')) {
                                $el.tooltip('destroy');
                            }

                            $el.tooltip({
                                container: 'body',
                                placement: $el.data('placement') || 'top',
                            });
                        });
                    }

                    $(function() {
                        centinelaInitVisualAlertTooltips();
                    });

                    document.addEventListener('livewire:init', function() {
                        Livewire.hook('morph.updated', function(payload) {
                            if (payload.el) {
                                centinelaInitVisualAlertTooltips(payload.el);
                            }
                        });
                    });
                })
                (window.jQuery);
            </script>
        @endpush
    @endonce
@endif
