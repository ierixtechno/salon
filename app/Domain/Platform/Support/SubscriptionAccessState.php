<?php

namespace App\Domain\Platform\Support;

/**
 * The result of resolving a tenant's current access level — shared between
 * EnforceSubscriptionAccess (the enforcement point), the account-access
 * landing page, and the renewal banner, so all three agree on exactly the
 * same state without re-deriving it independently.
 *
 * Deliberately holds only scalars, never Eloquent models — ResolveSubscription
 * AccessState caches this as a plain array (see toArray()/fromArray()).
 * Caching a hydrated object risks `__PHP_Incomplete_Class` on unserialize if
 * the cache is ever read from a PHP process/opcache state that doesn't agree
 * on the exact class shape (observed in practice across two PHP binaries on
 * the same machine) — plain scalars sidestep that class-resolution risk
 * entirely, which matters more here since this runs on shared hosting.
 */
final class SubscriptionAccessState
{
    public function __construct(
        public readonly string $level, // 'pending' | 'active' | 'grace' | 'blocked'
        public readonly ?string $planName = null,
        public readonly ?int $reminderDaysLeft = null,
        public readonly ?int $graceDaysLeft = null,
        public readonly ?int $daysSinceExpiry = null,
    ) {}

    public function isPending(): bool
    {
        return $this->level === 'pending';
    }

    public function isGrace(): bool
    {
        return $this->level === 'grace';
    }

    public function isBlocked(): bool
    {
        return $this->level === 'blocked';
    }

    public function showsReminderBanner(): bool
    {
        return $this->level === 'active' && $this->reminderDaysLeft !== null;
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'planName' => $this->planName,
            'reminderDaysLeft' => $this->reminderDaysLeft,
            'graceDaysLeft' => $this->graceDaysLeft,
            'daysSinceExpiry' => $this->daysSinceExpiry,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            level: $data['level'],
            planName: $data['planName'] ?? null,
            reminderDaysLeft: $data['reminderDaysLeft'] ?? null,
            graceDaysLeft: $data['graceDaysLeft'] ?? null,
            daysSinceExpiry: $data['daysSinceExpiry'] ?? null,
        );
    }
}
