<?php

namespace GFrame\Notifications\Campaigns\Contracts;

interface CampaignRecipientGuard
{
    public function allows(array $recipient, ?int $tenantID): bool;
}
