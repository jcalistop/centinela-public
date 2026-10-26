@php
    use App\Support\Upload\UploadSizeLimit;
@endphp
<script>
    document.addEventListener('change', function(event) {
        const input = event.target;
        if (!input || input.tagName !== 'INPUT' || input.type !== 'file') {
            return;
        }

        const maxBytes = parseInt(input.getAttribute('data-max-upload-bytes') || '', 10);
        if (!maxBytes || !input.files || input.files.length === 0) {
            return;
        }

        const oversize = Array.from(input.files).find(function(file) {
            return file.size > maxBytes;
        });

        if (!oversize) {
            return;
        }

        input.value = '';
        const limitLabel = input.getAttribute('data-max-upload-label') || '{{ UploadSizeLimit::humanLabel() }}';
        const message = 'El archivo «' + oversize.name + '» supera el tamaño máximo permitido (' + limitLabel +
            ').';

        if (typeof toastr !== 'undefined') {
            toastr.error(message);
        } else {
            alert(message);
        }
    }, true);
</script>
