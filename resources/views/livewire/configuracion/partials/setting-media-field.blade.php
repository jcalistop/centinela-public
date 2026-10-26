@php
    /** @var array<string, mixed> $setting */
    $flatKey = $setting['flat_key'];
    $type = $setting['type'];
    $previewUrl = $this->mediaPreviewUrl($flatKey, $type);
    $hasStored = $this->hasStoredMedia($flatKey, $type);
    $accept =
        $type === 'image'
            ? 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml'
            : 'image/*,.gif,.png,.jpg,.jpeg,.webp,.svg';
    $sizeHint = $this->mediaFieldHint($setting['key']);
    $uploadMaxKb =
        $type === 'image'
            ? \App\Support\Upload\UploadSizeLimit::settingsImageMaxKilobytes()
            : \App\Support\Upload\UploadSizeLimit::settingsFileMaxKilobytes();
@endphp

<div class="setting-media-field">
    @if ($previewUrl)
        <div class="setting-media-preview-wrap">
            <img src="{{ $previewUrl }}" alt="Vista previa" class="setting-media-preview">
        </div>
    @endif

    <div class="input-group" style="margin-bottom: 8px;">
        <input type="file" class="form-control" accept="{{ $accept }}"
            wire:model="mediaUploads.{{ $flatKey }}" wire:loading.attr="disabled"
            wire:target="mediaUploads.{{ $flatKey }},save" data-max-upload-bytes="{{ $uploadMaxKb * 1024 }}"
            data-max-upload-label="{{ \App\Support\Upload\UploadSizeLimit::humanLabelForKilobytes($uploadMaxKb) }}">
        <span class="input-group-btn" wire:loading wire:target="mediaUploads.{{ $flatKey }}">
            <span class="btn btn-default" disabled><i class="voyager-refresh lw-spin"></i></span>
        </span>
    </div>

    @error('mediaUploads.' . $flatKey)
        <p class="text-danger" style="font-size: 12px; margin-top: 4px;">{{ $message }}</p>
    @enderror

    @if ($sizeHint)
        <p class="help-block setting-media-size-hint">
            <i class="voyager-info-circled"></i> {{ $sizeHint }}
        </p>
    @endif

    <x-upload-size-hint :kilobytes="$uploadMaxKb" />

    @if ($hasStored)
        <button type="button" class="btn btn-sm btn-danger" wire:click="removeStoredMedia('{{ $flatKey }}')"
            wire:confirm="¿Eliminar el archivo actual?">
            <i class="voyager-trash"></i> Quitar archivo
        </button>
    @endif

    @if (filled($settings[$flatKey] ?? null) && $type === 'file')
        <p class="help-block" style="font-size: 12px; margin-top: 6px; margin-bottom: 0;">
            Archivo actual registrado en el sistema.
        </p>
    @endif
</div>
