@props(['icon'])

<h1 {{ $attributes->merge(['class' => 'page-title']) }}>
    <i class="{{ $icon }}"></i>
    {{ $slot }}
</h1>
