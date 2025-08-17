<?php

declare(strict_types=1);

namespace App\Video\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class SufficientTokens extends Constraint
{
    public string $message = 'app.ui.insufficient_balance';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
