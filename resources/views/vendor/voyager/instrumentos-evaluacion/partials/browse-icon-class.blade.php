@if ($content)
    <span class="it-browse-icon" title="{{ $content }}">
        <i class="{{ $content }}" style="font-size:20px; vertical-align:middle" aria-hidden="true"></i>
    </span>
@else
    <span class="text-muted">—</span>
@endif
