<div>
    {{-- Estilos compass + toggle inline (voyager::master usa @yield('css'), no @stack) --}}
    <style>
        .voyager .compass .nav-tabs {
            background: none;
            border-bottom: 0;
        }

        .voyager .compass .nav-tabs>li {
            margin-bottom: -1px !important;
        }

        .voyager .compass .nav-tabs a {
            text-align: center;
            font-size: 10px;
            font-weight: normal;
            background: #f8f8f8;
            border: 1px solid #f1f1f1;
            position: relative;
            top: -1px;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .voyager .compass .nav-tabs a i {
            display: block;
            font-size: 22px;
        }

        .tab-content {
            background: #fff;
            border: 1px solid transparent;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
        }

        .nav-tabs>li.active>a,
        .nav-tabs>li.active>a:focus,
        .nav-tabs>li.active>a:hover {
            background: #fff !important;
            color: #62a8ea !important;
            border-bottom: 1px solid #fff !important;
            top: -1px !important;
        }

        .voyager .compass .nav-tabs>li>a:hover {
            background-color: #fff !important;
        }

        .switch-toggle {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            vertical-align: middle;
            margin-right: 8px;
        }

        .switch-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .switch-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: #fff;
            transition: .4s;
            border-radius: 50%;
        }

        .switch-toggle input:checked+.switch-slider {
            background-color: #22A7F0;
        }

        .switch-toggle input:checked+.switch-slider:before {
            transform: translateX(26px);
        }

        .switch-label {
            vertical-align: middle;
            font-size: 14px;
            color: #555;
        }

        .compass .control-label {
            font-weight: 600;
            margin-bottom: .5rem;
            display: block;
        }

        .compass .form-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .compass .form-group:last-child {
            border-bottom: none;
        }

        .lw-saving {
            opacity: .65;
        }

        .lw-saving input,
        .lw-saving select,
        .lw-saving textarea,
        .lw-saving button,
        .lw-saving a,
        .lw-saving label {
            pointer-events: none;
        }

        .lw-spin {
            display: inline-block;
            animation: lw-spin 1s linear infinite;
        }

        @keyframes lw-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .setting-media-preview-wrap {
            margin-bottom: 10px;
        }

        .setting-media-preview {
            display: block;
            max-width: 140px;
            max-height: 140px;
            width: auto;
            height: auto;
            object-fit: contain;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 4px;
            background: #fafafa;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
        }

        .setting-media-size-hint {
            font-size: 12px;
            color: #777;
            margin: 8px 0 10px;
            line-height: 1.45;
        }

        .setting-media-size-hint .voyager-info-circled {
            color: #62a8ea;
            margin-right: 4px;
        }
    </style>

    <div class="page-content compass container-fluid" wire:loading.class="lw-saving" wire:target="save">
        @php
            $tabIcons = [
                'general' => 'voyager-paint-bucket',
                'modulos' => 'voyager-power',
                'deploy' => 'voyager-upload',
                'notificaciones' => 'voyager-bell',
                'sistema' => 'voyager-params',
                'servicios-externos' => 'voyager-world',
            ];
            $groupToSlug = [];
            foreach ($orderedTabSlugs as $tab) {
                $groupToSlug[$tab['slug']] = $tab['group'];
            }
        @endphp

        {{-- Tabs controladas por Livewire via $activeTab --}}
        <ul class="nav nav-tabs" role="tablist" style="margin-bottom:0">
            <li role="presentation" class="{{ $activeTab === 'general' ? 'active' : '' }}">
                <a href="#tab-general" role="tab" wire:click.prevent="$set('activeTab', 'general')">
                    <i class="voyager-paint-bucket"></i> General
                </a>
            </li>
            @foreach ($groupToSlug as $slug => $groupName)
                <li role="presentation" class="{{ $activeTab === $slug ? 'active' : '' }}">
                    <a href="#tab-{{ $slug }}" role="tab"
                        wire:click.prevent="$set('activeTab', '{{ $slug }}')">
                        <i class="{{ $tabIcons[$slug] ?? 'voyager-dot' }}"></i> {{ $groupName }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="tab-content" style="padding-top:20px">
            {{-- Tab General (Admin) --}}
            <div role="tabpanel" class="tab-pane {{ $activeTab === 'general' ? 'active' : '' }}" id="tab-general">
                @if (isset($settingsByGroup['Admin']))
                    <x-panel-component col="12" panel="primary">
                        <x-slot name="title"><i class="voyager-paint-bucket"></i> Apariencia</x-slot>
                        <x-slot name="body">
                            @foreach ($settingsByGroup['Admin'] as $setting)
                                @php
                                    $flatKey = $setting['flat_key'];
                                    $details = $setting['details'];
                                @endphp
                                <div class="form-group" wire:key="setting-{{ $flatKey }}">
                                    <label class="control-label">
                                        {{ $setting['display_name'] }}
                                        <code style="font-weight: normal; font-size: 12px; margin-left: 6px;">
                                            {{ $setting['key'] }}
                                        </code>
                                    </label>
                                    @if (isset($details['description']) && $details['description'])
                                        <p class="help-block" style="font-size:12px;color:#777;margin:-4px 0 8px">
                                            {{ $details['description'] }}</p>
                                    @endif

                                    @if ($setting['type'] === 'radio_btn' && isset($details['options']))
                                        @php $optionKeys = array_keys($details['options']); @endphp
                                        @if (count($optionKeys) === 2 && in_array('On', $optionKeys) && in_array('Off', $optionKeys))
                                            <label class="switch-toggle">
                                                <input type="checkbox"
                                                    wire:click="$set('settings.{{ $flatKey }}', '{{ $this->settings[$flatKey] === 'On' ? 'Off' : 'On' }}')"
                                                    {{ $this->settings[$flatKey] === 'On' ? 'checked' : '' }}>
                                                <span class="switch-slider"></span>
                                            </label>
                                            <span
                                                class="switch-label">{{ $this->settings[$flatKey] === 'On' ? $details['options']['On'] ?? 'Activado' : $details['options']['Off'] ?? 'Desactivado' }}</span>
                                        @else
                                            <div class="btn-group" data-toggle="buttons">
                                                @foreach ($details['options'] as $optVal => $optLabel)
                                                    <label
                                                        class="btn btn-primary {{ $this->settings[$flatKey] === $optVal ? 'active' : '' }}"
                                                        wire:click="$set('settings.{{ $flatKey }}', '{{ $optVal }}')">
                                                        <input type="radio" name="s-{{ $flatKey }}"
                                                            value="{{ $optVal }}"
                                                            {{ $this->settings[$flatKey] === $optVal ? 'checked' : '' }}>
                                                        {{ $optLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    @elseif (in_array($setting['type'], ['image', 'file']))
                                        @include('livewire.configuracion.partials.setting-media-field', [
                                            'setting' => $setting,
                                        ])
                                    @else
                                        <input type="text" class="form-control"
                                            wire:model.lazy="settings.{{ $flatKey }}"
                                            placeholder="{{ $this->textPlaceholder($setting['key'], is_array($details) ? $details : null) }}"
                                            autocomplete="off">
                                    @endif
                                </div>
                            @endforeach
                        </x-slot>
                        <x-slot name="footer">
                            <button type="button" class="btn btn-primary" wire:click="save"
                                wire:loading.attr="disabled" wire:target="save,mediaUploads">
                                <i class="voyager-check" wire:loading.remove wire:target="save"></i>
                                <i class="voyager-refresh lw-spin" wire:loading wire:target="save"></i>
                                <span wire:loading.remove wire:target="save">Guardar configuracion</span>
                                <span wire:loading wire:target="save">Guardando...</span>
                            </button>
                        </x-slot>
                    </x-panel-component>
                @endif
            </div>

            {{-- Tabs dinamicas (Modulos, Notificaciones, Sistema, Servicios externos) --}}
            @foreach ($groupToSlug as $slug => $groupName)
                <div role="tabpanel" class="tab-pane {{ $activeTab === $slug ? 'active' : '' }}"
                    id="tab-{{ $slug }}">
                    @php $items = $settingsByGroup[$groupName] ?? []; @endphp
                    @if (!empty($items))
                        <x-panel-component col="12" panel="primary">
                            <x-slot name="title"><i class="{{ $tabIcons[$slug] ?? 'voyager-dot' }}"></i>
                                {{ $groupName }}</x-slot>
                            <x-slot name="body">
                                @if ($slug === 'deploy')
                                    <div class="alert alert-info" style="margin-top: 0;">
                                        Parámetros del orquestador de deploy y enlaces GitHub/CI en intranet (SDD-097).
                                        El token de API sigue en <code>.env</code>
                                        (<code>CENTINELA_GITHUB_TOKEN</code>).
                                    </div>
                                @endif
                                @foreach ($items as $setting)
                                    @php
                                        $flatKey = $setting['flat_key'];
                                        $details = $setting['details'];
                                    @endphp
                                    <div class="form-group" wire:key="setting-{{ $flatKey }}">
                                        <label class="control-label">
                                            {{ $setting['display_name'] }}
                                            <code style="font-weight: normal; font-size: 12px; margin-left: 6px;">
                                                {{ $setting['key'] }}
                                            </code>
                                        </label>
                                        @if (isset($details['description']) && $details['description'])
                                            <p class="help-block" style="font-size:12px;color:#777;margin:-4px 0 8px">
                                                {{ $details['description'] }}</p>
                                        @endif

                                        @if ($setting['type'] === 'radio_btn' && isset($details['options']))
                                            @php $optionKeys = array_keys($details['options']); @endphp
                                            @if (count($optionKeys) === 2 && in_array('On', $optionKeys) && in_array('Off', $optionKeys))
                                                <label class="switch-toggle">
                                                    <input type="checkbox"
                                                        wire:click="$set('settings.{{ $flatKey }}', '{{ $this->settings[$flatKey] === 'On' ? 'Off' : 'On' }}')"
                                                        {{ $this->settings[$flatKey] === 'On' ? 'checked' : '' }}>
                                                    <span class="switch-slider"></span>
                                                </label>
                                                <span
                                                    class="switch-label">{{ $this->settings[$flatKey] === 'On' ? $details['options']['On'] ?? 'Activado' : $details['options']['Off'] ?? 'Desactivado' }}</span>
                                            @else
                                                <div class="btn-group" data-toggle="buttons">
                                                    @foreach ($details['options'] as $optVal => $optLabel)
                                                        <label
                                                            class="btn btn-primary {{ $this->settings[$flatKey] === $optVal ? 'active' : '' }}"
                                                            wire:click="$set('settings.{{ $flatKey }}', '{{ $optVal }}')">
                                                            <input type="radio" name="s-{{ $flatKey }}"
                                                                value="{{ $optVal }}"
                                                                {{ $this->settings[$flatKey] === $optVal ? 'checked' : '' }}>
                                                            {{ $optLabel }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @elseif (in_array($setting['type'], ['image', 'file']))
                                            @include(
                                                'livewire.configuracion.partials.setting-media-field',
                                                [
                                                    'setting' => $setting,
                                                ]
                                            )
                                        @else
                                            <input
                                                type="{{ isset($details['min']) && is_numeric($details['min']) ? 'number' : 'text' }}"
                                                class="form-control" wire:model.lazy="settings.{{ $flatKey }}"
                                                placeholder="{{ $this->textPlaceholder($setting['key'], is_array($details) ? $details : null) }}"
                                                autocomplete="off"
                                                @if (isset($details['min'])) min="{{ $details['min'] }}" @endif
                                                @if (isset($details['step'])) step="{{ $details['step'] }}" @endif>
                                        @endif
                                    </div>
                                @endforeach
                            </x-slot>
                            <x-slot name="footer">
                                <button type="button" class="btn btn-primary" wire:click="save"
                                    wire:loading.attr="disabled" wire:target="save,mediaUploads">
                                    <i class="voyager-check" wire:loading.remove wire:target="save"></i>
                                    <i class="voyager-refresh lw-spin" wire:loading wire:target="save"></i>
                                    <span wire:loading.remove wire:target="save">Guardar configuracion</span>
                                    <span wire:loading wire:target="save">Guardando...</span>
                                </button>
                            </x-slot>
                        </x-panel-component>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
