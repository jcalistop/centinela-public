@php
    /** @var \App\Models\CenHieDependency $data */
    $url = $data->logoPublicUrl();
    $hasCustom = is_string($data->logo) && trim($data->logo) !== '';
    $alt = (string) ($data->nombre ?? 'Logo');
@endphp

<img src="{{ $url }}" alt="{{ $alt }}"
    title="{{ $hasCustom ? 'Logo del servicio' : 'Logo por defecto' }}"
    style="width:40px;height:40px;object-fit:contain;border-radius:4px;background:#f4f6f9;padding:2px;">
