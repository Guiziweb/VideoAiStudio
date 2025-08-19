<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Video\Service;

interface VideoStatusSchedulerInterface
{
    /**
     * Schedule a status check for a video generation
     */
    public function schedule(int $generationId): void;
}
