<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Kullanıcı veri erişimi — prepared statements
 */
final class UserRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        if ($email === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        if ($username === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * İlk LDAP girişinde yerel kayıt oluşturur (LDAP_AUTO_PROVISION=true ise)
     *
     * @param array<string, string|null> $ldapInfo username, email, first_name, last_name, display_name
     * @return array<string, mixed>|null Oluşturulan veya mevcut satır
     */
    public function createFromLdap(array $ldapInfo): ?array
    {
        $username = trim((string) ($ldapInfo['username'] ?? ''));
        $email = trim((string) ($ldapInfo['email'] ?? ''));
        if ($username === '' || $email === '') {
            return null;
        }

        $existing = $this->findByUsername($username) ?? $this->findByEmail($email);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, first_name, last_name, display_name, external_id, is_active, password_hash)
             VALUES (:u, :e, :fn, :ln, :dn, :xid, 1, NULL)'
        );
        $stmt->execute([
            'u' => $username,
            'e' => $email,
            'fn' => $ldapInfo['first_name'] ?: null,
            'ln' => $ldapInfo['last_name'] ?: null,
            'dn' => $ldapInfo['display_name'] ?: null,
            'xid' => $username,
        ]);
        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }

    /**
     * AD arama satırı ile kullanıcı oluşturur veya günceller (objectGUID → external_id öncelikli).
     *
     * @param array<string, mixed> $profile LdapAuthService::fetchUsersForOu satırı + isteğe bağlı email_domain_fallback
     * @return array{action: 'created'|'updated'|'skipped', user_id?: int, message?: string}
     */
    public function syncUserFromAdProfile(array $profile): array
    {
        $username = trim((string) ($profile['username'] ?? ''));
        $email = trim((string) ($profile['email'] ?? ''));
        $guidHex = trim((string) ($profile['object_guid_hex'] ?? ''));

        $domain = trim((string) ($profile['email_domain_fallback'] ?? ''));
        if ($domain !== '') {
            $domain = ltrim($domain, '@');
        }
        if ($email === '' && $username !== '' && $domain !== '') {
            $email = $username . '@' . $domain;
        }
        if ($username === '' || $email === '') {
            return ['action' => 'skipped', 'message' => 'eksik_kullanici_veya_eposta'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['action' => 'skipped', 'message' => 'gecersiz_eposta'];
        }

        $xidForNew = $guidHex !== '' ? $guidHex : $username;

        $existing = null;
        if ($guidHex !== '') {
            $st = $this->db->prepare('SELECT * FROM users WHERE external_id = :x LIMIT 1');
            $st->execute(['x' => $guidHex]);
            $existing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($existing === null) {
            $existing = $this->findByEmail($email);
        }
        if ($existing === null) {
            $existing = $this->findByUsername($username);
        }

        $fn = isset($profile['first_name']) && $profile['first_name'] !== '' ? (string) $profile['first_name'] : null;
        $ln = isset($profile['last_name']) && $profile['last_name'] !== '' ? (string) $profile['last_name'] : null;
        $dn = isset($profile['display_name']) && $profile['display_name'] !== '' ? (string) $profile['display_name'] : null;
        $jt = isset($profile['title']) && $profile['title'] !== '' ? (string) $profile['title'] : null;

        if ($existing !== null) {
            $uid = (int) $existing['id'];
            $newXid = $guidHex !== '' ? $guidHex : (string) ($existing['external_id'] ?? $username);
            if ($newXid === '') {
                $newXid = $username;
            }
            $up = $this->db->prepare(
                'UPDATE users SET first_name = :fn, last_name = :ln, display_name = :dn, job_title = :jt,
                 external_id = :xid, email = :e, updated_at = NOW() WHERE id = :id'
            );
            $up->execute([
                'id' => $uid,
                'fn' => $fn,
                'ln' => $ln,
                'dn' => $dn,
                'jt' => $jt,
                'xid' => $newXid,
                'e' => $email,
            ]);

            return ['action' => 'updated', 'user_id' => $uid];
        }

        $ins = $this->db->prepare(
            'INSERT INTO users (username, email, first_name, last_name, display_name, job_title, external_id, is_active, password_hash)
             VALUES (:u, :e, :fn, :ln, :dn, :jt, :xid, 1, NULL)'
        );
        $ins->execute([
            'u' => $username,
            'e' => $email,
            'fn' => $fn,
            'ln' => $ln,
            'dn' => $dn,
            'jt' => $jt,
            'xid' => $xidForNew,
        ]);
        $newId = (int) $this->db->lastInsertId();

        return ['action' => 'created', 'user_id' => $newId];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDepartments(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM departments ORDER BY name');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<int>
     */
    public function listActiveUserIds(): array
    {
        $stmt = $this->db->query('SELECT id FROM users WHERE is_active = 1');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /**
     * @return list<int>
     */
    public function listActiveUserIdsByDepartment(int $departmentId): array
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE is_active = 1 AND department_id = :d');
        $stmt->execute(['d' => $departmentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /**
     * Hedef seçim listesi (çoklu seçim)
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveUsersForPicker(int $limit = 2000): array
    {
        $lim = max(1, min(5000, $limit));
        $sql = 'SELECT id, email, COALESCE(NULLIF(display_name, \'\'), username) AS display_name
                FROM users WHERE is_active = 1 ORDER BY email LIMIT ' . $lim;

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * LDAP satırlarından (email / sAMAccountName) yerel aktif kullanıcı id listesi
     *
     * @param list<array<string, mixed>> $ldapRows
     * @return list<int>
     */
    public function matchActiveLocalIdsFromLdapProfiles(array $ldapRows): array
    {
        $ids = [];
        foreach ($ldapRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $u = $this->findByEmail($email);
                if ($u !== null && !empty($u['is_active'])) {
                    $ids[] = (int) $u['id'];
                    continue;
                }
            }
            $sam = trim((string) ($row['username'] ?? ''));
            if ($sam !== '') {
                $u = $this->findByUsername($sam);
                if ($u !== null && !empty($u['is_active'])) {
                    $ids[] = (int) $u['id'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Kampanya OU hedefi: yerelde varsa (aktif/pasif) id alınır; yoksa AD alanından kullanıcı oluşturulur.
     * Panele giriş OU kısıtı AuthController’da kalır; burada sadece hedef + e-posta gövdesi için kayıt açılır.
     *
     * @param list<array<string, mixed>> $ldapRows
     * @return array{ids: list<int>, skipped: int}
     */
    public function matchOrProvisionFromLdapOuForTargets(array $ldapRows, ?string $emailDomainFallback, string $roleSlug): array
    {
        $ids = [];
        $skipped = 0;
        $domain = $emailDomainFallback !== null ? trim($emailDomainFallback) : '';
        if ($domain !== '') {
            $domain = ltrim($domain, '@');
        }

        foreach ($ldapRows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $email = trim((string) ($row['email'] ?? ''));
            $sam = trim((string) ($row['username'] ?? ''));
            if ($email !== '') {
                $u = $this->findByEmail($email);
                if ($u !== null) {
                    $ids[] = (int) $u['id'];
                    continue;
                }
            }
            if ($sam !== '') {
                $u = $this->findByUsername($sam);
                if ($u !== null) {
                    $ids[] = (int) $u['id'];
                    continue;
                }
            }

            $ldapInfo = $this->normalizeLdapRowForOuProvision($row, $domain !== '' ? $domain : null);
            if ($ldapInfo === null) {
                $skipped++;
                continue;
            }
            $u = $this->createFromLdap($ldapInfo);
            if ($u === null) {
                $skipped++;
                continue;
            }
            $ids[] = (int) $u['id'];
            $this->assignRoleBySlug((int) $u['id'], $roleSlug);
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'skipped' => $skipped,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string|null>|null
     */
    private function normalizeLdapRowForOuProvision(array $row, ?string $emailDomain): ?array
    {
        $username = trim((string) ($row['username'] ?? ''));
        if ($username === '') {
            return null;
        }
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' && str_contains($username, '@')) {
            $email = $username;
        }
        if ($email === '' && $emailDomain !== null && $emailDomain !== '') {
            $email = $username . '@' . $emailDomain;
        }
        if ($email === '') {
            return null;
        }

        return [
            'username' => $username,
            'email' => $email,
            'first_name' => isset($row['first_name']) && $row['first_name'] !== '' ? (string) $row['first_name'] : null,
            'last_name' => isset($row['last_name']) && $row['last_name'] !== '' ? (string) $row['last_name'] : null,
            'display_name' => isset($row['display_name']) && $row['display_name'] !== '' ? (string) $row['display_name'] : null,
        ];
    }

    /**
     * Panelde gösterim için rol adları (veritabanındaki `roles.name`).
     *
     * @return list<string>
     */
    public function roleNamesForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.name FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :u
             ORDER BY r.name ASC'
        );
        $stmt->execute(['u' => $userId]);

        return array_values(array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        ));
    }

    /**
     * user_roles için rol atar (INSERT IGNORE).
     */
    public function assignRoleBySlug(int $userId, string $slug): void
    {
        $s = trim($slug);
        if ($s === '') {
            return;
        }
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE slug = :s LIMIT 1');
        $stmt->execute(['s' => $s]);
        $roleId = $stmt->fetchColumn();
        if ($roleId === false) {
            return;
        }
        $ins = $this->db->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)');
        $ins->execute(['u' => $userId, 'r' => (int) $roleId]);
    }
}
