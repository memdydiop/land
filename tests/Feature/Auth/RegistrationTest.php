<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_registration_page_is_the_organization_onboarding_page(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSeeLivewire('pages::auth.register');
    }

    public function test_registration_component_renders(): void
    {
        Livewire::test('pages::auth.register')
            ->assertStatus(200)
            ->assertSee('Créer mon organisation');
    }
}
