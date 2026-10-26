<?php

namespace App\Filament\Messaging\Concerns;

use App\Enums\MessagingAudience;
use App\Enums\MessagingCampaignStatus;
use App\Models\MessagingCampaign;
use App\Models\User;
use App\Services\Messaging\MessagingCampaignLauncher;
use App\Support\Messaging\FilamentMessagingGate;
use App\Support\Messaging\MessagingComposeScope;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 * @property array<string, mixed>|null $data
 * @property int|null $previewCount
 */
trait ComposesMessagingCampaign
{
    use InteractsWithTable;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $previewCount = null;

    abstract protected static function actorIsAdminPanel(): bool;

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User || ! FilamentMessagingGate::enabled()) {
            return false;
        }

        if (static::actorIsAdminPanel()) {
            return MessagingComposeScope::actorIsAdmin($user);
        }

        return MessagingComposeScope::canCompose($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(MessagingComposeScope::defaults(
            $this->authenticatedActor(),
            static::actorIsAdminPanel(),
        ));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->messagingFormComponents())
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('previewRecipients')
                    ->footer([
                        Actions::make([
                            Action::make('previewRecipients')
                                ->label('Calcular destinatarios')
                                ->color('gray')
                                ->submit('previewRecipients'),
                            Action::make('sendCampaign')
                                ->label('Confirmar y encolar')
                                ->color('primary')
                                ->disabled(fn (): bool => $this->previewCount === null || $this->previewCount < 1)
                                ->requiresConfirmation()
                                ->modalHeading('Confirmar envío')
                                ->modalDescription(fn (): string => $this->previewCount === null
                                    ? 'Calcule primero los destinatarios.'
                                    : "Se encolarán {$this->previewCount} correo(s). El envío se fraccionará en olas.")
                                ->action('sendCampaign'),
                        ])->key('form-actions'),
                    ]),
                Section::make('Mensajería enviada')
                    ->description('Campañas recientes y su progreso de envío.')
                    ->schema([
                        EmbeddedTable::make(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->campaignsQuery())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('audience')
                    ->label('Audiencia')
                    ->formatStateUsing(fn (MessagingAudience|string $state): string => $state instanceof MessagingAudience
                        ? $state->label()
                        : (MessagingAudience::tryFrom((string) $state)?->label() ?? (string) $state)),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (MessagingCampaignStatus|string $state): string => $state instanceof MessagingCampaignStatus
                        ? $state->label()
                        : (MessagingCampaignStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (MessagingCampaignStatus|string $state): string => match ($state instanceof MessagingCampaignStatus ? $state : MessagingCampaignStatus::tryFrom((string) $state)) {
                        MessagingCampaignStatus::Completed => 'success',
                        MessagingCampaignStatus::Sending, MessagingCampaignStatus::Queued => 'warning',
                        MessagingCampaignStatus::Failed => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('recipients_total')
                    ->label('Total')
                    ->alignEnd(),
                TextColumn::make('recipients_sent')
                    ->label('Enviados')
                    ->alignEnd(),
                TextColumn::make('recipients_failed')
                    ->label('Fallidos')
                    ->alignEnd(),
                TextColumn::make('sender.name')
                    ->label('Emisor')
                    ->toggleable(isToggledHiddenByDefault: ! static::actorIsAdminPanel()),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->label('Detalle')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (MessagingCampaign $record): string => $record->subject)
                    ->modalDescription(fn (MessagingCampaign $record): string => 'Campaña #'.$record->id.' · '.($record->created_at?->format('d-m-Y H:i') ?? '—'))
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->schema([
                        Section::make('Resumen')
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (MessagingCampaignStatus $state): string => $state->label())
                                    ->color(fn (MessagingCampaignStatus $state): string => match ($state) {
                                        MessagingCampaignStatus::Completed => 'success',
                                        MessagingCampaignStatus::Sending, MessagingCampaignStatus::Queued => 'warning',
                                        MessagingCampaignStatus::Failed => 'danger',
                                    }),
                                TextEntry::make('audience')
                                    ->label('Audiencia')
                                    ->formatStateUsing(fn (MessagingAudience $state): string => $state->label()),
                                TextEntry::make('sender.name')->label('Emisor'),
                                TextEntry::make('sender.email')->label('Correo emisor'),
                                TextEntry::make('sender.unidad')->label('Unidad')->placeholder('—'),
                                TextEntry::make('healthService.nombre')->label('Servicio de Salud')->placeholder('—'),
                            ])
                            ->columns(2),
                        Section::make('Progreso de envío')
                            ->schema([
                                TextEntry::make('recipients_total')->label('Destinatarios'),
                                TextEntry::make('recipients_sent')->label('Enviados')->color('success'),
                                TextEntry::make('recipients_failed')->label('Fallidos')->color('danger'),
                                TextEntry::make('created_at')->label('Encolada')->dateTime('d-m-Y H:i'),
                            ])
                            ->columns(4),
                        Section::make('Mensaje')
                            ->schema([
                                TextEntry::make('body_html')
                                    ->label('')
                                    ->html()
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<MessagingCampaign>
     */
    protected function campaignsQuery(): Builder
    {
        return MessagingComposeScope::campaignsQuery(
            $this->authenticatedActor(),
            static::actorIsAdminPanel(),
        );
    }

    public function previewRecipients(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $state = $this->form->getState();
            $actor = $this->authenticatedActor();
            $this->previewCount = app(MessagingCampaignLauncher::class)->previewCount($actor, $this->payloadFromState($state));

            $notification = Notification::make()
                ->title($this->previewCount === 0
                    ? 'Sin destinatarios elegibles'
                    : "Destinatarios: {$this->previewCount}");

            if ($this->previewCount === 0) {
                $notification->warning()->send();
            } else {
                $notification->success()->send();
            }
        } catch (ValidationException $e) {
            $this->previewCount = null;
            $this->mapValidationExceptionToForm($e);
        }
    }

    public function sendCampaign(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $actor = $this->authenticatedActor();
        $launcher = app(MessagingCampaignLauncher::class);

        try {
            $count = $launcher->previewCount($actor, $this->payloadFromState($state));
            if ($count < 1) {
                Notification::make()
                    ->title('No hay destinatarios elegibles')
                    ->warning()
                    ->send();

                return;
            }

            $campaign = $launcher->launch($actor, $this->payloadFromState($state));
            $this->previewCount = $campaign->recipients_total;

            Notification::make()
                ->title('Campaña encolada')
                ->body("ID {$campaign->id}: {$campaign->recipients_total} destinatario(s).")
                ->success()
                ->send();

            $this->form->fill([
                'audience' => $state['audience'],
                'cen_hie_dependency_id' => $state['cen_hie_dependency_id'] ?? null,
                'user_ids' => [],
                'subject' => '',
                'body_html' => '',
            ]);
            $this->previewCount = null;
            $this->resetTable();
        } catch (ValidationException $e) {
            $this->mapValidationExceptionToForm($e);
        }
    }

    protected function mapValidationExceptionToForm(ValidationException $e): void
    {
        foreach ($e->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError('data.'.$key, $message);
            }
        }
    }

    /**
     * @return array<int, Select|TextInput|RichEditor>
     */
    protected function messagingFormComponents(): array
    {
        $isAdmin = static::actorIsAdminPanel();

        return [
            Select::make('audience')
                ->label('Audiencia')
                ->options($this->audienceOptions($isAdmin))
                ->required()
                ->live()
                ->native(false),
            Select::make('cen_hie_dependency_id')
                ->label('Servicio de Salud')
                ->options(fn (): array => MessagingComposeScope::healthServiceOptions())
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (Get $get): bool => in_array($get('audience'), [
                    MessagingAudience::HealthServiceAll->value,
                    MessagingAudience::HealthServiceReferentes->value,
                ], true) && $isAdmin)
                ->required(fn (Get $get): bool => in_array($get('audience'), [
                    MessagingAudience::HealthServiceAll->value,
                    MessagingAudience::HealthServiceReferentes->value,
                ], true) && $isAdmin)
                ->disabled(fn (): bool => ! $isAdmin)
                ->dehydrated(),
            Select::make('user_ids')
                ->label('Usuarios')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => MessagingComposeScope::userOptions($this->authenticatedActor(), $isAdmin))
                ->visible(fn (Get $get): bool => $get('audience') === MessagingAudience::SpecificUsers->value)
                ->required(fn (Get $get): bool => $get('audience') === MessagingAudience::SpecificUsers->value)
                ->native(false),
            TextInput::make('subject')
                ->label('Asunto')
                ->required()
                ->maxLength(255),
            RichEditor::make('body_html')
                ->label('Mensaje')
                ->required()
                ->toolbarButtons([
                    ['bold', 'italic', 'underline'],
                    ['h2', 'h3'],
                    ['bulletList', 'orderedList'],
                    ['link'],
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function audienceOptions(bool $isAdmin): array
    {
        return MessagingComposeScope::audienceOptions($isAdmin);
    }

    /**
     * @return array<int, string>
     */
    protected function userOptions(bool $isAdmin): array
    {
        return MessagingComposeScope::userOptions($this->authenticatedActor(), $isAdmin);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *     audience: string,
     *     cen_hie_dependency_id: int|null,
     *     user_ids: list<int>,
     *     subject: string,
     *     body_html: string
     * }
     */
    protected function payloadFromState(array $state): array
    {
        return MessagingComposeScope::payloadFromState($state);
    }

    protected function authenticatedActor(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
