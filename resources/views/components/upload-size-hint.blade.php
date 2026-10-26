@props([
    'kilobytes' => null,
    'label' => null,
])

@php
    use App\Support\Upload\UploadSizeLimit;

    $maxKb = $kilobytes ?? UploadSizeLimit::maxKilobytes();
    $hint = $label ?? 'Tamaño máximo: ' . UploadSizeLimit::humanLabelForKilobytes((int) $maxKb) . ' por archivo';
@endphp

<p {{ $attributes->merge(['class' => 'help-block text-muted upload-size-hint']) }}>
    {{ $hint }}
</p>
