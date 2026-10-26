@php
    use App\Models\User;
    use App\Support\Configuration\AdminBackgroundImage;

    $user = filament()->auth()->user();
    $name = filled($user) ? trim((string) filament()->getUserName($user)) : '';
    $email = filled($user) ? trim((string) ($user->email ?? '')) : '';
    $avatar = $user instanceof User ? (string) ($user->getFilamentAvatarUrl() ?? '') : '';

    $backgroundUrl = AdminBackgroundImage::cachedUrl();
@endphp

@if ($name !== '' || $email !== '')
    <div class="cen-sidebar-session"
        @if ($backgroundUrl !== '') style="background-image: url('{{ $backgroundUrl }}')" @endif>
        <div class="cen-sidebar-session__dimmer"></div>
        <div class="cen-sidebar-session__content">
            @if ($avatar !== '')
                <img src="{{ $avatar }}" alt="" class="cen-sidebar-session__avatar">
            @endif
            <div class="cen-sidebar-session__text">
                @if ($name !== '')
                    <p class="cen-sidebar-session__name">{{ $name }}</p>
                @endif
                @if ($email !== '')
                    <p class="cen-sidebar-session__email">{{ $email }}</p>
                @endif
            </div>
        </div>
    </div>
@endif
