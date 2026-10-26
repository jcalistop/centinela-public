<div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
    role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" wire:click="closeDetail">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">{{ $campaign->subject }}</h4>
                <p class="text-muted" style="margin: 0;">
                    Campaña #{{ $campaign->id }} · {{ $campaign->created_at?->format('d-m-Y H:i') ?? '—' }}
                </p>
            </div>
            <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                <h5>Resumen</h5>
                <table class="table table-bordered table-condensed">
                    <tbody>
                        <tr>
                            <th style="width: 30%;">Estado</th>
                            <td>{{ $campaign->status->label() }}</td>
                        </tr>
                        <tr>
                            <th>Audiencia</th>
                            <td>{{ $campaign->audience->label() }}</td>
                        </tr>
                        <tr>
                            <th>Emisor</th>
                            <td>{{ $campaign->sender?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Correo emisor</th>
                            <td>{{ $campaign->sender?->email ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Unidad</th>
                            <td>{{ $campaign->sender?->unidad ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Servicio de Salud</th>
                            <td>{{ $campaign->healthService?->nombre ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>

                <h5>Progreso de envío</h5>
                <table class="table table-bordered table-condensed">
                    <tbody>
                        <tr>
                            <th style="width: 30%;">Destinatarios</th>
                            <td>{{ $campaign->recipients_total }}</td>
                        </tr>
                        <tr>
                            <th>Enviados</th>
                            <td>{{ $campaign->recipients_sent }}</td>
                        </tr>
                        <tr>
                            <th>Fallidos</th>
                            <td>{{ $campaign->recipients_failed }}</td>
                        </tr>
                        <tr>
                            <th>Encolada</th>
                            <td>{{ $campaign->created_at?->format('d-m-Y H:i') ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>

                <h5>Mensaje</h5>
                <iframe sandbox="" referrerpolicy="no-referrer"
                    srcdoc="{{ $campaign->body_html }}"
                    style="width:100%; min-height:220px; border:1px solid #ddd; background:#fff;"
                    title="Cuerpo del mensaje"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" wire:click="closeDetail">Cerrar</button>
            </div>
        </div>
    </div>
</div>
