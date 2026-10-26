<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessRequestRequest;
use App\Mail\AccessRequestSubmitted;
use App\Models\CenHieDependency;
use App\Services\Auth\AccessRequestRecipientResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccessRequestController extends Controller
{
    public function create(): View
    {
        $serviciosHabilitados = CenHieDependency::query()
            ->enabled()
            ->get(['id', 'codigo', 'nombre']);

        return view('auth.access-request', compact('serviciosHabilitados'));
    }

    public function store(
        StoreAccessRequestRequest $request,
        AccessRequestRecipientResolver $resolver,
    ): RedirectResponse {
        $validated = $request->validated();
        $ssId = (int) $validated['cen_hie_dependency_id'];

        if ($request->isOtroServicioSalud()) {
            $recipientEmail = $resolver->resolveAdminFallbackEmail();
            $servicioSaludDisplay = trim((string) ($validated['servicio_salud_nombre'] ?? ''));
            $servicioSalud = null;
        } else {
            $servicioSalud = CenHieDependency::query()->findOrFail($ssId);
            $servicioSaludDisplay = $servicioSalud->codigo.' — '.$servicioSalud->nombre;
            $recipientEmail = $servicioSalud->habilitado
                ? $resolver->resolveEmailForServicio($ssId)
                : $resolver->resolveAdminFallbackEmail();
        }

        if ($recipientEmail === '') {
            Log::error('Solicitud de acceso: sin destinatario (receptor primario ni notifications.contact_mail_to).', [
                'cen_hie_dependency_id' => $ssId,
            ]);

            return redirect()
                ->route('centinela.access-request.create')
                ->withInput()
                ->with('access_request_error', 'No fue posible enviar la solicitud en este momento. Intente más tarde o contacte al administrador de la plataforma.');
        }

        Mail::to($recipientEmail)->queue(new AccessRequestSubmitted(
            $validated,
            $servicioSalud,
            $servicioSaludDisplay,
        ));

        return redirect()
            ->route('centinela.access-request.create')
            ->with('access_request_success', 'Su solicitud fue enviada correctamente. El equipo de su Servicio de Salud o soporte se pondrá en contacto.');
    }
}
