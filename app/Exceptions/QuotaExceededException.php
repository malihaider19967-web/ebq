<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown by UsageMeter::assertCanSpend when a user's plan cap for a paid
 * external API is exhausted. Rendered to JSON as a structured 402 so
 * both the WP plugin and the platform UI can show a friendly banner with
 * an Upgrade CTA.
 */
class QuotaExceededException extends HttpException
{
    public function __construct(
        public readonly string $provider,
        public readonly int $limit,
        public readonly int $used,
        public readonly string $userMessage,
        public readonly string $upgradeUrl,
    ) {
        parent::__construct(402, $userMessage);
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'error'       => 'quota_exceeded',
            'provider'    => $this->provider,
            'limit'       => $this->limit,
            'used'        => $this->used,
            'message'     => $this->userMessage,
            'upgrade_url' => $this->upgradeUrl,
        ];
    }
}
