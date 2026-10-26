@once('centinela-document-pdf-preview-modal')
    <div class="modal fade" id="centinelaDocumentPdfModal" tabindex="-1" role="dialog"
        aria-labelledby="centinelaDocumentPdfModalLabel">
        <div class="modal-dialog modal-lg" role="document" style="width: 92%; max-width: 1100px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="centinelaDocumentPdfModalLabel">Documento PDF</h4>
                </div>
                <div class="modal-body" style="padding: 0;">
                    <iframe id="centinelaDocumentPdfFrame" title="Vista previa PDF"
                        style="border: 0; width: 100%; min-height: 75vh;" src=""></iframe>
                </div>
            </div>
        </div>
    </div>

    @include('partials.voyager.bootstrap-modal-stack', ['modalIds' => ['#centinelaDocumentPdfModal']])

    @push('javascript')
        <script>
            (function($) {
                $(document).on('click', '.centinela-pdf-preview-trigger', function(event) {
                    event.preventDefault();

                    var url = $(this).data('pdf-url');
                    if (!url) {
                        return;
                    }

                    var title = $(this).data('pdf-title') || 'Documento PDF';
                    $('#centinelaDocumentPdfModalLabel').text(title);
                    $('#centinelaDocumentPdfFrame').attr('src', url);
                    $('#centinelaDocumentPdfModal').modal('show');
                });

                $('#centinelaDocumentPdfModal').on('hidden.bs.modal', function() {
                    $('#centinelaDocumentPdfFrame').attr('src', '');
                });
            })(jQuery);
        </script>
    @endpush
@endonce
