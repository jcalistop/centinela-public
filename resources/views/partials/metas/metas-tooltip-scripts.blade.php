{{-- Tooltips Bootstrap para marcadores `data-metas-tooltip`; se reinician tras cada morph de Livewire. --}}
<script>
    (function($) {
        'use strict';

        function metasInitTooltips(root) {
            if (typeof $ === 'undefined' || !$.fn.tooltip) {
                return;
            }

            var $scope = root ? $(root) : $(document.body);

            $scope.find('[data-metas-tooltip]').each(function() {
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
            metasInitTooltips();
        });

        document.addEventListener('livewire:init', function() {
            Livewire.hook('morph.updated', function(payload) {
                if (payload.el) {
                    metasInitTooltips(payload.el);
                }
            });
        });
    })(window.jQuery);
</script>
