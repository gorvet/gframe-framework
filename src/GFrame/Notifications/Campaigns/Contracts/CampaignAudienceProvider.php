<?php

namespace GFrame\Notifications\Campaigns\Contracts;

interface CampaignAudienceProvider
{
    /** @return iterable<array{recipient:string, variables?:array<string,mixed>}> */
    public function recipients(array $criteria): iterable;
}
