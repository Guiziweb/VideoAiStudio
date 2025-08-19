<?php

declare(strict_types=1);

namespace App\IA\Provider\Exception;

use RuntimeException;

/**
 * Base exception for video generation provider errors
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $providerType = null,
        private readonly ?string $externalJobId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getProviderType(): ?string
    {
        return $this->providerType;
    }

    public function getExternalJobId(): ?string
    {
        return $this->externalJobId;
    }
}
