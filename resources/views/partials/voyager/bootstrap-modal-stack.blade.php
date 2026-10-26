{{-- SDD-143 / SDD-260: Bootstrap 3 modal stack — evita quedar bajo .modal-backdrop (.side-body z-index 2) --}}
@php
    /** @var array<int, string> $modalIds */
    $modalIds = array_values(array_filter($modalIds ?? [], static fn($id): bool => is_string($id) && $id !== ''));
    $autoRegisterAll = (bool) ($autoRegisterAll ?? false);
@endphp

@if (count($modalIds) > 0 || $autoRegisterAll)
    @once('centinela-bootstrap-modal-stack-init')
        @push('javascript')
            <script>
                (function($) {
                    window.__centinelaBootstrapModalIds = window.__centinelaBootstrapModalIds || new Set();
                    window.__centinelaBootstrapModalAutoRegister = window.__centinelaBootstrapModalAutoRegister || false;

                    function centinelaBootstrapModalSelector() {
                        return Array.from(window.__centinelaBootstrapModalIds).join(', ');
                    }

                    function ensureCentinelaBootstrapModalsInBody() {
                        var selector = centinelaBootstrapModalSelector();
                        if (!selector) {
                            return;
                        }

                        $(selector).each(function() {
                            if (this.parentElement !== document.body) {
                                document.body.appendChild(this);
                            }
                        });
                    }

                    function syncCentinelaBootstrapModalBodyClass() {
                        var selector = centinelaBootstrapModalSelector();
                        if (selector && $(selector).filter('.in').length) {
                            document.body.classList.add('centinela-bootstrap-modal-open');
                        } else {
                            document.body.classList.remove('centinela-bootstrap-modal-open');
                        }
                    }

                    function registerCentinelaBootstrapModal(modalId) {
                        if (!modalId || modalId === '#') {
                            return;
                        }

                        window.__centinelaBootstrapModalIds.add(modalId);
                    }

                    function registerAllCentinelaBootstrapModals() {
                        $('.modal[id]').each(function() {
                            registerCentinelaBootstrapModal('#' + this.id);
                        });
                    }

                    $(document).on('show.bs.modal', function(event) {
                        var modalId = event.target && event.target.id ? '#' + event.target.id : '';

                        if (window.__centinelaBootstrapModalAutoRegister && modalId) {
                            registerCentinelaBootstrapModal(modalId);
                        }

                        if (!modalId || !window.__centinelaBootstrapModalIds.has(modalId)) {
                            return;
                        }

                        ensureCentinelaBootstrapModalsInBody();
                        document.body.classList.add('centinela-bootstrap-modal-open');
                    });

                    $(document).on('hidden.bs.modal', function(event) {
                        var modalId = event.target && event.target.id ? '#' + event.target.id : '';
                        if (!modalId || !window.__centinelaBootstrapModalIds.has(modalId)) {
                            return;
                        }

                        syncCentinelaBootstrapModalBodyClass();
                    });

                    $(document).ready(function() {
                        if (window.__centinelaBootstrapModalAutoRegister) {
                            registerAllCentinelaBootstrapModals();
                        }

                        ensureCentinelaBootstrapModalsInBody();
                    });
                })(jQuery);
            </script>
        @endpush
    @endonce

    @push('javascript')
        <script>
            (function() {
                window.__centinelaBootstrapModalIds = window.__centinelaBootstrapModalIds || new Set();
                @if ($autoRegisterAll)
                    window.__centinelaBootstrapModalAutoRegister = true;
                @endif
                @foreach ($modalIds as $modalId)
                    window.__centinelaBootstrapModalIds.add(@json($modalId));
                @endforeach
            })();
        </script>
    @endpush
@endif
