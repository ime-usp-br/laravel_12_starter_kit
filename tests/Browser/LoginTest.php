<?php

namespace Tests\Browser;

use App\Livewire\Forms\LoginForm;
use App\View\Components\GuestLayout;
use App\View\Components\usp\header as UspHeader; // Alias to avoid conflict
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\DuskTestCase;

/**
 * Tests for the Login functionality using Laravel Dusk.
 *
 * Corresponds to Issue #31.
 */
// Covers the Livewire Form Object and the Layout/Header Components used
#[CoversClass(LoginForm::class)]
#[CoversClass(GuestLayout::class)]
#[CoversClass(UspHeader::class)] // Use the alias
class LoginTest extends DuskTestCase
{
    use DatabaseMigrations; // Use migrations for Dusk tests if needed (e.g., if creating users)

    /**
     * Test if essential UI elements are present on the local login screen.
     *
     * This test covers AC8 of Issue #31.
     */
    #[Test]
    #[Group('auth')]
    #[Group('dusk')]
    public function local_login_screen_elements_are_present(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login/local') // Access the local login route
                ->assertVisible('@usp-logo') // Check for USP logo (in header)
                // Use assertPresent for logos that might depend on dark/light mode visibility
                ->assertPresent('@ime-logo-light')
                ->assertPresent('@ime-logo-dark')
                ->assertVisible('@email-input') // Check email input field
                ->assertVisible('@password-input') // Check password input field
                ->assertVisible('@login-button') // Check local "Log in" button
                ->assertSeeIn('@login-button', __('Log in')) // Verify text of local "Log in" button
                ->assertVisible('@senhaunica-login-button') // Check Senha Única button/link
                ->assertSeeIn('@senhaunica-login-button', __('Login with Senha Única USP')) // Verify text of Senha Única button/link
                ->assertVisible('@forgot-password-link') // Check "Forgot your password?" link
                ->assertSeeIn('@forgot-password-link', __('Forgot your password?')) // Verify text of "Forgot password" link
                ->assertVisible('@register-link') // Check "Register" link
                ->assertSeeIn('@register-link', __('Register')); // Verify text of "Register" link
        });
    }

    // AC9 to AC13 will be implemented in separate test methods later.
    // Example placeholder for AC9
    // #[Test]
    // #[Group('auth')]
    // #[Group('dusk')]
    // public function user_can_login_successfully_via_local_form(): void
    // {
    //     $user = \App\Models\User::factory()->create([
    //         'email' => 'taylor@laravel.com',
    //     ]);
    //
    //     $this->browse(function (Browser $browser) use ($user) {
    //         $browser->visit('/login/local')
    //                 ->type('@email-input', $user->email)
    //                 ->type('@password-input', 'password')
    //                 ->click('@login-button')
    //                 ->assertPathIs('/dashboard'); // Or your intended redirect path
    //     });
    // }
}
