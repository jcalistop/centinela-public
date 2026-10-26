{{-- Evita que la rueda del mouse cambie el valor de inputs type="number" (comportamiento nativo en varios navegadores). Trade-off: con el puntero encima del control, el scroll de página puede no avanzar al usar la rueda sobre ese elemento. Ver SDD-060 / ADR-031. --}}
<script>
    (function() {
        'use strict';
        document.addEventListener(
            'wheel',
            function(e) {
                const t = e.target;
                if (t instanceof HTMLInputElement && t.type === 'number') {
                    e.preventDefault();
                }
            }, {
                capture: true,
                passive: false,
            });
    })();
</script>
