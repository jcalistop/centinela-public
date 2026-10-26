<?php

namespace App\Livewire\Configuracion;

use App\Models\User;
use App\Support\Configuration\AdminBackgroundImage;
use App\Support\Configuration\ProductClosedSettings;
use App\Support\Configuration\VoyagerSettingMediaService;
use App\Support\Upload\UploadSizeLimit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use TCG\Voyager\Models\Setting;

/**
 * Componente Livewire que reemplaza la pagina /admin/settings de Voyager.
 *
 * Organiza las configuraciones en tabs semanticos:
 * - General: settings cosmeticos de Voyager (admin.*)
 * - Modulos: feature flags (module.*)
 * - Notificaciones: emails de alerta (notifications.*)
 * - Sistema: umbrales numericos (system.*)
 *
 * Las claves con puntos (ej: module.usage_tracking_enabled) se transforman
 * a guion bajo para el binding de Livewire (ej: module_usage_tracking_enabled)
 * y se mapean de vuelta al guardar, porque Livewire interpreta los puntos
 * como acceso a propiedades anidadas.
 */
class SettingsManager extends Component
{
    use WithFileUploads;

    /** @var array<string, string> Valores keyeados por nombre "plano" (puntos reemplazados por _) */
    public array $settings = [];

    /**
     * Archivos pendientes de persistir al guardar (flat_key => upload).
     *
     * @var array<string, TemporaryUploadedFile|null>
     */
    public array $mediaUploads = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $settingsByGroup = [];

    /** @var string Tab activa (slug del grupo). Ej: 'modulos', 'notificaciones', etc. */
    public string $activeTab = 'general';

    /**
     * Mapa de nombres planos (settings_) a claves reales de BD (settings.).
     *
     * @var array<string, string>
     */
    private array $keyMap = [];

    /**
     * Inicializar con todos los settings de BD.
     */
    public function mount(): void
    {
        $this->loadSettings();
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user?->hasPermission('browse_settings')) {
            abort(403);
        }

        /** @var View|\Illuminate\View\View $view */
        $view = view('livewire.configuracion.settings-manager', [
            'orderedTabSlugs' => $this->orderedSettingsTabSlugs(),
        ]);

        return $view->extends('voyager::master')->section('content');
    }

    /**
     * Orden fijo de pestañas en /admin/settings (slug => nombre de grupo en BD).
     *
     * @return array<int, array{slug: string, group: string}>
     */
    public function orderedSettingsTabSlugs(): array
    {
        $preferredOrder = [
            'Modulos',
            'Deploy',
            'Notificaciones',
            'Sistema',
            'Servicios externos',
        ];

        $available = [];
        foreach (array_keys($this->settingsByGroup) as $groupName) {
            if ($groupName === 'Admin') {
                continue;
            }
            $available[$groupName] = Str::slug($groupName, '-');
        }

        $ordered = [];
        foreach ($preferredOrder as $groupName) {
            if (! isset($available[$groupName])) {
                continue;
            }

            $ordered[] = [
                'slug' => $available[$groupName],
                'group' => $groupName,
            ];
            unset($available[$groupName]);
        }

        ksort($available);
        foreach ($available as $groupName => $slug) {
            $ordered[] = [
                'slug' => $slug,
                'group' => $groupName,
            ];
        }

        return $ordered;
    }

    /**
     * Guardar todos los settings modificados.
     */
    public function save(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user?->hasPermission('edit_settings')) {
            abort(403);
        }

        $this->validate($this->mediaUploadRules());

        try {
            $all = Setting::query()->get()->keyBy('key');

            // Reconstruir keyMap desde BD porque las propiedades privadas
            // no se serializan entre requests de Livewire.
            $keyMap = [];
            foreach ($all as $s) {
                $keyMap[str_replace('.', '_', $s->key)] = $s->key;
            }

            $mediaService = app(VoyagerSettingMediaService::class);

            foreach ($this->mediaUploads as $flatKey => $upload) {
                if (! $upload instanceof TemporaryUploadedFile) {
                    continue;
                }

                $dbKey = $keyMap[$flatKey] ?? null;
                if ($dbKey === null) {
                    continue;
                }

                $setting = $all->get($dbKey);
                if ($setting === null) {
                    continue;
                }

                $mediaService->deleteValue($setting->value, (string) $setting->type);

                $storedPath = match ((string) $setting->type) {
                    'image' => $mediaService->storeImage($upload, $dbKey),
                    'file' => $mediaService->storeFile($upload),
                    default => null,
                };

                if ($storedPath === null) {
                    continue;
                }

                $setting->value = $storedPath;
                $setting->save();
                $this->settings[$flatKey] = $storedPath;
            }

            $this->mediaUploads = [];

            foreach ($this->settings as $flatKey => $value) {
                $dbKey = $keyMap[$flatKey] ?? null;
                if ($dbKey === null) {
                    continue;
                }

                $setting = $all->get($dbKey);
                if ($setting === null) {
                    continue;
                }

                // Validar segun tipo
                if ($setting->type === 'radio_btn') {
                    if (! in_array($value, ['On', 'Off'], true)) {
                        $value = 'Off';
                    }
                }

                if ((string) $setting->value !== (string) $value) {
                    $setting->value = $value;
                    $setting->save();
                }
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'No se pudo guardar la configuración.');

            return;
        }

        AdminBackgroundImage::invalidate();

        $this->loadSettings();

        $this->dispatch('toast', type: 'success', message: 'Configuración guardada correctamente.');
    }

    public function removeStoredMedia(string $flatKey): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user?->hasPermission('edit_settings')) {
            abort(403);
        }

        $dbKey = $this->keyMapFromDatabase()[$flatKey] ?? null;
        if ($dbKey === null) {
            $this->dispatch('toast', type: 'error', message: 'No se encontró el setting.');

            return;
        }

        $setting = Setting::query()->where('key', $dbKey)->first();
        if ($setting === null) {
            $this->dispatch('toast', type: 'error', message: 'No se encontró el setting.');

            return;
        }

        if (! in_array($setting->type, ['image', 'file'], true)) {
            abort(400);
        }

        app(VoyagerSettingMediaService::class)->deleteValue($setting->value, (string) $setting->type);
        $setting->value = '';
        $setting->save();

        unset($this->mediaUploads[$flatKey]);
        $this->loadSettings();

        if ($dbKey === 'admin.bg_image') {
            AdminBackgroundImage::invalidate();
        }

        $this->dispatch('toast', type: 'success', message: 'Archivo eliminado.');
    }

    public function mediaPreviewUrl(string $flatKey, string $type): ?string
    {
        $upload = $this->mediaUploads[$flatKey] ?? null;
        if ($upload instanceof TemporaryUploadedFile) {
            try {
                return $upload->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        $value = $this->settings[$flatKey] ?? '';

        return app(VoyagerSettingMediaService::class)->previewUrl($value, $type);
    }

    public function hasStoredMedia(string $flatKey, string $type): bool
    {
        $value = $this->settings[$flatKey] ?? '';

        return app(VoyagerSettingMediaService::class)->hasStoredValue($value, $type);
    }

    /**
     * Guía de tamaño según uso real en la UI (Voyager / custom.css), no el peso del archivo en disco.
     */
    public function mediaFieldHint(string $dbKey): ?string
    {
        return match ($dbKey) {
            'admin.icon_image' => 'Uso: logo en la cabecera del menú lateral. En pantalla se ve ~30×30 px (dentro de un recuadro de 60×60 px). '
                .'Recomendado: PNG cuadrado 64×64 px (o 128×128 px para pantallas retina), fondo transparente.',
            'admin.bg_image' => 'Uso: franja decorativa superior en páginas tipo Compass (`#gradient_bg`: ancho completo × 220 px de alto, `background-size: cover`) '
                .'y fondo del panel de usuario en el sidebar (ancho del menú, recorte `cover`). '
                .'Recomendado: imagen horizontal ancha, mín. 1920×400 px; evita texto o logos en los bordes porque se recorta.',
            'admin.favicon_image' => 'Uso: icono de la pestaña del navegador (típicamente 16–32 px). '
                .'Recomendado: PNG cuadrado 32×32 px o 64×64 px, diseño simple y legible a tamaño pequeño.',
            'admin.loader' => 'Uso: animación/spinner al cargar el panel (`#voyager-loader`, centrado a 100×100 px). '
                .'Recomendado: PNG o GIF cuadrado 100×100 px (o 200×200 px retina). GIF animado solo si se necesita movimiento.',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function keyMapFromDatabase(): array
    {
        $map = [];
        foreach (Setting::query()->get(['key']) as $setting) {
            $map[str_replace('.', '_', $setting->key)] = $setting->key;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function mediaUploadRules(): array
    {
        $rules = [];

        foreach ($this->settingsByGroup as $items) {
            foreach ($items as $setting) {
                if (! in_array($setting['type'], ['image', 'file'], true)) {
                    continue;
                }

                $flatKey = $setting['flat_key'];
                $rules["mediaUploads.{$flatKey}"] = $setting['type'] === 'image'
                    ? 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:'.UploadSizeLimit::settingsImageMaxKilobytes()
                    : 'nullable|file|mimes:jpeg,jpg,png,gif,webp,svg|max:'.UploadSizeLimit::settingsFileMaxKilobytes();
            }
        }

        return $rules;
    }

    private function loadSettings(): void
    {
        $all = Setting::query()
            ->orderBy('group')
            ->orderBy('order')
            ->get();

        $this->settings = [];
        $this->keyMap = [];
        $groups = [];

        foreach ($all as $setting) {
            /** @var Setting $setting */
            if (ProductClosedSettings::isHiddenKey((string) $setting->key)) {
                continue;
            }

            $groupName = $setting->group ?? 'General';
            $flatKey = str_replace('.', '_', $setting->key);

            $this->keyMap[$flatKey] = $setting->key;

            if (! isset($groups[$groupName])) {
                $groups[$groupName] = [];
            }

            $groups[$groupName][] = [
                'key' => $setting->key,
                'flat_key' => $flatKey,
                'display_name' => $setting->display_name,
                'value' => $setting->value,
                'type' => $setting->type,
                'details' => $setting->details ? json_decode($setting->details, true) : null,
            ];

            $this->settings[$flatKey] = $setting->value;
        }

        $this->settingsByGroup = $groups;
    }
}
