<?php
namespace GFrame\Notifications;

final class NotificationTime
{
    public static function relative(string $date, ?int $now = null): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) return '';
        $seconds = max(0, ($now ?? time()) - $timestamp);
        foreach ([31536000 => 'a', 2592000 => 'mes', 604800 => 'sem', 86400 => 'd', 3600 => 'h', 60 => 'min', 1 => 's'] as $unit => $label) {
            if ($seconds >= $unit || $unit === 1) return (int)floor($seconds / $unit) . ' ' . $label;
        }
        return '';
    }
}
