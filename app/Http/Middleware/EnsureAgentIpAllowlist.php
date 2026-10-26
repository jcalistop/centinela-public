<?php

namespace App\Http\Middleware;

use App\Support\Agent\AgentErrorEnvelope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $allowlist */
        $allowlist = config('centinela.agent_api.ip_allowlist', []);

        if ($allowlist === []) {
            return $next($request);
        }

        $ip = (string) $request->ip();

        foreach ($allowlist as $entry) {
            if ($this->matches($ip, $entry)) {
                return $next($request);
            }
        }

        return AgentErrorEnvelope::json(
            'AGENT_IP_DENIED',
            'IP no autorizada para la superficie de agentes.',
            403,
            ['ip' => $ip]
        );
    }

    private function matches(string $ip, string $entry): bool
    {
        if ($entry === $ip) {
            return true;
        }

        if (! str_contains($entry, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $entry, 2);
        $mask = (int) $mask;

        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || $mask < 0
            || $mask > 32
        ) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
