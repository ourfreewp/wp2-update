<?php

namespace WP2\Update\Core\Health;

use WP2\Update\Core\GitHub\ConnectionService;

class ConnectivityCheck {

    private ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService) {
        $this->connectionService = $connectionService;
    }

    public function get_status(): array {
        $connection_status = $this->connectionService->get_connection_status();

        return [
            'status'  => $connection_status['status'] ?? 'unknown',
            'message' => $connection_status['message'] ?? 'N/A',
        ];
    }
}