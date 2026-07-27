<?php

namespace App\Policies;

use App\Domain\Core\Models\GiftCard;
use App\Models\User;

class GiftCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gift-cards.view');
    }

    public function view(User $user, GiftCard $giftCard): bool
    {
        return $user->can('gift-cards.view') && $user->tenant_id === $giftCard->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('gift-cards.create');
    }

    public function update(User $user, GiftCard $giftCard): bool
    {
        return $user->can('gift-cards.cancel') && $user->tenant_id === $giftCard->tenant_id;
    }
}
