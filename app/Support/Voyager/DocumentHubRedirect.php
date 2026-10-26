<?php

namespace App\Support\Voyager;

use App\Models\Document;
use App\Models\IndicatorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Redirección tras mutar un documento desde un hub (COMGES, Metas, ADP), con fragmento a la pestaña correspondiente.
 */
final class DocumentHubRedirect
{
    /**
     * URL válida o null si debe usarse el redirect por defecto de Voyager.
     */
    public static function urlAfterStore(Request $request, mixed $document = null): ?string
    {
        return self::urlAfterMutation($request, $document);
    }

    /**
     * @param  array{hub?: string|null, metas_route?: string|null, indicator_type_id?: int|null}  $context
     */
    public static function hubUrlFromContext(array $context): ?string
    {
        $hub = $context['hub'] ?? null;
        if (! is_string($hub) || $hub === '') {
            return null;
        }

        return match ($hub) {
            'comges' => self::comgesDocumentsTabUrl(),
            'adp' => self::adpDocumentsTabUrl(),
            'metas' => self::metasDocumentsTabUrl((string) ($context['metas_route'] ?? '')),
            default => null,
        };
    }

    /**
     * @param  array{hub?: string|null, metas_route?: string|null, indicator_type_id?: int|null}  $context
     * @return array<string, int|string>
     */
    public static function queryParamsFromContext(array $context): array
    {
        $hub = $context['hub'] ?? null;
        if (! is_string($hub) || $hub === '') {
            return [];
        }

        $params = ['hub' => $hub];
        if (($context['indicator_type_id'] ?? null) !== null) {
            $params['indicator_type_id'] = (int) $context['indicator_type_id'];
        }
        if ($hub === 'metas' && is_string($context['metas_route'] ?? null) && $context['metas_route'] !== '') {
            $params['metas_route'] = $context['metas_route'];
        }

        return $params;
    }

    /**
     * @param  array{hub?: string|null, metas_route?: string|null, indicator_type_id?: int|null}  $context
     */
    public static function appendHubQueryToUrl(string $url, array $context): string
    {
        $params = self::queryParamsFromContext($context);
        if ($params === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($params);
    }

    /**
     * URL de retorno al hub tras crear, editar o eliminar un documento.
     */
    public static function urlAfterMutation(Request $request, mixed $document = null): ?string
    {
        $fromRequest = self::urlFromHubForm($request);
        if ($fromRequest !== null) {
            return $fromRequest;
        }

        if ($document instanceof Document && $document->indicator_type_id) {
            return self::urlForIndicatorTypeId((int) $document->indicator_type_id);
        }

        return null;
    }

    public static function urlForIndicatorTypeId(int $indicatorTypeId): ?string
    {
        $type = IndicatorType::query()->find($indicatorTypeId);

        return $type instanceof IndicatorType ? self::urlForIndicatorType($type) : null;
    }

    /**
     * @return array{hub: ?string, metas_route: ?string, indicator_type_id: ?int}
     */
    public static function contextFromIndicatorTypeId(int $indicatorTypeId): array
    {
        $empty = [
            'hub' => null,
            'metas_route' => null,
            'indicator_type_id' => null,
        ];

        if ($indicatorTypeId <= 0) {
            return $empty;
        }

        $type = IndicatorType::query()->find($indicatorTypeId);
        if (! $type instanceof IndicatorType) {
            return $empty;
        }

        $route = (string) $type->route;
        if ($route !== '' && preg_match('/^metas-[0-9]+$/', $route) === 1) {
            return [
                'hub' => 'metas',
                'metas_route' => $route,
                'indicator_type_id' => $indicatorTypeId,
            ];
        }

        if ($type->clave_indicador === 'adp') {
            return [
                'hub' => 'adp',
                'metas_route' => null,
                'indicator_type_id' => $indicatorTypeId,
            ];
        }

        return [
            'hub' => 'comges',
            'metas_route' => null,
            'indicator_type_id' => $indicatorTypeId,
        ];
    }

    private static function urlFromHubForm(Request $request): ?string
    {
        $hub = (string) $request->input('centinela_document_hub', '');
        if ($hub === '') {
            return null;
        }

        return match ($hub) {
            'comges' => self::comgesDocumentsTabUrl(),
            'adp' => self::adpDocumentsTabUrl(),
            'metas' => self::metasDocumentsTabUrl((string) $request->input('centinela_metas_route', '')),
            default => null,
        };
    }

    private static function urlForIndicatorType(IndicatorType $type): ?string
    {
        $route = (string) $type->route;
        if ($route !== '' && preg_match('/^metas-[0-9]+$/', $route) === 1) {
            return self::metasDocumentsTabUrl($route);
        }

        if ($type->clave_indicador === 'adp') {
            return self::adpDocumentsTabUrl();
        }

        return self::comgesDocumentsTabUrl();
    }

    private static function comgesDocumentsTabUrl(): ?string
    {
        $base = Route::has('voyager.compromisos.index')
            ? route('voyager.compromisos.index')
            : url('/admin/compromisos');

        return $base.'#documentos';
    }

    private static function adpDocumentsTabUrl(): ?string
    {
        if (! Route::has('adp.instrument.index')) {
            return null;
        }

        return route('adp.instrument.index').'#tab-documentos';
    }

    private static function metasDocumentsTabUrl(string $metasRoute): ?string
    {
        if ($metasRoute === '' || preg_match('/^metas-[0-9]+$/', $metasRoute) !== 1) {
            return null;
        }

        $type = IndicatorType::query()->where('route', $metasRoute)->first();
        if ($type === null) {
            return null;
        }

        if (! Route::has('metas.instrument.index')) {
            return null;
        }

        return route('metas.instrument.index', ['indicatorType' => $type]).'#tab-documentos';
    }
}
