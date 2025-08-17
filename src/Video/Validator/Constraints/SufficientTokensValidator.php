<?php

declare(strict_types=1);

namespace App\Video\Validator\Constraints;

use App\Entity\Customer\Customer;
use App\Video\Service\VideoGenerationEligibilityChecker;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class SufficientTokensValidator extends ConstraintValidator
{
    public function __construct(
        private CustomerContextInterface $customerContext,
        private VideoGenerationEligibilityChecker $eligibilityChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SufficientTokens) {
            return;
        }

        $customer = $this->customerContext->getCustomer();
        if (!$customer instanceof Customer) {
            return;
        }

        if (!$this->eligibilityChecker->canGenerate($customer)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('prompt')
                ->addViolation();
        }
    }
}
