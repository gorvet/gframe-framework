<?php

namespace GFrame\Notifications\Campaigns\Contracts;

interface CampaignRepository
{
    public function create(array $campaign, array $recipients): int;
    public function find(int $campaignID, ?int $tenantID = null): ?array;
    public function paginate(int $page, int $perPage, ?int $tenantID = null, string $status = 'all'): array;
    public function updateStatus(int $campaignID, string $status, ?int $tenantID = null): bool;
    public function reserveRecipients(int $campaignID, int $limit): array;
    public function recoverRecipients(int $campaignID, int $seconds): int;
    public function markRecipientQueued(int $recipientID, int $jobs): void;
    public function markRecipientFailed(int $recipientID, string $error): void;
    public function refreshProgress(int $campaignID): array;
}
