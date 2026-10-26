<div class="alert alert-danger validation-error">
    {{ __('voyager::json.invalid') }}
</div>
<div>
    <pre id="json-input-{{ $row->field }}">{{ old($row->field, $dataTypeContent->{$row->field} ?? '{}') }}</pre>
    <input type="hidden" name="{{ $row->field }}" id="hidden-{{ $row->field }}">
</div>
@push('javascript')
    <script>
        var editor = ace.edit("json-input-{{ $row->field }}");
        editor.renderer.setScrollMargin(10, 10, 10, 10);
        editor.session.setMode("ace/mode/json");
        editor.session.setUseWorker(true);
        editor.setAutoScrollEditorIntoView(true);
        editor.setOption("maxLines", 30);
        editor.setOption("minLines", 4);
        editor.setOption("showLineNumbers", false);
        editor.setTheme("ace/theme/github");

        var validationAlert = document.querySelector('.validation-error');
        validationAlert.style.display = 'none';
        var valid = false;

        function syncHiddenInput() {
            document.getElementById('hidden-{{ $row->field }}').value = editor.getValue();
        }
        syncHiddenInput();
        editor.session.on('change', syncHiddenInput);

        editor.session.on("changeAnnotation", function() {
            valid = editor.session.getAnnotations().length === 0;
            validationAlert.style.display = valid ? 'none' : '';
        });

        var form = document.getElementById('json-input-{{ $row->field }}').closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                syncHiddenInput();
                if (!valid) {
                    e.preventDefault();
                    validationAlert.style.display = '';
                }
            });
        }
    </script>
@endpush

{{--

{
    "asdfasdfasd": "asdlfkasdlñfk",
    "test":
    {"prueba": 123}
}

--}}
