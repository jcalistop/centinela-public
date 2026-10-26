<?php

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use Tests\TestCase;

class DeployOrchestratorBranchAlignmentTest extends TestCase
{
    /** @test */
    public function branch_mismatch_summary_when_checkout_differs_from_config(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.git_status_via_http' => true,
        ]);

        $orchestrator = new class extends DeployOrchestrator
        {
            public function currentCheckoutBranch(): ?string
            {
                return 'dev';
            }
        };

        $this->assertFalse($orchestrator->isCheckoutAlignedWithDeployBranch());
        $message = $orchestrator->branchMismatchSummary();
        $this->assertIsString($message);
        $this->assertStringContainsString('dev', $message);
        $this->assertStringContainsString('main', $message);
    }

    /** @test */
    public function branch_mismatch_summary_is_null_when_aligned(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.git_status_via_http' => true,
        ]);

        $orchestrator = new class extends DeployOrchestrator
        {
            public function currentCheckoutBranch(): ?string
            {
                return 'main';
            }
        };

        $this->assertTrue($orchestrator->isCheckoutAlignedWithDeployBranch());
        $this->assertNull($orchestrator->branchMismatchSummary());
    }

    /** @test */
    public function detached_head_is_treated_as_misaligned_when_git_readable(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.git_status_via_http' => true,
        ]);

        $orchestrator = new class extends DeployOrchestrator
        {
            public function currentCheckoutBranch(): ?string
            {
                return null;
            }
        };

        $this->assertFalse($orchestrator->isCheckoutAlignedWithDeployBranch());
        $this->assertStringContainsString('detached HEAD', (string) $orchestrator->branchMismatchSummary());
    }

    /** @test */
    public function detached_head_deferred_when_git_not_readable_via_http(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.git_status_via_http' => false,
        ]);

        $orchestrator = new class extends DeployOrchestrator
        {
            public function currentCheckoutBranch(): ?string
            {
                return null;
            }
        };

        $this->assertTrue($orchestrator->isCheckoutAlignedWithDeployBranch());
        $this->assertNull($orchestrator->branchMismatchSummary());
    }

    /** @test */
    public function detached_head_deferred_when_auto_on_and_git_not_readable_via_http(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.automatic_enabled' => true,
            'centinela.deploy.git_status_via_http' => false,
        ]);

        $orchestrator = new class extends DeployOrchestrator
        {
            public function currentCheckoutBranch(): ?string
            {
                return null;
            }
        };

        $this->assertTrue($orchestrator->isCheckoutAlignedWithDeployBranch());
        $this->assertNull($orchestrator->branchMismatchSummary());
    }
}
