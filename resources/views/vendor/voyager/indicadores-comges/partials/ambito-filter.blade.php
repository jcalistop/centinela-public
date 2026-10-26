<form method="get" class="form-inline" style="margin-bottom: 1rem;">
    <label for="ambito_filter" class="control-label" style="margin-right: 8px;">Ámbito</label>
    <select name="ambito_filter" id="ambito_filter" class="form-control" onchange="this.form.submit()">
        <option value="">Todos los ámbitos</option>
        <option value="none" @selected(($ambitoFilter ?? '') === 'none')>Sin ámbito</option>
        @foreach ($indicatorAmbitoOptions ?? [] as $ambito)
            <option value="{{ $ambito->id }}" @selected((string) ($ambitoFilter ?? '') === (string) $ambito->id)>
                {{ $ambito->nombre }}
            </option>
        @endforeach
    </select>
</form>
