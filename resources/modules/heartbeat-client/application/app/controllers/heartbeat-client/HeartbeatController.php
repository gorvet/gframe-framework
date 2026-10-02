<?php

namespace GFrame\Modules\HeartbeatClient\Controllers;

class HeartbeatController extends \HeartbeatMaster
{
    public function __construct()
    {
        parent::__construct();
        $this->registerChannel('session', [
            'interval_ms' => 60000,
            'run_when_hidden' => true,
            'payload' => [],
        ], 'auth-ui/AuthController@heartbeatSessionChannel');
        $notifications = \GFrame\Modules\ModuleRuntime::file('controllers', 'notifications/NotificationController.php', 'notifications');
        if ($notifications !== null) {
            $this->registerChannel('notifications.inbox', [
                'interval_ms' => 60000,
                'run_when_hidden' => false,
                'payload' => ['limit' => 20],
            ], 'notifications/NotificationController@heartbeatInboxChannel');
        }
    }
}
