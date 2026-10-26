<div>
    <div class="page-content container-fluid">
        <div class="clearfix">
            <h1 class="page-title">
                <i class="voyager-mail"></i> Enviar mensajería
            </h1>
            <a id="centinela-mensajeria-volver-usuarios" href="{{ route('voyager.users.index') }}"
                class="btn btn-warning btn-add-new">
                <i class="voyager-angle-left"></i> <span>Volver a Usuarios</span>
            </a>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title">Redactar envío</h3>
            </div>
            <div class="panel-body">
                <div class="form-group @error('audience') has-error @enderror">
                    <label for="messaging-audience">Audiencia</label>
                    <select id="messaging-audience" class="form-control" wire:model.live="audience">
                        @foreach ($audienceOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('audience')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                @if ($isAdmin && in_array($audience, $ssAudiences, true))
                    <div class="form-group @error('cen_hie_dependency_id') has-error @enderror">
                        <label for="messaging-ss">Servicio de Salud</label>
                        <select id="messaging-ss" class="form-control" wire:model.live="cen_hie_dependency_id">
                            <option value="">— Seleccione —</option>
                            @foreach ($healthServiceOptions as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @error('cen_hie_dependency_id')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>
                @elseif (!$isAdmin)
                    <div class="form-group">
                        <label>Servicio de Salud</label>
                        <input type="text" class="form-control"
                            value="{{ $healthServiceOptions[$cen_hie_dependency_id] ?? '—' }}" disabled>
                    </div>
                @endif

                @if ($audience === $specificUsers)
                    <div class="form-group @error('user_ids') has-error @enderror" wire:ignore>
                        <label for="messaging-user-ids">Usuarios</label>
                        <select id="messaging-user-ids" class="form-control" multiple style="width:100%">
                            @foreach ($userOptions as $id => $label)
                                <option value="{{ $id }}" @selected(in_array((int) $id, $user_ids, true))>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('user_ids')
                        <span class="help-block text-danger">{{ $message }}</span>
                    @enderror
                @endif

                <div class="form-group @error('subject') has-error @enderror">
                    <label for="messaging-subject">Asunto</label>
                    <input id="messaging-subject" type="text" class="form-control" wire:model="subject"
                        maxlength="255">
                    @error('subject')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group @error('body_html') has-error @enderror">
                    <label>Mensaje</label>
                    <div wire:ignore class="messaging-body-quill-wrap" style="background:#fff;">
                        <div data-messaging-body-quill class="messaging-body-quill-editor" style="min-height:180px;">
                        </div>
                    </div>
                    <input type="hidden" wire:model="body_html" data-messaging-body-quill-input>
                    @error('body_html')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                </div>

                @if ($previewCount !== null)
                    <p class="text-muted">Destinatarios calculados: <strong>{{ $previewCount }}</strong></p>
                @endif

                <button type="button" class="btn btn-default" wire:click="previewRecipients"
                    wire:loading.attr="disabled">
                    Calcular destinatarios
                </button>
                <button type="button" class="btn btn-primary" wire:click="openConfirm" @disabled($previewCount === null || $previewCount < 1)
                    wire:loading.attr="disabled">
                    Confirmar y encolar
                </button>
            </div>
        </div>

        <div wire:key="messaging-campaign-index">
            @livewire(\App\Livewire\Messaging\MessagingCampaignIndex::class)
        </div>
    </div>

    @if ($showConfirm)
        @include('livewire.messaging.partials.confirm-modal')
    @endif
</div>

@push('javascript')
    @vite('resources/js/messaging-body-quill.js')
@endpush

@script
    <script>
        const initUserSelect = () => {
            const $sel = $('#messaging-user-ids');
            if (!$sel.length) {
                return;
            }
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }
            $sel.select2({
                placeholder: 'Buscar usuarios...',
                width: '100%',
            });
            $sel.off('change.messaging').on('change.messaging', function() {
                const ids = ($(this).val() || []).map((id) => parseInt(id, 10)).filter((id) => id > 0);
                $wire.set('user_ids', ids);
            });
        };

        $wire.on('messaging-user-select-init', () => {
            setTimeout(initUserSelect, 150);
        });

        queueMicrotask(initUserSelect);
    </script>
@endscript
