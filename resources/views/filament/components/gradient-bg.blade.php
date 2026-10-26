@php
    use App\Support\Configuration\AdminBackgroundImage;

    $backgroundUrl = AdminBackgroundImage::cachedUrl();
@endphp

@if ($backgroundUrl !== '')
    <div class="cen-gradient-bg" style="background-image: url('{{ $backgroundUrl }}')" aria-hidden="true"></div>
@endif
