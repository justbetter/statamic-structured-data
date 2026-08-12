<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables;

interface VariableProvider
{
    /**
     * @return array<int, mixed>
     */
    public function variables(mixed $parent = null): array;
}
