@php
    /** @var \App\Models\User|null $authUser */
    $authUser = Auth::user();
    $canView = $authUser instanceof \App\Models\User && $authUser->hasRole('admin');
@endphp

@if ($canView)
    <div class="admin-dashboard-compass admin-dashboard-compass--deploy cen-compass-ui">
        <div class="cen-dashboard-shell">
            @livewire(\App\Livewire\Admin\DeployDashboard::class, ['defer' => true])
        </div>
    </div>
@endif
