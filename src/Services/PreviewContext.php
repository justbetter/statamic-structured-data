<?php

namespace Justbetter\StatamicStructuredData\Services;

class PreviewContext
{
    /** @var array<string, mixed>|null */
    protected ?array $values = null;

    /**
     * @param  array<string, mixed>|null  $values
     */
    public function setValues(?array $values): void
    {
        $this->values = $values;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getValues(): ?array
    {
        return $this->values;
    }

    public function clear(): void
    {
        $this->values = null;
    }
}
