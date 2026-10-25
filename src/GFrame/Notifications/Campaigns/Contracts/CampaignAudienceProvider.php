<?php

namespace GFrame\Notifications\Campaigns\Contracts;

interface CampaignAudienceProvider
{
    /** @return iterable<array{recipient:string, channel?:string, variables?:array<string,mixed>}|array{recipients:array<string,string>, variables?:array<string,mixed>}> */
    public function recipients(array $criteria): iterable;
}
