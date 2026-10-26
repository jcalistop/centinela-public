<?php

namespace App\Http\Controllers;

use App\Models\PublicInfoPanel;
use App\Models\User;
use App\Services\Dashboard\PublicInfoPanelAssetService;
use App\Support\Configuration\PublicDashboardGate;
use App\Support\Configuration\PublicSsDashboardGate;
use App\Support\Dashboard\PublicInfoPanelPreview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TCG\Voyager\Facades\Voyager;

class StorageController extends Controller
{
    public function showAvatar(Request $request): BinaryFileResponse
    {
        $filepath = $this->filepathFromRequest($request);
        $this->assertRelativePathSegmentSafe($filepath);

        $relativePath = 'users/'.$filepath;
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        $absolutePath = $disk->path($relativePath);

        return Response::file($absolutePath);
    }

    public function showSettings(Request $request): BinaryFileResponse
    {
        $filepath = $this->filepathFromRequest($request);
        $this->assertRelativePathSegmentSafe($filepath);

        $relativePath = 'settings/'.$filepath;
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        $absolutePath = $disk->path($relativePath);

        return Response::file($absolutePath);
    }

    /**
     * Logo público de Servicio de Salud (disco Voyager `private`, sin auth).
     * Requiere dashboard público + vitrina SS (SDD-282).
     */
    public function showServicioSaludLogo(Request $request): BinaryFileResponse|RedirectResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return redirect()->route('voyager.login');
        }

        if (! PublicSsDashboardGate::enabled()) {
            abort(404);
        }

        $filepath = $this->filepathFromRequest($request);
        $this->assertRelativePathSegmentSafe($filepath);

        $relativePath = str_replace('\\', '/', 'servicios-de-salud/'.$filepath);
        $this->assertRelativePathSegmentSafe($relativePath);

        if (! str_starts_with($relativePath, 'servicios-de-salud/')) {
            abort(403);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        if (! $disk->exists($relativePath)) {
            abort(404);
        }

        return Response::file($disk->path($relativePath));
    }

    /**
     * Assets tipados de paneles (HTML con &lt;base&gt; virtual / CSV / PNG).
     */
    public function showPublicInfoPanelAsset(Request $request, string $slug): BinaryFileResponse|RedirectResponse|StreamedResponse|SymfonyResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return redirect()->route('voyager.login');
        }

        $panel = $this->accessiblePublicInfoPanel($slug);
        if ($panel === null) {
            abort(404);
        }

        $asset = (string) $request->route('asset');

        return app(PublicInfoPanelAssetService::class)->serveTyped($panel, $asset);
    }

    /**
     * Rutas relativas pedidas por el HTML del panel (p. ej. datos.csv vía &lt;base&gt;).
     * No modifica archivos en disco: resuelve al html/csv/png asociados al panel.
     */
    public function showPublicInfoPanelRelative(Request $request, string $slug): BinaryFileResponse|RedirectResponse|StreamedResponse|SymfonyResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return redirect()->route('voyager.login');
        }

        $panel = $this->accessiblePublicInfoPanel($slug);
        if ($panel === null) {
            abort(404);
        }

        $path = (string) $request->route('path');

        return app(PublicInfoPanelAssetService::class)->serveRelative($panel, $path);
    }

    /**
     * Publicado en hub, o borrador con gate de sesión (SDD-293) o firma de asset (SDD-295).
     */
    private function accessiblePublicInfoPanel(string $slug): ?PublicInfoPanel
    {
        $published = PublicInfoPanel::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($published !== null) {
            return $published;
        }

        $request = request();
        $expires = $request->route('expires') ?? $request->query('expires');
        $signature = $request->route('signature') ?? $request->query('signature');

        $allowed = PublicInfoPanelPreview::allows($slug)
            || PublicInfoPanelPreview::validAssetSignature($slug, $signature, $expires);

        if (! $allowed) {
            return null;
        }

        $panel = PublicInfoPanel::query()
            ->where('slug', $slug)
            ->first();

        if ($panel === null || ! $panel->hasRequiredAssets()) {
            return null;
        }

        return $panel;
    }

    public function downloadFile(Request $request): BinaryFileResponse|StreamedResponse
    {
        $slug = (string) $request->route('slug', $request->slug);
        if (! in_array($slug, ['medios-verificacion', 'documentos', 'documents'], true)) {
            abort(404);
        }

        $resolved = $this->resolveVoyagerFileForRequest($request);
        if ($resolved === null) {
            abort(404);
        }

        ['absolutePath' => $absolutePath, 'originalName' => $originalName] = $resolved;

        return Response::download($absolutePath, $originalName);
    }

    /**
     * Sirve un PDF con cabecera inline (vista previa en modal o pestaña).
     *
     * Slugs admitidos: normativa (`documentos` / `documents`) y medios de verificación COMGES (`medios-verificacion`).
     */
    public function viewFileInline(Request $request): BinaryFileResponse
    {
        $slug = (string) $request->route('slug', $request->slug);
        if (! in_array($slug, ['documentos', 'documents', 'medios-verificacion'], true)) {
            abort(404);
        }

        $resolved = $this->resolveVoyagerFileForRequest($request);
        if ($resolved === null) {
            abort(404);
        }

        ['absolutePath' => $absolutePath, 'originalName' => $originalName] = $resolved;

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            abort(415);
        }

        return Response::file($absolutePath);
    }

    /**
     * @return array{absolutePath: string, originalName: string}|null
     */
    private function resolveVoyagerFileForRequest(Request $request): ?array
    {
        $slug = (string) $request->route('slug', $request->slug);

        $field = (string) $request->route('field', $request->field);
        if ($field === '' || ! preg_match('/^[a-z0-9_]+$/i', $field)) {
            return null;
        }

        $id = $request->route('id', $request->id);
        $fileIndexRaw = $request->route('file_index', $request->file_index);
        $fileIndex = filter_var($fileIndexRaw, FILTER_VALIDATE_INT);
        if ($fileIndex === false || $fileIndex < 0) {
            return null;
        }

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
        if ($dataType === null) {
            return null;
        }

        /** @var Model|null $dataTypeContent */
        $dataTypeContent = app($dataType->model_name)->find($id);
        if ($dataTypeContent === null) {
            return null;
        }

        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser instanceof User) {
            abort(401);
        }

        $isAuth = match ($slug) {
            'medios-verificacion' => $dataTypeContent->comAction->comIndicatorCutoff->comIndicator->comIndicatorSettings
                ->firstWhere('cen_hie_dependency_id', $authUser->cen_hie_dependency_id)?->users?->contains($authUser) ?? false,
            'documentos', 'documents' => $dataTypeContent instanceof Model
                && (int) $dataTypeContent->getAttribute('cen_hie_dependency_id') === (int) $authUser->cen_hie_dependency_id,
            default => false,
        };

        $roleName = $authUser->role?->name;
        if ($roleName === 'control-gestion' || $roleName === 'admin') {
            $isAuth = true;
        }

        if (! $isAuth) {
            abort(403, 'No está autorizado para la decarga de este archivo');
        }

        $raw = $dataTypeContent->getAttribute($field);
        $fileList = json_decode(is_string($raw) ? $raw : '', true);
        if (! is_array($fileList) || ! array_key_exists($fileIndex, $fileList)) {
            return null;
        }

        $entry = $fileList[$fileIndex];
        if (! is_array($entry)) {
            return null;
        }

        $downloadLink = $entry['download_link'] ?? null;
        $originalName = is_string($entry['original_name'] ?? null) ? $entry['original_name'] : 'archivo';

        if (! is_string($downloadLink) || $downloadLink === '') {
            return null;
        }

        $this->assertRelativePathSegmentSafe($downloadLink);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        $absolutePath = $disk->path($downloadLink);

        return [
            'absolutePath' => $absolutePath,
            'originalName' => $originalName,
        ];
    }

    private function filepathFromRequest(Request $request): string
    {
        $filepath = (string) ($request->route('filepath') ?? $request->filepath ?? '');

        return $filepath;
    }

    /**
     * Mitiga path traversal y rutas absolutas en rutas relativas al disco `private`.
     */
    private function assertRelativePathSegmentSafe(string $relativePath): void
    {
        if ($relativePath === '') {
            abort(403);
        }

        if (str_contains($relativePath, '..') || str_contains($relativePath, "\0")) {
            abort(403);
        }

        if (str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\')) {
            abort(403);
        }

        if (preg_match('#^[a-zA-Z]:[/\\\\]#', $relativePath) === 1) {
            abort(403);
        }
    }
}
