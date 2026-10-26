<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportTicketReceiverRequest;
use App\Http\Requests\Support\SupportTicketReceiverPersistRequest;
use App\Http\Requests\Support\UpdateSupportTicketReceiverRequest;
use App\Models\CenHieDependency;
use App\Models\SupportTicket;
use App\Models\SupportTicketReceiver;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SupportTicketReceiverController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SupportTicketReceiver::class);

        $receivers = SupportTicketReceiver::query()
            ->with(['user', 'cenHieDependency'])
            ->orderBy('scope')
            ->orderByDesc('enabled')
            ->orderByDesc('updated_at')
            ->get();

        return view('centinela.support.receivers.index', compact('receivers'));
    }

    /**
     * Búsqueda de usuarios para asignar un receptor (Select2 AJAX). Respuesta formato Select2 4.x.
     *
     * Filtros: {@see SupportTicketReceiverPersistRequest}.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        Gate::authorize('manageReceivers', SupportTicket::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:190'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'scope' => ['required', Rule::in([SupportTicketReceiver::SCOPE_GLOBAL, SupportTicketReceiver::SCOPE_SS])],
            'cen_hie_dependency_id' => [
                Rule::requiredIf(fn () => $request->input('scope') === SupportTicketReceiver::SCOPE_SS),
                'nullable',
                'integer',
                Rule::exists('cen_hie_dependencies', 'id')->where(
                    fn ($query) => $query->where('tipo_dependencia', 'SERVICIO'),
                ),
            ],
        ]);

        /** @var string $term */
        $term = isset($validated['q']) ? trim($validated['q']) : '';

        /** @var int $page */
        $page = (int) ($validated['page'] ?? 1);

        if (mb_strlen($term) < 2) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        if ($validated['scope'] === SupportTicketReceiver::SCOPE_SS) {
            $cenId = (int) $validated['cen_hie_dependency_id'];
            $ss = CenHieDependency::query()->find($cenId);
            if ($ss === null || ! $ss->habilitado) {
                return response()->json([
                    'results' => [],
                    'pagination' => ['more' => false],
                ]);
            }
        }

        $perPage = 30;

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query = User::query()
            ->with('role')
            ->where(function ($w) use ($like): void {
                $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->orderBy('id');

        if ($validated['scope'] === SupportTicketReceiver::SCOPE_GLOBAL) {
            $query->whereHas('role', fn ($r) => $r->where('name', 'admin'));
        } else {
            $query->whereHas('role', fn ($r) => $r->where('name', 'control-gestion'))
                ->where('cen_hie_dependency_id', (int) $validated['cen_hie_dependency_id']);
        }

        $users = (clone $query)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get(['id', 'name', 'email', 'role_id']);

        $hasMore = $users->count() > $perPage;
        $pageRows = $users->take($perPage);

        $results = [];
        foreach ($pageRows as $user) {
            $roleLabel = $user->role?->display_name ?? $user->role?->name ?? '—';

            $results[] = [
                'id' => (string) $user->getKey(),
                'text' => $user->name.' ('.$roleLabel.') · '.$user->email,
            ];
        }

        return response()->json([
            'results' => $results,
            'pagination' => ['more' => $hasMore],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SupportTicketReceiver::class);

        $serviciosSalud = $this->enabledServiciosSalud();

        return view('centinela.support.receivers.form', [
            'receiver' => new SupportTicketReceiver,
            'selectedUser' => $this->resolveSelectedUser(null),
            'serviciosSalud' => $serviciosSalud,
        ]);
    }

    public function store(StoreSupportTicketReceiverRequest $request): RedirectResponse
    {
        Gate::authorize('create', SupportTicketReceiver::class);

        SupportTicketReceiver::query()->create($request->validated());

        return redirect()
            ->route('centinela.support.receivers.index')
            ->with('flash_support_success', 'Receptor registrado.');
    }

    public function edit(SupportTicketReceiver $supportTicketReceiver): View
    {
        Gate::authorize('update', $supportTicketReceiver);

        $serviciosSalud = $this->enabledServiciosSalud();

        return view('centinela.support.receivers.form', [
            'receiver' => $supportTicketReceiver,
            'selectedUser' => $this->resolveSelectedUser($supportTicketReceiver),
            'serviciosSalud' => $serviciosSalud,
        ]);
    }

    /**
     * Usuario preseleccionado en el formulario (solo uno; el resto vía AJAX).
     */
    private function resolveSelectedUser(?SupportTicketReceiver $receiver): ?User
    {
        $effectiveId = old('user_id');

        if ($effectiveId === null && $receiver?->exists) {
            $effectiveId = $receiver->user_id;
        }

        if ($effectiveId === null || $effectiveId === '') {
            return null;
        }

        return User::query()
            ->with('role')
            ->find((int) $effectiveId);
    }

    /**
     * Servicios de Salud configurados como habilitados en la plataforma.
     *
     * @return Collection<int, CenHieDependency>
     */
    private function enabledServiciosSalud(): Collection
    {
        return CenHieDependency::query()
            ->where('tipo_dependencia', 'SERVICIO')
            ->where('habilitado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function update(UpdateSupportTicketReceiverRequest $request, SupportTicketReceiver $supportTicketReceiver): RedirectResponse
    {
        Gate::authorize('update', $supportTicketReceiver);

        $supportTicketReceiver->update($request->validated());

        return redirect()
            ->route('centinela.support.receivers.index')
            ->with('flash_support_success', 'Receptor actualizado.');
    }

    public function destroy(SupportTicketReceiver $supportTicketReceiver): RedirectResponse
    {
        Gate::authorize('delete', $supportTicketReceiver);

        $supportTicketReceiver->delete();

        return redirect()
            ->route('centinela.support.receivers.index')
            ->with('flash_support_success', 'Receptor eliminado.');
    }
}
