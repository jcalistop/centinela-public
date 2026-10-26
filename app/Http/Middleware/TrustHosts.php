<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
        ];
    }

    /**
     * En PHPUnit el framework omite TrustHosts; se fuerza para cubrir el error path de SDD-217.
     */
    protected function shouldSpecifyTrustedHosts(): bool
    {
        if ($this->app->runningUnitTests()) {
            return true;
        }

        return parent::shouldSpecifyTrustedHosts();
    }
}
