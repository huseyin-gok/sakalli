<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use PDO;

/**
 * Olay günlüğü (tracking_events)
 */
final class TrackingEventsController
{
    public function index(): void
    {
        require_any_role(['super_admin', 'security_manager', 'report_viewer']);
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT te.*, u.email AS user_email, c.name AS campaign_name
             FROM tracking_events te
             LEFT JOIN users u ON u.id = te.user_id
             LEFT JOIN campaigns c ON c.id = te.campaign_id
             ORDER BY te.id DESC
             LIMIT 200'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        View::render('events/index', [
            'title' => 'Olay günlüğü',
            'events' => $rows,
        ]);
    }
}
