<?php

namespace App\Filament\Configuracion\Pages;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Configuration\FilamentSettingsGate;
use App\Support\Configuration\ProductClosedSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Piloto T1: configuración del sistema en Filament (paridad parcial con SettingsManager).
 *
 * @property-read Schema $form
 */
class ManageSystemSettings extends Page
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración del sistema';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, string> flat_key => db key
     */
    private array $keyMap = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->hasRole('admin')
            && FilamentSettingsGate::enabled();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($this->loadFormState());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->buildTabComponents())
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Guardar')
                                ->submit('save'),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $this->rebuildKeyMap();

        foreach ($state as $flatKey => $value) {
            $dbKey = $this->keyMap[$flatKey] ?? null;
            if ($dbKey === null) {
                continue;
            }

            $setting = SystemSetting::query()->where('key', $dbKey)->first();
            if ($setting === null) {
                continue;
            }

            if (in_array($setting->type, ['image', 'file'], true)) {
                continue;
            }

            if ($setting->type === 'radio_btn') {
                if (is_bool($value)) {
                    $value = $value ? 'On' : 'Off';
                }
                if (! in_array($value, ['On', 'Off'], true)) {
                    Notification::make()
                        ->title('Valor inválido')
                        ->body("La clave {$dbKey} solo admite On/Off.")
                        ->danger()
                        ->send();

                    return;
                }
            }

            if ((string) $setting->value !== (string) $value) {
                $setting->value = $value;
                $setting->save();
            }
        }

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();

        $this->form->fill($this->loadFormState());
    }

    /**
     * @return array<int, Tabs>
     */
    private function buildTabComponents(): array
    {
        $grouped = $this->settingsBySemanticGroup();
        $tabs = [];

        foreach ($grouped as $label => $settings) {
            $fields = [];
            foreach ($settings as $setting) {
                $flatKey = str_replace('.', '_', $setting->key);
                $fields[] = $this->fieldForSetting($setting, $flatKey);
            }

            $tabs[] = Tab::make($label)
                ->schema($fields);
        }

        return [
            Tabs::make('settingsTabs')
                ->tabs($tabs)
                ->columnSpanFull(),
        ];
    }

    private function fieldForSetting(SystemSetting $setting, string $flatKey): TextInput|Select
    {
        $label = (string) ($setting->display_name ?: $setting->key);

        if ($setting->type === 'radio_btn') {
            return Select::make($flatKey)
                ->label($label)
                ->options([
                    'On' => 'Activado',
                    'Off' => 'Desactivado',
                ])
                ->required()
                ->helperText($setting->key);
        }

        return TextInput::make($flatKey)
            ->label($label)
            ->helperText($setting->key)
            ->disabled(in_array($setting->type, ['image', 'file'], true));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormState(): array
    {
        $this->rebuildKeyMap();
        $state = [];

        foreach (SystemSetting::query()->orderBy('order')->get() as $setting) {
            if (ProductClosedSettings::isHiddenKey((string) $setting->key)) {
                continue;
            }

            if (! $this->isPilotGroup((string) $setting->group)) {
                continue;
            }

            $flatKey = str_replace('.', '_', $setting->key);
            $state[$flatKey] = $setting->value;
        }

        return $state;
    }

    private function rebuildKeyMap(): void
    {
        $this->keyMap = [];
        foreach (SystemSetting::query()->get(['key']) as $setting) {
            if (ProductClosedSettings::isHiddenKey((string) $setting->key)) {
                continue;
            }

            $this->keyMap[str_replace('.', '_', $setting->key)] = $setting->key;
        }
    }

    /**
     * @return array<string, list<SystemSetting>>
     */
    private function settingsBySemanticGroup(): array
    {
        $preferred = ['General', 'Módulos', 'Notificaciones', 'Sistema'];
        $buckets = array_fill_keys($preferred, []);

        foreach (SystemSetting::query()->orderBy('order')->get() as $setting) {
            if (ProductClosedSettings::isHiddenKey((string) $setting->key)) {
                continue;
            }

            $label = $this->semanticGroupLabel((string) ($setting->group ?? 'General'));
            if (! isset($buckets[$label])) {
                continue;
            }
            $buckets[$label][] = $setting;
        }

        return array_filter($buckets, fn (array $items): bool => $items !== []);
    }

    private function isPilotGroup(string $group): bool
    {
        return in_array($this->semanticGroupLabel($group), ['General', 'Módulos', 'Notificaciones', 'Sistema'], true);
    }

    private function semanticGroupLabel(string $group): string
    {
        $normalized = Str::lower(trim($group));

        return match (true) {
            $normalized === 'admin', $normalized === 'general' => 'General',
            $normalized === 'modulos', $normalized === 'módulos', $normalized === 'modules' => 'Módulos',
            $normalized === 'notificaciones', $normalized === 'notifications' => 'Notificaciones',
            $normalized === 'sistema', $normalized === 'system' => 'Sistema',
            default => $group,
        };
    }
}
