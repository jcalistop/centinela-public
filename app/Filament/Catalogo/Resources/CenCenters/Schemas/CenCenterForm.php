<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Schemas;

use App\Enums\Cen\CenCenterAdmDependency;
use App\Enums\Cen\CenCenterCertification;
use App\Enums\Cen\CenCenterEmergencyType;
use App\Enums\Cen\CenCenterOrigen;
use App\Enums\Cen\CenCenterProviderType;
use App\Enums\Cen\CenCenterScope;
use App\Enums\Cen\CenCenterType;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Cen\CentroNivelAtencion;
use App\Enums\Cen\CentroNivelComplejidad;
use App\Enums\Cen\CentroPertenenciaSnss;
use App\Enums\Cen\CentroTieneUrgencia;
use App\Enums\Cen\CentroTipoAtencion;
use App\Enums\Cen\CentroTipoSapu;
use App\Enums\Cen\DireccionVia;
use App\Models\CenCommune;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CenCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Básicos')
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('nombre_oficial')
                            ->label('Nombre oficial')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nombre_corto')
                            ->label('Nombre corto')
                            ->required()
                            ->maxLength(255),
                        Select::make('cen_hie_dependency_id')
                            ->label('Servicio de Salud')
                            ->relationship('cenHieDependency', 'nombre')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('origen')
                            ->label('Origen')
                            ->options(CenCenterOrigen::class)
                            ->default(CenCenterOrigen::Manual->value)
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        TextInput::make('codigo_madre')
                            ->label('Código madre')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Clasificación')
                    ->schema([
                        Select::make('cen_type')
                            ->label('Tipo')
                            ->options(CenCenterType::class)
                            ->searchable(),
                        Select::make('cen_scope')
                            ->label('Ámbito')
                            ->options(CenCenterScope::class)
                            ->searchable(),
                        Select::make('cen_adm_dependency')
                            ->label('Dependencia administrativa')
                            ->options(CenCenterAdmDependency::class)
                            ->searchable(),
                        Select::make('cen_certification')
                            ->label('Certificación')
                            ->options(CenCenterCertification::class)
                            ->searchable(),
                        Select::make('cen_emergency_type')
                            ->label('Tipo urgencia')
                            ->options(CenCenterEmergencyType::class)
                            ->searchable(),
                        Select::make('cen_provider_type')
                            ->label('Tipo prestador')
                            ->options(CenCenterProviderType::class)
                            ->searchable(),
                        Select::make('pertenencia_snss')
                            ->label('Pertenencia SNSS')
                            ->options(CentroPertenenciaSnss::class)
                            ->required(),
                        Select::make('nivel_atencion')
                            ->label('Nivel de atención')
                            ->options(CentroNivelAtencion::class)
                            ->required(),
                        Select::make('tiene_urgencia')
                            ->label('Tiene urgencia')
                            ->options(CentroTieneUrgencia::class)
                            ->required(),
                        Select::make('tipo_sapu')
                            ->label('Tipo SAPU')
                            ->options(CentroTipoSapu::class)
                            ->required(),
                        Select::make('estado_funcionamiento')
                            ->label('Estado funcionamiento')
                            ->options(CentroEstadoFuncionamiento::class)
                            ->required(),
                        Select::make('nivel_complejidad')
                            ->label('Nivel complejidad')
                            ->options(CentroNivelComplejidad::class)
                            ->required(),
                        Select::make('tipo_atencion')
                            ->label('Tipo atención')
                            ->options(CentroTipoAtencion::class)
                            ->required(),
                        Toggle::make('tarea_pendiente')
                            ->label('Tarea pendiente')
                            ->default(false),
                    ])
                    ->columns(2),
                Section::make('Ubicación')
                    ->schema([
                        Select::make('address_via')
                            ->label('Vía')
                            ->options(DireccionVia::class)
                            ->searchable(),
                        TextInput::make('address_direccion')
                            ->label('Dirección')
                            ->maxLength(255),
                        TextInput::make('address_numero')
                            ->label('Número')
                            ->maxLength(50),
                        TextInput::make('address_telefono')
                            ->label('Teléfono')
                            ->maxLength(50),
                        TextInput::make('address_latitud')
                            ->label('Latitud')
                            ->maxLength(50),
                        TextInput::make('address_longitud')
                            ->label('Longitud')
                            ->maxLength(50),
                        Select::make('address_cen_commune_id')
                            ->label('Comuna')
                            ->options(fn (): array => CenCommune::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                            ->searchable(),
                    ])
                    ->columns(2),
            ]);
    }
}
