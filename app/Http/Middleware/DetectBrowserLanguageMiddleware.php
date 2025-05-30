<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class DetectBrowserLanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This middleware detects the user's preferred browser language and
     * sets the application locale accordingly, if the language is supported.
     * It prioritizes locales already set in the session.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $sessionKey = 'locale';
        $configSupportedLocalesKey = 'app.supported_locales';
        $configFallbackLocaleKey = 'app.fallback_locale';

        /** @var array<string, string> $supportedLocales */
        $supportedLocales = Config::get($configSupportedLocalesKey, []);
        $availableLocaleKeys = array_keys($supportedLocales);

        if (Session::has($sessionKey)) {
            $localeFromSession = Session::get($sessionKey);
            if (is_string($localeFromSession) && isset($supportedLocales[$localeFromSession])) {
                if (App::getLocale() !== $localeFromSession) {
                    App::setLocale($localeFromSession);
                }

                return $next($request);
            } else {
                Session::forget($sessionKey);
            }
        }

        $browserPreferredLang = $request->getPreferredLanguage($availableLocaleKeys);

        if ($browserPreferredLang && isset($supportedLocales[$browserPreferredLang])) {
            App::setLocale($browserPreferredLang);
            Session::put($sessionKey, $browserPreferredLang);
        } else {
            $fallbackLocaleValue = Config::get($configFallbackLocaleKey, 'en');

            if (is_string($fallbackLocaleValue) && isset($supportedLocales[$fallbackLocaleValue])) {
                App::setLocale($fallbackLocaleValue);
                Session::put($sessionKey, $fallbackLocaleValue);
            }
        }

        return $next($request);
    }
}
