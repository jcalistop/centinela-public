<div>
    <div class="panel panel-bordered">
        <div class="panel-heading">
            <h3 class="panel-title">Mensajería enviada</h3>
        </div>
        <div class="panel-body">
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-4">
                    <input type="search" class="form-control" placeholder="Buscar por asunto"
                        wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-8 text-right">
                    <label class="text-muted" style="font-weight: normal; margin-right: 8px;">Por página</label>
                    <select class="form-control" style="display: inline-block; width: auto;" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Asunto</th>
                            <th>Audiencia</th>
                            <th>Estado</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Enviados</th>
                            <th class="text-right">Fallidos</th>
                            @if ($isAdmin)
                                <th>Emisor</th>
                            @endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr wire:key="campaign-{{ $campaign->id }}">
                                <td>{{ $campaign->id }}</td>
                                <td>{{ $campaign->created_at?->format('d-m-Y H:i') ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit((string) $campaign->subject, 40) }}</td>
                                <td>{{ $campaign->audience->label() }}</td>
                                <td>
                                    @php
                                        $status = $campaign->status;
                                        $statusClass = match ($status) {
                                            \App\Enums\MessagingCampaignStatus::Completed => 'success',
                                            \App\Enums\MessagingCampaignStatus::Sending, \App\Enums\MessagingCampaignStatus::Queued => 'warning',
                                            \App\Enums\MessagingCampaignStatus::Failed => 'danger',
                                            default => 'default',
                                        };
                                    @endphp
                                    <span class="label label-{{ $statusClass }}">{{ $status->label() }}</span>
                                </td>
                                <td class="text-right">{{ $campaign->recipients_total }}</td>
                                <td class="text-right">{{ $campaign->recipients_sent }}</td>
                                <td class="text-right">{{ $campaign->recipients_failed }}</td>
                                @if ($isAdmin)
                                    <td>{{ $campaign->sender?->name ?? '—' }}</td>
                                @endif
                                <td class="text-right">
                                    <button type="button" class="btn btn-xs btn-default"
                                        wire:click="openDetail({{ $campaign->id }})">
                                        <i class="voyager-eye"></i> Detalle
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 10 : 9 }}" class="text-center text-muted">
                                    {{ trim($search) === '' ? 'Sin campañas enviadas.' : 'Sin resultados para la búsqueda.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $campaigns->links() }}
        </div>
    </div>

    @if ($showDetail && $detail !== null)
        @include('livewire.messaging.partials.detail-modal', ['campaign' => $detail])
    @endif
</div>
