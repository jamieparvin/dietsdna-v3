<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_can_view_the_public_home_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertViewIs('home');
    }
}
