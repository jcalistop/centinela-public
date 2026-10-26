<?php

namespace App\Filament\Catalogo\Resources\IndicatorTypeSettings\Schemas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\IndicatorType\TipoEvaluacion;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\User;
use App\Models\Year;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IndicatorTypeSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('indicator_type_id')
                    ->label('Instrumento')
                    ->options(fn (): array => IndicatorType::query()
                        ->orderBy('orden')
                        ->pluck('nombre_indicador', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false),
                Select::make('year_id')
                    ->label('Año')
                    ->options(fn (): array => Year::query()
                        ->orderByDesc('ano')
                        ->get()
                        ->mapWithKeys(fn (Year $year): array => [$year->id => (string) $year->ano])
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false),
                Select::make('cen_hie_dependency_id')
                    ->label('Servicio de Salud')
                    ->options(fn (): array => CenHieDependency::query()
                        ->orderBy('nombre')
                        ->pluck('nombre', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->default(function (): ?int {
                        $user = Auth::user();
                        if (! $user instanceof User) {
                            return null;
                        }

                        return $user->cen_hie_dependency_id;
                    }),
                Select::make('etapa_vigente')
                    ->label('Etapa vigente')
                    ->options(self::etapaOptions())
                    ->required()
                    ->native(false)
                    ->rules([Rule::enum(EtapaVigente::class)]),
                Select::make('tipo_evaluacion')
                    ->label('Tipo evaluación')
                    ->options(self::tipoEvaluacionOptions())
                    ->required()
                    ->native(false)
                    ->default(TipoEvaluacion::NoDefinido->value)
                    ->rules([Rule::enum(TipoEvaluacion::class)]),
                Toggle::make('habilitado')
                    ->label('Habilitado')
                    ->default(false)
                    ->helperText('En instrumentos Metas se deriva de la etapa vigente al guardar.'),
                TextInput::make('panel_minsal')
                    ->label('Panel Minsal (URL)')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://direccion.web.del.panel'),
                RichEditor::make('texto_enriquecido')
                    ->label('Texto enriquecido')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function etapaOptions(): array
    {
        $options = [];
        foreach (EtapaVigente::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function tipoEvaluacionOptions(): array
    {
        return [
            TipoEvaluacion::NoDefinido->value => 'No definido',
            TipoEvaluacion::Anual->value => 'Anual',
            TipoEvaluacion::Semestral->value => 'Semestral',
            TipoEvaluacion::Trimestral->value => 'Trimestral',
            TipoEvaluacion::Mensual->value => 'Mensual',
        ];
    }
}
