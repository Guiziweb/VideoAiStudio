<?php

/*
 * This file is part of the VideoAI Studio package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Video\Gateway;

use App\Video\Entity\VideoGeneration;

interface VideoProviderGatewayInterface
{
    /**
     * Submit a video generation to the external provider
     *
     * @return array{provider: string, job_id: string, metadata?: array<string, mixed>}|null
     */
    public function submitJob(VideoGeneration $generation): ?array;

    /**
     * Get current job status from provider
     * Returns workflow state constant (STATE_*)
     */
    public function getJobStatus(VideoGeneration $generation): ?string;

    /**
     * Get job result for completed generation
     *
     * @return array{video_url: string, metadata?: array<string, mixed>}|null
     */
    public function getJobResult(string $externalJobId): ?array;
}
