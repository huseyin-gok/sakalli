<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use PDO;

/**
 * Hedef kullanıcı listesi
 */
final class UserController
{
    public function index(): void
    {
        require_any_role(['super_admin', 'security_manager']);

        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT u.id, u.email, u.display_name, u.is_active, d.name AS department
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             ORDER BY u.id DESC
             LIMIT 200'
        );
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('users/index', ['title' => 'Kullanıcılar', 'users' => $users]);
    }
}
