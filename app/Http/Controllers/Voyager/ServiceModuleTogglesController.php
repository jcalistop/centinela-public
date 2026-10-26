<?php

namespace App\Http\Controllers\Voyager;

use App\Livewire\ModulosPorServicio\Grid;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

class ServiceModuleTogglesController extends CentinelaVoyagerController
{
    /**
     * Renderiza la grilla Livewire en lugar del BREAD browse tradicional.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403, 'Unauthorized.');
        }
        abort_unless($user->hasPermission('browse_service_module_toggles'), 403);

        return app(Grid::class)();
    }

    /**
     * Vista CRUD inhabilitada. Redirige al index.
     */
    public function create(Request $request): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function store(Request $request): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Vista CRUD inhabilitada. Redirige al index.
     */
    public function show(Request $request, mixed $id): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Vista CRUD inhabilitada. Redirige al index.
     */
    public function edit(Request $request, mixed $id): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function update(Request $request, mixed $id): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function destroy(Request $request, mixed $id): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function restore(Request $request, mixed $id): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function remove_media(Request $request): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function order(Request $request)
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function update_order(Request $request): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function action(Request $request): RedirectResponse|Redirector
    {
        return $this->redirectToIndex();
    }

    /**
     * Accion CRUD inhabilitada. Redirige al index.
     */
    public function relation(Request $request)
    {
        return $this->redirectToIndex();
    }

    /**
     * Redirige al index de modulos-por-servicio con un mensaje informativo.
     */
    private function redirectToIndex(): RedirectResponse|Redirector
    {
        return redirect()->route('voyager.modulos-por-servicio.index')->with([
            'message' => 'La gestion de modulos se realiza desde la grilla de toggles.',
            'alert-type' => 'info',
        ]);
    }
}
