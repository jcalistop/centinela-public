<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypes\Schemas;

use App\Models\IndicatorType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class IndicatorTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('clave_indicador')
                    ->label('Clave')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Nombre que aparecerá en el menú.'),
                TextInput::make('nombre_indicador')
                    ->label('Nombre visible')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Nombre que aparecerá en los títulos.'),
                Textarea::make('descripcion_indicador')
                    ->label('Descripción')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('route')
                    ->label('Slug de URL bajo /admin/')
                    ->options(fn (): array => self::routeOptions())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->disabledOn('edit')
                    ->dehydrated()
                    ->disableOptionWhen(function (string $value, ?Model $record): bool {
                        return IndicatorType::query()
                            ->where('route', $value)
                            ->when(
                                $record !== null,
                                fn ($query) => $query->whereKeyNot($record->getKey())
                            )
                            ->exists();
                    })
                    ->rules([
                        Rule::in(IndicatorType::configMetasRouteSlugs()),
                    ])
                    ->unique(table: 'indicator_types', column: 'route', ignoreRecord: true)
                    ->noOptionsMessage('No hay slugs en config (centinela.metas_indicator_type_route_slugs).')
                    ->helperText('Lista fija de config. Los ya asignados aparecen deshabilitados (en uso). Al editar: inmutable.'),
                TextInput::make('icon_class')
                    ->label('Icono (clase Voyager)')
                    ->maxLength(255)
                    ->placeholder('voyager-book')
                    ->helperText('Texto de clase; render visual diferido (deuda T3b/C1).'),
                Select::make('measurement_granularity')
                    ->label('Granularidad de medición')
                    ->options(IndicatorType::measurementGranularityOptions())
                    ->required()
                    ->native(false),
                Toggle::make('estado')
                    ->label('Activo')
                    ->default(false)
                    ->required(),
                TextInput::make('orden')
                    ->label('Orden')
                    ->numeric()
                    ->integer()
                    ->minValue(0),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function routeOptions(): array
    {
        $inUse = IndicatorType::query()
            ->whereNotNull('route')
            ->where('route', '!=', '')
            ->pluck('route')
            ->all();

        $options = [];
        foreach (IndicatorType::configMetasRouteSlugs() as $slug) {
            $options[$slug] = in_array($slug, $inUse, true)
                ? $slug.' (en uso)'
                : $slug;
        }

        return $options;
    }
}
