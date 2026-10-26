<?php

namespace Tests\Unit\Mail;

use App\Mail\UserCredentialsMail;
use App\Models\User;
use Tests\TestCase;

class UserCredentialsMailSerializationTest extends TestCase
{
    public function test_mailable_can_be_serialized_for_queue(): void
    {
        $user = new User([
            'name' => 'Usuario Prueba',
            'email' => 'prueba@minsal.cl',
        ]);

        $mailable = new UserCredentialsMail(
            $user,
            'Secreta123',
            'https://centinela.test/login',
            'Centinela',
        );

        serialize($mailable);

        $this->addToAssertionCount(1);
    }
}
