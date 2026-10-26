<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class AddSecurityHeadersTest extends TestCase
{
    public function test_login_incluye_headers_de_endurecimiento_y_csp_report_only(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy-Report-Only', AddSecurityHeaders::CSP_REPORT_ONLY);
        $this->assertStringContainsString('report-uri /csp-report', (string) $response->headers->get('Content-Security-Policy-Report-Only'));
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_csp_report_loguea_el_payload_y_responde_204(): void
    {
        $logger = \Mockery::mock();
        $logger->shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'csp-report'
                    && ($context['report']['csp-report']['violated-directive'] ?? null) === 'script-src';
            });

        Log::shouldReceive('channel')
            ->once()
            ->with('csp')
            ->andReturn($logger);

        $response = $this->postJson('/csp-report', [
            'csp-report' => [
                'document-uri' => 'https://example.test/admin',
                'violated-directive' => 'script-src',
            ],
        ]);

        $response->assertNoContent();
    }

    public function test_trust_hosts_rechaza_host_fuera_de_app_url(): void
    {
        $kernelSource = (string) file_get_contents(base_path('app/Http/Kernel.php'));
        $this->assertStringContainsString('TrustHosts::class', $kernelSource);
        $this->assertStringNotContainsString('// \\App\\Http\\Middleware\\TrustHosts::class', $kernelSource);

        $middleware = $this->app->make(TrustHosts::class);
        $request = Request::create('http://evil.example/login', 'GET');

        $this->expectException(SuspiciousOperationException::class);

        $middleware->handle($request, function (Request $req) {
            $req->getHost();

            return response('ok');
        });
    }

    public function test_security_audit_check_sigue_en_el_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('security:audit-check')
            ->assertSuccessful();
    }
}
