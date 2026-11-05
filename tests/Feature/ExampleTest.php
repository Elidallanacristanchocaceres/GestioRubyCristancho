<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * La raíz redirige al panel principal.
     */
    public function test_the_application_root_redirects_to_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/panel');
    }
}
