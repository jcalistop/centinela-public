<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeployOrchestratorEarlyExitLogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @param  callable(MessageLogged): bool  $predicate
     */
    private function assertEarlyExitLogged(string $level, callable $predicate): void
    {
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($level, $predicate): bool {
            if ($event->level !== $level || $event->message !== 'centinela.deploy.early_exit') {
                return false;
            }

            return $predicate($event);
        });
    }

    /** @test */
    public function run_auto_logs_early_exit_when_disabled(): void
    {
        Event::fake([MessageLogged::class]);
        config(['centinela.deploy.automatic_enabled' => false]);

        $result = app(DeployOrchestrator::class)->runAuto();

        $this->assertTrue($result['ok']);
        $this->assertNull($result['execution_id']);
        $this->assertSame('Auto deploy deshabilitado.', $result['summary']);
        $this->assertSame(0, DeployExecution::query()->count());
        $this->assertEarlyExitLogged('info', function (MessageLogged $event): bool {
            return ($event->context['reason'] ?? null) === 'auto_disabled'
                && ($event->context['mode'] ?? null) === 'auto'
                && ($event->context['ok'] ?? null) === true
                && ($event->context['execution_id'] ?? null) === null;
        });
    }

    /** @test */
    public function run_auto_logs_early_exit_when_blocked(): void
    {
        Event::fake([MessageLogged::class]);
        config(['centinela.deploy.automatic_enabled' => true]);
        Cache::put(DeployOrchestrator::SCHEDULE_LOCK_KEY, '1', now()->addMinutes(5));

        $result = app(DeployOrchestrator::class)->runAuto();

        $this->assertFalse($result['ok']);
        $this->assertSame(0, DeployExecution::query()->count());
        $this->assertEarlyExitLogged('warning', function (MessageLogged $event): bool {
            return ($event->context['reason'] ?? null) === 'auto_blocked'
                && ($event->context['mode'] ?? null) === 'auto'
                && ($event->context['ok'] ?? null) === false;
        });
    }

    /** @test */
    public function run_auto_logs_early_exit_when_already_ok_today(): void
    {
        Event::fake([MessageLogged::class]);
        config(['centinela.deploy.automatic_enabled' => true]);
        Cache::forget(DeployOrchestrator::SCHEDULE_LOCK_KEY);
        Cache::put('centinela:deploy:auto:last_success_date:v1', now()->toDateString(), now()->addDay());

        $result = app(DeployOrchestrator::class)->runAuto();

        $this->assertTrue($result['ok']);
        $this->assertSame(0, DeployExecution::query()->count());
        $this->assertEarlyExitLogged('info', function (MessageLogged $event): bool {
            return ($event->context['reason'] ?? null) === 'already_ok_today'
                && ($event->context['mode'] ?? null) === 'auto'
                && ($event->context['ok'] ?? null) === true;
        });
    }

    /** @test */
    public function run_manual_logs_early_exit_when_lock_busy_without_precreated(): void
    {
        Event::fake([MessageLogged::class]);
        config(['cache.default' => 'array']);

        $lock = Cache::lock('centinela:deploy:run:lock:v1', 600);
        $this->assertTrue($lock->get());

        try {
            /** @var DeployOrchestrator $orchestrator */
            $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
                $mock->shouldAllowMockingProtectedMethods();
                $mock->shouldReceive('shouldInvokeGitForStatusRead')->andReturn(false);
                $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
                $mock->shouldReceive('ciGateBlockSummary')->with('manual')->andReturn(null);
            });

            $actor = User::factory()->create();
            $result = $orchestrator->runManual($actor);

            $this->assertFalse($result['ok']);
            $this->assertNull($result['execution_id']);
            $this->assertSame('Otro deploy está en progreso.', $result['summary']);
            $this->assertSame(0, DeployExecution::query()->count());
            $this->assertEarlyExitLogged('warning', function (MessageLogged $event): bool {
                return ($event->context['reason'] ?? null) === 'lock_busy'
                    && ($event->context['mode'] ?? null) === 'manual'
                    && ($event->context['ok'] ?? null) === false
                    && ($event->context['execution_id'] ?? null) === null;
            });
        } finally {
            optional($lock)->release();
        }
    }
}
