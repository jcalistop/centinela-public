@php
    $helpText = $text ?? '';
    $placement = $placement ?? 'top';
@endphp
@if ($helpText !== '')
    <span class="cen-help-icon" tabindex="0" role="button" data-toggle="tooltip" data-placement="{{ $placement }}"
        title="{{ $helpText }}" aria-label="Ayuda">?</span>
@endif
