<?php

namespace Tests\Feature;

use Tests\TestCase;

class CentinelaLegalDocTest extends TestCase
{
    /** @test */
    public function terminos_de_uso_page_renders(): void
    {
        $this->get('/legal/terminos-de-uso')
            ->assertOk()
            ->assertSee('Términos de uso', false)
            ->assertSee('SS de prueba', false)
            ->assertDontSee('Reloncaví', false)
            ->assertDontSee('{{instance.', false);
    }

    /** @test */
    public function politica_de_privacidad_page_renders(): void
    {
        $this->get('/legal/politica-de-privacidad')
            ->assertOk()
            ->assertSee('Política de privacidad', false)
            ->assertSee('Co-responsables del tratamiento', false)
            ->assertSee('SS de prueba', false)
            ->assertDontSee('Reloncaví', false)
            ->assertDontSee('{{instance.', false);
    }

    /** @test */
    public function legal_pages_reflect_instance_display_name_from_config(): void
    {
        config(['centinela.instance.ss_display_name' => 'SS Legal Lab']);

        $this->get('/legal/terminos-de-uso')
            ->assertOk()
            ->assertSee('SS Legal Lab', false)
            ->assertDontSee('SS de prueba', false);
    }

    /** @test */
    public function unknown_legal_path_returns_not_found(): void
    {
        $this->get('/legal/no-existe')->assertNotFound();
    }

    /** @test */
    public function legal_pages_return_not_found_when_login_legal_documents_disabled(): void
    {
        config()->set('centinela.login_legal_documents.enabled', false);

        $this->get('/legal/terminos-de-uso')->assertNotFound();
        $this->get('/legal/politica-de-privacidad')->assertNotFound();
    }
}
