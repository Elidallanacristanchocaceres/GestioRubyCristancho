<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz responde como API (la interfaz vive en la SPA Angular).
     */
    public function test_the_application_root_returns_api_info(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertJsonPath('app', 'GestioRubyCristancho API')
            ->assertJsonPath('api', url('/api'))
            ->assertJsonPath('docs', url('/docs'));
    }
}
