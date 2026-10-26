@props([
    'title',
    'description',
])

<span {{ $attributes->class(['label', 'label-warning', 'metas-catalog-alert-tag']) }}
    title="{{ $description }}">
    <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
    <strong>{{ $title }}</strong>
</span>
