<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * E-posta şablonları ve sürümleri
 */
final class TemplateRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allWithLatestVersion(): array
    {
        $sql = 'SELECT t.*, tv.id AS version_id, tv.subject AS latest_subject, tv.version AS version_no
                FROM templates t
                LEFT JOIN template_versions tv ON tv.template_id = t.id
                AND tv.version = (
                    SELECT MAX(tv2.version) FROM template_versions tv2 WHERE tv2.template_id = t.id
                )
                ORDER BY t.updated_at DESC, t.id DESC';

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithLatestVersion(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tpl === false) {
            return null;
        }
        $vstmt = $this->db->prepare(
            'SELECT * FROM template_versions WHERE template_id = :tid ORDER BY version DESC LIMIT 1'
        );
        $vstmt->execute(['tid' => $id]);
        $ver = $vstmt->fetch(PDO::FETCH_ASSOC);
        $tpl['latest_version'] = $ver ?: null;

        return $tpl;
    }

    /**
     * @return int Yeni şablon id
     */
    public function create(
        string $name,
        string $category,
        ?string $description,
        string $subject,
        string $bodyHtml,
        string $bodyPlain,
        ?int $createdBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO templates (name, category, description, is_active, created_by)
             VALUES (:n, :c, :d, 1, :cb)'
        );
        $stmt->execute([
            'n' => $name,
            'c' => $category,
            'd' => $description,
            'cb' => $createdBy,
        ]);
        $tid = (int) $this->db->lastInsertId();

        $v = $this->db->prepare(
            'INSERT INTO template_versions (template_id, version, subject, body_html, body_plain)
             VALUES (:tid, 1, :sub, :html, :plain)'
        );
        $v->execute([
            'tid' => $tid,
            'sub' => $subject,
            'html' => $bodyHtml,
            'plain' => $bodyPlain,
        ]);

        return $tid;
    }

    public function updateMeta(int $id, string $name, string $category, ?string $description, bool $isActive): void
    {
        $stmt = $this->db->prepare(
            'UPDATE templates SET name = :n, category = :c, description = :d, is_active = :a, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'n' => $name,
            'c' => $category,
            'd' => $description,
            'a' => $isActive ? 1 : 0,
            'id' => $id,
        ]);
    }

    /**
     * En son sürüm içeriğini günceller (MVP: tek aktif sürüm güncellenir)
     */
    public function updateLatestVersion(int $templateId, string $subject, string $bodyHtml, string $bodyPlain): void
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM template_versions WHERE template_id = :tid ORDER BY version DESC LIMIT 1'
        );
        $stmt->execute(['tid' => $templateId]);
        $vid = $stmt->fetchColumn();
        if ($vid === false) {
            $ins = $this->db->prepare(
                'INSERT INTO template_versions (template_id, version, subject, body_html, body_plain)
                 VALUES (:tid, 1, :sub, :html, :plain)'
            );
            $ins->execute([
                'tid' => $templateId,
                'sub' => $subject,
                'html' => $bodyHtml,
                'plain' => $bodyPlain,
            ]);

            return;
        }
        $up = $this->db->prepare(
            'UPDATE template_versions SET subject = :sub, body_html = :html, body_plain = :plain WHERE id = :id'
        );
        $up->execute([
            'sub' => $subject,
            'html' => $bodyHtml,
            'plain' => $bodyPlain,
            'id' => (int) $vid,
        ]);
    }

    /**
     * Kampanya formu için: aktif şablon + son sürüm id
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForSelect(): array
    {
        $sql = 'SELECT t.id, t.name, tv.id AS version_id
                FROM templates t
                INNER JOIN template_versions tv ON tv.template_id = t.id
                WHERE t.is_active = 1
                AND tv.version = (SELECT MAX(v2.version) FROM template_versions v2 WHERE v2.template_id = t.id)
                ORDER BY t.name';

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLatestVersionId(int $templateId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM template_versions WHERE template_id = :tid ORDER BY version DESC LIMIT 1'
        );
        $stmt->execute(['tid' => $templateId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function countCampaignUsage(int $templateId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM campaigns WHERE template_id = :tid');
        $stmt->execute(['tid' => $templateId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $templateId): int
    {
        $stmt = $this->db->prepare('DELETE FROM templates WHERE id = :id');
        $stmt->execute(['id' => $templateId]);

        return $stmt->rowCount();
    }

    /**
     * @return array<string, mixed>|null subject, body_html, body_plain
     */
    public function getVersionById(int $versionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM template_versions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $versionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}
