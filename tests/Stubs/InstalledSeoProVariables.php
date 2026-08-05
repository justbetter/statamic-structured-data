<?php

namespace Justbetter\StatamicStructuredData\Tests\Stubs;

use Justbetter\StatamicStructuredData\Services\AvailableVariables\SeoProVariables;

class InstalledSeoProVariables extends SeoProVariables
{
    public function isInstalled(): bool
    {
        return true;
    }
}
