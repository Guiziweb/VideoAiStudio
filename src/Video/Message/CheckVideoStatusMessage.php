<?php

declare(strict_types=1);

namespace App\Video\Message;

/**
 * Message to check video generation status from external provider
 */
final class CheckVideoStatusMessage
{
    public function __construct(
        public readonly int $videoGenerationId,
    ) {
    }
}
