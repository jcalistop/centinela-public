<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Support\Voyager\DocumentBread;

/**
 * Permisos Voyager del BREAD Documento (clave dinámica por `name` del DataType: {@see DocumentBread::permissionTableName()}).
 */
class DocumentPolicy
{
    public function browse(User $user, mixed $document = null): bool
    {
        return $this->hasVoyagerPermission($user, 'browse');
    }

    public function read(User $user, mixed $document = null): bool
    {
        return $this->hasVoyagerPermission($user, 'read');
    }

    public function edit(User $user, mixed $document = null): bool
    {
        return $this->allowsWriteAction($user, 'edit', $document);
    }

    public function add(User $user, mixed $document = null): bool
    {
        return $this->allowsWriteAction($user, 'add', $document);
    }

    public function delete(User $user, mixed $document = null): bool
    {
        return $this->allowsWriteAction($user, 'delete', $document);
    }

    /**
     * Usado por el listado BREAD para filas con soft deletes.
     */
    public function restore(User $user, mixed $document = null): bool
    {
        return $this->allowsWriteAction($user, 'delete', $document);
    }

    private function allowsWriteAction(User $user, string $action, mixed $document): bool
    {
        if ($this->hasVoyagerPermission($user, $action)) {
            return true;
        }

        if (! $this->canManageDocuments($user)) {
            return false;
        }

        if ($document instanceof Document) {
            return $this->canManageDocumentInService($user, $document);
        }

        return true;
    }

    private function canManageDocuments(User $user): bool
    {
        return $user->hasRole(['admin', 'control-gestion']);
    }

    private function canManageDocumentInService(User $user, Document $document): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return (int) $user->cen_hie_dependency_id === (int) $document->cen_hie_dependency_id;
    }

    private function hasVoyagerPermission(User $user, string $action): bool
    {
        return $user->hasPermission($action.'_'.DocumentBread::permissionTableName());
    }
}
