<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Volt\Volt;
use Mockery;
use Tests\Fakes\FakeSenhaunicaSocialiteProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        // Teste original do Breeze para garantir que a rota local exista
        $response = $this->get('/login/local');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_local_login_screen(): void
    {
        // Este teste atende ao Critério de Aceite 3 da Issue #31
        $user = User::factory()->create();

        // Testa o componente Livewire/Volt diretamente
        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password'); // Usa a senha padrão da factory

        // Chama a ação de login dentro do componente
        $component->call('login');

        // Verifica se não há erros de validação
        $component
            ->assertHasNoErrors()
            // Verifica se foi redirecionado para o dashboard
            ->assertRedirect(route('dashboard', absolute: false));

        // Verifica se o usuário está autenticado
        $this->assertAuthenticated();
    }

    public function test_email_must_be_a_valid_email_address_for_local_login(): void
    {
        // Este teste atende ao Critério de Aceite 4 da Issue #31
        Volt::test('pages.auth.login')
            ->set('form.email', 'invalid-email') // Formato inválido de e-mail
            ->set('form.password', 'password') // Senha qualquer, não será usada
            ->call('login')
            // Verifica especificamente o erro de validação da regra 'email' para o campo 'form.email'
            ->assertHasErrors(['form.email' => 'email'])
            // Garante que não há erros para o campo de senha neste cenário
            ->assertHasNoErrors(['form.password'])
            ->assertNoRedirect(); // Garante que não houve redirecionamento

        // Garante que o usuário não foi autenticado
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password_on_local_login(): void
    {
        // Este teste atende ao Critério de Aceite 5 da Issue #31
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password'); // Senha incorreta

        $component->call('login');

        // Verifica se há erro no campo de email (auth.failed é associado ao email geralmente)
        $component->assertHasErrors(['form.email' => trans('auth.failed')])
            // Garante que não há erro específico de validação de formato na senha neste caso
            ->assertHasNoErrors(['form.password']);

        $component->assertNoRedirect(); // Garante que não houve redirecionamento

        $this->assertGuest(); // Garante que o usuário não foi autenticado
    }

    public function test_users_can_not_authenticate_with_non_existent_credentials_on_local_login(): void
    {
        // Este teste atende ao Critério de Aceite 6 da Issue #31
        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'nonexistent@example.com') // Email válido em formato, mas não existente
            ->set('form.password', 'password'); // Senha qualquer

        $component->call('login');

        // Verifica se há erro no campo de email (auth.failed é associado ao email geralmente)
        $component->assertHasErrors(['form.email' => trans('auth.failed')])
            // Garante que não há erro específico de validação de formato na senha neste caso
            ->assertHasNoErrors(['form.password']);

        $component->assertNoRedirect(); // Garante que não houve redirecionamento

        $this->assertGuest(); // Garante que o usuário não foi autenticado
    }

    /**
     * Test if accessing the /login route triggers the Senhaunica Socialite redirect.
     *
     * @test
     * @group auth
     * @covers \Uspdev\SenhaunicaSocialite\Http\Controllers\SenhaunicaController::redirectToProvider
     * @see \Tests\Feature\Auth\AuthenticationTest::test_login_route_redirects_to_senhaunica_provider
     *
     * Acceptance Criteria 7 (AC7) from Issue #31:
     * - Teste verifica se o acesso à rota `/login` (botão Senha Única) invoca o método correto do `SenhaunicaController` (ex: `redirectToProvider`). (Pode exigir mock do Socialite).
     */
    public function test_login_route_redirects_to_senhaunica_provider(): void
    {
        // Arrange: Mock the Socialite facade to return our Fake Provider instance
        // Use the Fake Provider to simulate the redirect without needing Mockery directly on the driver
        // This ensures our SenhaunicaController calls Socialite::driver('senhaunica')->redirect()
        $fakeProvider = new FakeSenhaunicaSocialiteProvider(); // Instancia o Fake
        $fakeProvider->setRedirectUrl('https://expected-fake-redirect.usp.br'); // Define a URL esperada

        // Configura o Facade para retornar nossa instância fake quando o driver 'senhaunica' for chamado
        Socialite::shouldReceive('driver')
            ->with('senhaunica')
            ->once() // Garante que o driver foi chamado
            ->andReturn($fakeProvider); // Retorna nossa instância fake

        // Act: Make a GET request to the main login route (which now handles SenhaUnica)
        $response = $this->get(route('login')); // Use route() helper

        // Assert: Check if the response is a redirect to the URL defined in our Fake Provider
        $response->assertStatus(302);
        $response->assertRedirect('https://expected-fake-redirect.usp.br');

        // Mockery's expectation `->once()` verifies the driver was requested.
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}