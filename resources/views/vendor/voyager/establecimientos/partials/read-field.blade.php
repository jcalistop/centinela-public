@php
    use App\Support\Voyager\VoyagerBackedEnumBreadForm;
@endphp
<div class="panel-heading" style="border-bottom:0;">
    <h3 class="panel-title">{{ $label }}</h3>
</div>
<div class="panel-body" style="padding-top:0;">
    <p>{{ VoyagerBackedEnumBreadForm::displayLabel($value ?? null) }}</p>
</div>
<hr style="margin:0;">
