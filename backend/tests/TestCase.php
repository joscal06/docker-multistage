<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prepara la siguiente petición para autenticarse con el token JWT dado.
     *
     * A diferencia de una petición HTTP real (proceso nuevo por petición),
     * varias llamadas dentro de un mismo test comparten el mismo contenedor
     * de la aplicación. Tanto el guard 'api' como el singleton `tymon.jwt`
     * cachean el usuario/token ya resuelto, por lo que —al simular más de un
     * usuario autenticado en un mismo test— hay que olvidar ambas cachés
     * antes de cada petición para que el token enviado se vuelva a parsear.
     */
    protected function withJwt(string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
