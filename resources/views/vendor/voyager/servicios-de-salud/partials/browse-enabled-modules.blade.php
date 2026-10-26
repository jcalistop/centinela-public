@php
    use App\Models\CenHieDependency;
    use App\Models\ServiceModuleToggle;

    $ss = isset($data) && $data instanceof CenHieDependency ? $data : null;

    if ($ss === null && isset($dataTypeContent) && $dataTypeContent instanceof CenHieDependency) {
        $ss = $dataTypeContent;
    }

    $modules = $ss ? $ss->enabledServiceModuleToggles()->orderBy('module_key')->get() : collect();
@endphp

@if ($modules->isEmpty())
    <p>{{ __('voyager::generic.no_results') }}</p>
@else
    <p>
        @foreach ($modules as $toggle)
            <span class="badge badge-primary">{{ ServiceModuleToggle::moduleLabel($toggle->module_key) }}</span>
        @endforeach
    </p>
@endif
