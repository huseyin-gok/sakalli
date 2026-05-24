<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use App\Repositories\CredentialCaptureRepository;
use PDO;

/**
 * Rapor ekranı — özet sorgular ve CSV dışa aktarma iskeleti
 */
final class ReportController
{
    public function index(): void
    {
        require_any_role(['super_admin', 'security_manager', 'report_viewer']);

        $pdo = Database::connection();
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');

        $stmt = $pdo->prepare(
            'SELECT d.name AS department, COUNT(te.id) AS events
             FROM tracking_events te
             LEFT JOIN users u ON u.id = te.user_id
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE te.created_at BETWEEN :f AND :t
             GROUP BY d.name
             ORDER BY events DESC'
        );
        $stmt->execute(['f' => $from . ' 00:00:00', 't' => $to . ' 23:59:59']);
        $byDept = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $riskStmt = $pdo->query(
            'SELECT rs.level, COUNT(*) AS users_count, ROUND(AVG(rs.score), 1) AS avg_score
             FROM risk_scores rs
             GROUP BY rs.level
             ORDER BY FIELD(rs.level, \'critical\', \'high\', \'medium\', \'low\')'
        );
        $riskByLevel = $riskStmt->fetchAll(PDO::FETCH_ASSOC);

        $fsStmt = $pdo->prepare(
            'SELECT fs.id, fs.created_at, fs.answers_json, u.email,
                    COALESCE(NULLIF(u.display_name, \'\'), u.username) AS user_label,
                    c.name AS campaign_name, c.id AS campaign_id
             FROM form_submissions fs
             INNER JOIN users u ON u.id = fs.user_id
             INNER JOIN campaigns c ON c.id = fs.campaign_id
             WHERE fs.created_at BETWEEN :f AND :t
             ORDER BY fs.id DESC
             LIMIT 300'
        );
        $fsStmt->execute(['f' => $from . ' 00:00:00', 't' => $to . ' 23:59:59']);
        $formSubmissions = $fsStmt->fetchAll(PDO::FETCH_ASSOC);

        $credentialCaptures = (new CredentialCaptureRepository($pdo))->listBetweenDates(
            $from . ' 00:00:00',
            $to . ' 23:59:59',
            300
        );

        View::render('reports/index', [
            'title' => 'Raporlar',
            'by_department' => $byDept,
            'risk_by_level' => $riskByLevel,
            'form_submissions' => $formSubmissions,
            'credential_captures' => $credentialCaptures,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * CSV indirme — basit örnek
     */
    public function exportCsv(): void
    {
        require_any_role(['super_admin', 'security_manager', 'report_viewer']);

        $pdo = Database::connection();
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $stmt = $pdo->prepare(
            'SELECT u.email, te.event_type, te.created_at
             FROM tracking_events te
             INNER JOIN users u ON u.id = te.user_id
             WHERE te.created_at BETWEEN :f AND :t
             ORDER BY te.created_at DESC
             LIMIT 50000'
        );
        $stmt->execute(['f' => $from . ' 00:00:00', 't' => $to . ' 23:59:59']);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="olaylar.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['email', 'event_type', 'created_at']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}
