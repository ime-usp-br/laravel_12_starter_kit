<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test; // Importa o atributo Test
use Tests\TestCase;

class DetectBrowserLanguageMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // This might be needed if your test environment doesn't auto-discover
        // or if you have specific provider configurations for testing.
        // For this test, it's likely not strictly necessary as we are testing
        // middleware applied globally or to the 'web' group.
        return parent::getPackageProviders($app);
    }

    protected function defineAppRoutes(): void
    {
        // Define a rota de teste dentro do setup para que ela exista
        // quando o middleware for aplicado globalmente ou no grupo 'web'.
        Route::get('/_test-locale-middleware', function () {
            return response('Current locale: '.App::getLocale(), 200);
        })->middleware('web'); // Assegura que o grupo 'web' (com o middleware) é usado.
    }

    #[Test]
    public function middleware_is_registered_and_allows_request_to_pass(): void
    {
        $this->defineAppRoutes();

        // Configuração mínima de locales para o teste de registro.
        // A lógica completa de detecção será testada no AC9.
        Config::set('app.supported_locales', ['en' => 'English', 'pt_BR' => 'Português (Brasil)']);
        Config::set('app.fallback_locale', 'en');

        // Faz uma requisição para a rota de teste
        $response = $this->get('/_test-locale-middleware');

        // Verifica se a resposta foi OK (status 200)
        // Isso implica que o middleware foi chamado e permitiu a passagem da requisição.
        // O locale padrão será 'en' (fallback ou default da config) se nada for detectado ou na sessão.
        $response->assertStatus(200)
            ->assertSee('Current locale: en'); // Assumindo que 'en' é o fallback/default
    }

    /**
     * Testa se o middleware define o locale para 'pt_BR' quando o header Accept-Language é 'pt-br'.
     * Este teste prepara o terreno para o AC4 e AC9.
     */
    #[Test]
    public function middleware_sets_locale_to_pt_br_based_on_browser_header_if_session_empty(): void
    {
        $this->defineAppRoutes();
        Config::set('app.supported_locales', ['en' => 'English', 'pt_BR' => 'Português (Brasil)']);
        Config::set('app.fallback_locale', 'en');
        Session::forget('locale'); // Garante que não há locale na sessão

        $response = $this->withHeaders([
            'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->get('/_test-locale-middleware');

        $response->assertStatus(200)->assertSee('Current locale: pt_BR');
        $this->assertEquals('pt_BR', App::getLocale());
        $this->assertEquals('pt_BR', Session::get('locale'));
    }

    /**
     * Testa se o middleware define o locale para 'en' quando o header Accept-Language é 'en'.
     * Este teste prepara o terreno para o AC5 e AC9.
     */
    #[Test]
    public function middleware_sets_locale_to_en_based_on_browser_header_if_session_empty(): void
    {
        $this->defineAppRoutes();
        Config::set('app.supported_locales', ['en' => 'English', 'pt_BR' => 'Português (Brasil)']);
        Config::set('app.fallback_locale', 'pt_BR'); // Mudar fallback para testar 'en'
        Session::forget('locale');

        $response = $this->withHeaders([
            'Accept-Language' => 'en,en-US;q=0.9',
        ])->get('/_test-locale-middleware');

        $response->assertStatus(200)->assertSee('Current locale: en');
        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('en', Session::get('locale'));
    }

    /**
     * Testa se o middleware usa o fallback_locale quando o idioma do navegador não é suportado.
     * Este teste prepara o terreno para o AC6 e AC9.
     */
    #[Test]
    public function middleware_uses_fallback_locale_if_browser_language_not_supported(): void
    {
        $this->defineAppRoutes();
        Config::set('app.supported_locales', ['en' => 'English', 'pt_BR' => 'Português (Brasil)']);
        Config::set('app.fallback_locale', 'en');
        Session::forget('locale');

        $response = $this->withHeaders([
            'Accept-Language' => 'fr-FR,fr;q=0.9', // Francês não é suportado
        ])->get('/_test-locale-middleware');

        $response->assertStatus(200)->assertSee('Current locale: en'); // Espera o fallback
        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('en', Session::get('locale'));
    }

    /**
     * Testa se o middleware prioriza o locale da sessão sobre a detecção do navegador.
     * Este teste prepara o terreno para o AC7, AC8 e AC9.
     */
    #[Test]
    public function middleware_prioritizes_session_locale_over_browser_detection(): void
    {
        $this->defineAppRoutes();
        Config::set('app.supported_locales', ['en' => 'English', 'pt_BR' => 'Português (Brasil)']);
        Config::set('app.fallback_locale', 'en');
        Session::put('locale', 'pt_BR'); // Define um locale na sessão

        // Envia um header que resultaria em 'en', mas a sessão deve prevalecer
        $response = $this->withHeaders([
            'Accept-Language' => 'en,en-US;q=0.9',
        ])->get('/_test-locale-middleware');

        $response->assertStatus(200)->assertSee('Current locale: pt_BR');
        $this->assertEquals('pt_BR', App::getLocale());
        $this->assertEquals('pt_BR', Session::get('locale')); // Garante que a sessão não foi sobrescrita indevidamente
    }

    /**
     * Testa se um locale inválido na sessão é ignorado e a detecção do navegador ocorre.
     */
    #[Test]
    public function middleware_ignores_invalid_session_locale_and_detects_browser_language(): void
    {
        $this->defineAppRoutes();
        Config::set('app.supported_locales', ['en' => 'English', 'pt_BR' => 'Português (Brasil)']);
        Config::set('app.fallback_locale', 'en');
        Session::put('locale', 'xx_XX'); // Locale inválido/não suportado na sessão

        $response = $this->withHeaders([
            'Accept-Language' => 'pt-BR,pt;q=0.9',
        ])->get('/_test-locale-middleware');

        // Espera que o locale pt_BR (do header) seja aplicado
        $response->assertStatus(200)->assertSee('Current locale: pt_BR');
        $this->assertEquals('pt_BR', App::getLocale());
        $this->assertEquals('pt_BR', Session::get('locale')); // Sessão deve ser atualizada
    }
}