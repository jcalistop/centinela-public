<?php

namespace Tests\Unit\Support\Mail;

use App\Mail\UserCredentialsMail;
use App\Models\User;
use App\Support\Mail\CentinelaEmailLogo;
use Tests\TestCase;

class CentinelaEmailLogoTest extends TestCase
{
    public function test_path_returns_readable_png_logo(): void
    {
        $path = CentinelaEmailLogo::path();

        $this->assertNotNull($path);
        $this->assertStringEndsWith('centinela_logo.png', $path);
        $this->assertFileIsReadable($path);
    }

    public function test_cid_src_uses_fixed_content_id(): void
    {
        $this->assertSame('cid:centinela-logo', CentinelaEmailLogo::cidSrc());
    }

    public function test_credentials_mail_html_references_fixed_cid(): void
    {
        $user = new User([
            'name' => 'Usuario Prueba',
            'email' => 'prueba@minsal.cl',
        ]);

        $html = (new UserCredentialsMail(
            $user,
            'Secreta123',
            'https://centinela.test/login',
            'Centinela',
        ))->render();

        $this->assertTrue(
            str_contains($html, 'src="cid:centinela-logo"')
                || str_contains($html, 'src="data:image/png;base64,'),
            'El HTML debe incluir logo incrustado (CID o base64 en vista previa).'
        );
    }
}
