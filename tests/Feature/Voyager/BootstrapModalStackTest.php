<?php

namespace Tests\Feature\Voyager;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BootstrapModalStackTest extends TestCase
{
    public function test_custom_css_defines_centinela_modal_stack_tokens(): void
    {
        $css = File::get(public_path('css/custom.css'));

        $this->assertStringContainsString('--centinela-modal-backdrop-z:', $css);
        $this->assertStringContainsString('--centinela-modal-z:', $css);
        $this->assertStringContainsString('body.centinela-bootstrap-modal-open .modal-backdrop', $css);
        $this->assertStringContainsString('body.centinela-bootstrap-modal-open .modal.in', $css);
    }

    public function test_custom_css_restyles_btn_default_with_brand_tokens(): void
    {
        $css = File::get(public_path('css/custom.css'));

        $this->assertStringContainsString('--centinela-btn-default-bg:', $css);
        $this->assertStringContainsString('--centinela-btn-default-border:', $css);
        $this->assertStringContainsString('.voyager .btn.btn-default', $css);
        $this->assertStringContainsString('.voyager .btn.btn-default.btn-sm', $css);
        $this->assertStringContainsString('var(--centinela-btn-default-bg)', $css);
    }

    public function test_bootstrap_modal_stack_partial_registers_modal_ids(): void
    {
        $partial = File::get(resource_path('views/partials/voyager/bootstrap-modal-stack.blade.php'));

        $this->assertStringContainsString('__centinelaBootstrapModalIds', $partial);
        $this->assertStringContainsString('centinela-bootstrap-modal-open', $partial);
        $this->assertStringContainsString('ensureCentinelaBootstrapModalsInBody', $partial);
        $this->assertStringContainsString('__centinelaBootstrapModalAutoRegister', $partial);
        $this->assertStringContainsString('registerAllCentinelaBootstrapModals', $partial);
    }

    public function test_voyager_master_auto_registers_all_bootstrap_modals(): void
    {
        $master = File::get(resource_path('views/vendor/voyager/master.blade.php'));

        $this->assertStringContainsString('partials.voyager.bootstrap-modal-stack', $master);
        $this->assertStringContainsString("'autoRegisterAll' => true", $master);
    }

    public function test_medios_verificacion_edit_includes_bootstrap_modal_stack(): void
    {
        $view = File::get(resource_path('views/vendor/voyager/medios-verificacion/edit-add.blade.php'));

        $this->assertStringContainsString('partials.voyager.bootstrap-modal-stack', $view);
        $this->assertStringContainsString('#confirm_delete_modal', $view);
    }

    public function test_indicator_data_modal_stack_delegates_to_unified_partial(): void
    {
        $partial = File::get(resource_path('views/partials/metas/indicator-data-modal-bootstrap-stack.blade.php'));

        $this->assertStringContainsString('partials.voyager.bootstrap-modal-stack', $partial);
        $this->assertStringContainsString('#indicatorDataModal', $partial);
    }
}
