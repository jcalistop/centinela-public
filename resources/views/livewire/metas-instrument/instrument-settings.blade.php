<div class="metas-instrument-settings" wire:poll.15s>
    @if (session()->has('settings_message'))
        <div class="alert alert-success alert-dismissible metas-settings-flash" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('settings_message') }}
        </div>
    @endif

    <div class="metas-settings-grid">
        @if (Auth::user()->hasRole(['admin', 'control-gestion']))
            <x-metas.settings-section title="Etapa vigente" icon="voyager-activity">
                <div class="metas-settings-status">
                    <span class="metas-settings-status__label">Etapa actual:</span>
                    <span class="label label-info metas-settings-status__badge">
                        {{ \App\Enums\IndicatorType\EtapaVigente::tryFrom($etapaVigente)?->label() ?? $etapaVigente }}
                    </span>
                </div>

                @if ($errors->hasAny(['etapaVigente', 'motivoRetroceso']))
                    <div class="alert alert-danger metas-settings-etapa-form__alert" role="alert">
                        <strong>No se pudo actualizar la etapa.</strong>
                        <ul class="metas-settings-etapa-form__alert-list">
                            @error('etapaVigente')
                                <li>{{ $message }}</li>
                            @enderror
                            @error('motivoRetroceso')
                                <li>{{ $message }}</li>
                            @enderror
                        </ul>
                    </div>
                @endif

                <div class="metas-settings-etapa-form">
                    <div class="metas-settings-etapa-form__main @error('etapaVigente') has-error @enderror">
                        <div class="form-group metas-settings-etapa-form__select">
                            <label for="settings-etapa-vigente">Cambiar etapa</label>
                            <select id="settings-etapa-vigente" wire:model="etapaVigente" class="form-control">
                                @foreach (\App\Enums\IndicatorType\EtapaVigente::ordenadasPorCiclo() as $etapa)
                                    <option value="{{ $etapa->value }}">{{ $etapa->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="metas-settings-etapa-form__submit">
                            <button wire:click="saveEtapa" wire:loading.attr="disabled" type="button"
                                class="btn btn-primary btn-block">
                                <span wire:loading wire:target="saveEtapa"><i class="voyager-loader"></i></span>
                                Actualizar etapa
                            </button>
                        </div>
                    </div>

                    <div
                        class="form-group metas-settings-etapa-form__motivo @error('motivoRetroceso') has-error @enderror">
                        <label for="settings-motivo-retroceso">Motivo (obligatorio al retroceder)</label>
                        <textarea id="settings-motivo-retroceso" wire:model="motivoRetroceso" class="form-control" rows="2"
                            placeholder="Indique el motivo si la nueva etapa es anterior a la vigente"></textarea>
                        <p class="help-block text-muted metas-settings-etapa-form__hint">
                            Solo se exige al retroceder en el ciclo de etapas.
                        </p>
                    </div>
                </div>

                <x-slot:actions>
                    <div class="metas-settings-recalc-row">
                        @if (($stageTransitionsCount ?? 0) > 0)
                            <button wire:click="openStageHistoryModal" wire:loading.attr="disabled" type="button"
                                class="btn btn-warning">
                                <span wire:loading wire:target="openStageHistoryModal"><i
                                        class="voyager-loader"></i></span>
                                <i class="voyager-list" aria-hidden="true"></i>
                                Historial de etapas ({{ $stageTransitionsCount }})
                            </button>
                        @endif
                        <button wire:click="recalculateNow" wire:loading.attr="disabled" type="button"
                            class="btn btn-warning">
                            <span wire:loading wire:target="recalculateNow"><i class="voyager-loader"></i></span>
                            <i class="voyager-wand" aria-hidden="true"></i>
                            Recalcular resultados
                        </button>
                        @if (($calculationQueue['pending_jobs'] ?? 0) > 0)
                            <span class="metas-settings-recalc-queue text-warning">
                                <i class="voyager-info-circled" aria-hidden="true"></i>
                                {{ $calculationQueue['message'] }}
                            </span>
                        @endif
                    </div>
                </x-slot:actions>
            </x-metas.settings-section>

            @include('livewire.metas-instrument.partials.stage-history-modal')

            <x-metas.settings-section title="Ciclo de etapas del instrumento" icon="voyager-info-circled"
                intro="La etapa vigente controla el acceso de los usuarios, según su rol, y qué puede editarse.">
                <x-metas.etapas-legend :etapa-vigente="$etapaVigente" />
                <div class="metas-settings-status" style="margin-top: 1rem;">
                    <span class="metas-settings-status__label">Acceso referentes/visualizadores:</span>
                    <span class="label label-{{ $habilitado ? 'success' : 'danger' }} metas-settings-status__badge">
                        {{ $habilitado ? 'Habilitado' : 'No habilitado' }}
                    </span>
                </div>
            </x-metas.settings-section>
        @endif

        @if (Auth::user()->hasRole(['parametrizador', 'admin', 'control-gestion']))
            <x-metas.settings-section title="Parámetros de convenio" icon="voyager-params" wide
                intro="Parámetros globales que aplican a todos los Servicios de Salud para este instrumento en el año activo. Se validan al configurar indicadores por establecimiento.">
                @unless ($canEditGlobalParams)
                    <p class="metas-settings-section__note text-muted small">Solo lectura. Los edita el rol parametrizador.
                    </p>
                @endunless
                <div class="metas-settings-params">
                    <div class="form-group @error('minIndicadores') has-error @enderror">
                        <label for="settings-min-indicadores">Mínimo de indicadores</label>
                        <input id="settings-min-indicadores" wire:model="minIndicadores" type="number" min="1"
                            step="1" class="form-control" @disabled(!$canEditGlobalParams) />
                        @error('minIndicadores')
                            <span class="help-block">{{ $message }}</span>
                        @else
                            <span class="help-block text-muted">
                                Mínimo seleccionables por establecimiento (actual:
                                <strong>{{ $minIndicadores }}</strong>).
                            </span>
                        @enderror
                    </div>

                    <div class="form-group @error('minPonderacion') has-error @enderror">
                        <label for="settings-min-ponderacion">Ponderación mínima (%)</label>
                        <input id="settings-min-ponderacion" wire:model="minPonderacion" type="number" min="0"
                            max="99.99" step="0.01" class="form-control" @disabled(!$canEditGlobalParams) />
                        @error('minPonderacion')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group @error('maxPonderacion') has-error @enderror">
                        <label for="settings-max-ponderacion">Ponderación máxima (%)</label>
                        <input id="settings-max-ponderacion" wire:model="maxPonderacion" type="number" min="0.01"
                            max="100" step="0.01" class="form-control" @disabled(!$canEditGlobalParams) />
                        @error('maxPonderacion')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <x-slot:actions>
                    @if ($canEditGlobalParams)
                        <button wire:click="saveParams" wire:loading.attr="disabled" type="button"
                            class="btn btn-primary">
                            <span wire:loading wire:target="saveParams"><i class="voyager-loader"></i></span>
                            Guardar parámetros
                        </button>
                    @endif
                </x-slot:actions>
            </x-metas.settings-section>

            <x-metas.settings-section title="Umbrales de semáforo global" icon="voyager-params" wide
                intro="Afecta solo al color del cumplimiento global; los umbrales que definen el aporte ponderado viven en la ficha de cada indicador.">
                @unless ($canEditGlobalParams)
                    <p class="metas-settings-section__note text-muted small">Solo lectura. Los edita el rol parametrizador.
                    </p>
                @endunless
                <div class="metas-settings-params">
                    <div class="form-group @error('semaforoVerdeMin') has-error @enderror">
                        <label for="settings-semaforo-verde">Umbral verde (%)</label>
                        <input id="settings-semaforo-verde" wire:model.live="semaforoVerdeMin" type="number"
                            min="0.01" max="100" step="0.01" class="form-control"
                            @disabled(!$canEditGlobalParams) />
                        @error('semaforoVerdeMin')
                            <span class="help-block">{{ $message }}</span>
                        @else
                            <span class="help-block text-muted">
                                @if ($semaforoVerdeEsHeredado)
                                    Valor heredado por ley del instrumento
                                    (<strong>{{ $semaforoVerdeMin }}</strong> %).
                                @else
                                    Configurado para este año.
                                @endif
                            </span>
                        @enderror
                    </div>

                    <div class="form-group @error('semaforoAmarilloMin') has-error @enderror">
                        <label for="settings-semaforo-amarillo">Umbral amarillo (%)</label>
                        <input id="settings-semaforo-amarillo" wire:model.live="semaforoAmarilloMin" type="number"
                            min="0" max="99.99" step="0.01" class="form-control"
                            @disabled(!$canEditGlobalParams) />
                        @error('semaforoAmarilloMin')
                            <span class="help-block">{{ $message }}</span>
                        @else
                            <span class="help-block text-muted">
                                @if ($semaforoAmarilloEsHeredado)
                                    Valor heredado por ley del instrumento
                                    (<strong>{{ $semaforoAmarilloMin }}</strong> %).
                                @else
                                    Configurado para este año.
                                @endif
                            </span>
                        @enderror
                    </div>
                </div>

                @if (is_numeric($semaforoVerdeMin) && is_numeric($semaforoAmarilloMin))
                    <div class="metas-settings-semaforo-preview" style="margin-top: 1rem;">
                        <x-metas.semaforo-legend context="global" :umbral-superior="(float) $semaforoVerdeMin" :umbral-amarillo="(float) $semaforoAmarilloMin" />
                    </div>
                @endif

                <x-slot:actions>
                    @if ($canEditGlobalParams)
                        <button wire:click="saveSemaforo" wire:loading.attr="disabled" type="button"
                            class="btn btn-primary">
                            <span wire:loading wire:target="saveSemaforo"><i class="voyager-loader"></i></span>
                            Guardar umbrales
                        </button>
                    @endif
                </x-slot:actions>
            </x-metas.settings-section>
        @endif
    </div>
</div>
