<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Page as BasePage;

abstract class Page extends BasePage
{
    /**
     * Get the global element shortcuts for the site.
     */
    public static function siteElements(): array
    {
        return [
            '@element' => '#selector',
        ];
    }
}
