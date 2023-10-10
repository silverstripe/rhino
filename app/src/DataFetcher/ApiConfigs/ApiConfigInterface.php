<?php

namespace App\DataFetcher\Apis;

use App\DataFetcher\Requesters\AbstractRequester;
use App\DataFetcher\Interfaces\TypeInterface;

interface ApiConfigInterface extends TypeInterface
{
    public function getCredentials(): string;

    public function getHeaders(AbstractRequester $requester, string $method): array;

    public function getCurlOptions(): array;

    public function deriveUrl(string $path): string;

    public function supportsPagination(string $path): bool;

    public function getPaginationOffsetInitial(): int;

    public function getPaginationOffsetIncrement(): int;

    public function getPaginationOffsetMaximum(): int;
}
