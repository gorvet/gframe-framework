<?php

final class HeartbeatController extends HeartbeatMaster
{
    public function __construct()
    {
        parent::__construct();
        $this->registerChannel('session', [
            'interval_ms' => 60000,
            'run_when_hidden' => true,
            'payload' => [],
        ], 'auth/AuthController@heartbeatSessionChannel');
        $notifications = ABSPATH . 'app/controllers/notifications/NotificationController.php';
        if (is_file($notifications)) {
            $this->registerChannel('notifications.inbox', [
                'interval_ms' => 60000,
                'run_when_hidden' => false,
                'payload' => ['limit' => 20],
            ], 'notifications/NotificationController@heartbeatInboxChannel');
        }
    }
}
