<?php

declare(strict_types=1);

namespace App\Video;

interface VideoGenerationTransitions
{
    public const GRAPH = 'video_generation';

    // States - following Sylius pattern
    public const STATE_CREATED = 'created';

    public const STATE_SUBMITTED = 'submitted';

    public const STATE_PROCESSING = 'processing';

    public const STATE_COMPLETED = 'completed';

    public const STATE_FAILED = 'failed';

    public const STATE_REFUNDED = 'refunded';

    // Transitions
    public const TRANSITION_SUBMIT = 'submit';

    public const TRANSITION_START_PROCESSING = 'start_processing';

    public const TRANSITION_COMPLETE = 'complete';

    public const TRANSITION_FAIL = 'fail';

    public const TRANSITION_REFUND = 'refund';
}
