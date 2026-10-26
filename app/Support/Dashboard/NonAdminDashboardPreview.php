<?php

namespace App\Support\Dashboard;

use App\Models\CenHieDependency;
use App\Models\User;

final class NonAdminDashboardPreview
{
    public static function resolveSsId(User $user): ?int
    {
        $querySs = request()->query('ss');
        if (is_numeric($querySs) && (int) $querySs > 0) {
            return (int) $querySs;
        }

        if ($user->cen_hie_dependency_id !== null) {
            return (int) $user->cen_hie_dependency_id;
        }

        $first = CenHieDependency::query()
            ->enabled()
            ->orderBy('codigo')
            ->value('id');

        return $first !== null ? (int) $first : null;
    }

    public static function fromRequest(User $user): ?int
    {
        if (! request()->boolean('preview') || ! $user->hasRole('admin')) {
            return null;
        }

        $ss = request()->query('ss');
        if (! is_numeric($ss) || (int) $ss <= 0) {
            return null;
        }

        return (int) $ss;
    }

    public static function querySuffix(?int $previewSsId): string
    {
        if ($previewSsId === null || $previewSsId <= 0) {
            return '';
        }

        return '?preview=1&ss='.$previewSsId;
    }

    public static function operativoTabUrl(?int $previewSsId): string
    {
        $params = ['tab' => 'operativo'];
        if ($previewSsId !== null && $previewSsId > 0) {
            $params['ss'] = $previewSsId;
        }

        return route('voyager.dashboard').'?'.http_build_query($params);
    }

    public static function ambitoDetailUrl(User $user, string $slug): string
    {
        $url = route('centinela.dashboard.detail', [
            'section' => 'ambitos',
            'slug' => $slug,
        ]);

        if ($user->hasRole('admin')) {
            $ssId = self::resolveSsId($user);
            if ($ssId !== null) {
                return $url.self::querySuffix($ssId);
            }
        }

        return $url;
    }

    public static function ambitoDetailLinksEnabled(): bool
    {
        return (bool) config('centinela.dashboard_non_admin.enabled', true);
    }
}
