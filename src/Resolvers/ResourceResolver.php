<?php

namespace Justbetter\StatamicStructuredData\Resolvers;

interface ResourceResolver
{
    public function resolveCurrent(): ?object;

    public function supports(mixed $item): bool;

    public function handle(mixed $item, ?string $resourceHandle = null): ?string;
}
