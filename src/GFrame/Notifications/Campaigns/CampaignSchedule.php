<?php

namespace GFrame\Notifications\Campaigns;

final class CampaignSchedule
{
    // Original Base Confías conversion and daily/weekly UTC recurrence.
    public static function utc(string $raw, string $timezone = 'UTC'): ?string
    {
        $raw = trim(str_replace('T', ' ', $raw));
        if ($raw === '') return null;
        $date = new \DateTime($raw, new \DateTimeZone($timezone));
        $date->setTimezone(new \DateTimeZone('UTC'));
        return $date->format('Y-m-d H:i:s');
    }

    public static function next(string $type, string $baseAt): string
    {
        if (!in_array($type, ['daily', 'weekly'], true)) throw new \InvalidArgumentException('Invalid recurrence');
        $date = new \DateTime($baseAt, new \DateTimeZone('UTC'));
        $date->modify($type === 'daily' ? '+1 day' : '+7 days');
        return $date->format('Y-m-d H:i:s');
    }
}
