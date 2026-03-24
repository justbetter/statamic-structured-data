<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Fieldtypes;

use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataPreview;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StructuredDataPreviewTest extends TestCase
{
    #[Test]
    public function default_value_returns_null(): void
    {
        $fieldtype = new StructuredDataPreview;

        $_ = $fieldtype->defaultValue();

        $this->expectNotToPerformAssertions();
    }
}
