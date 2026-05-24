<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class LandingPageRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allOrdered(): array
    {
        $sql = 'SELECT id, name, slug, page_title, show_feedback_form, credential_capture, created_at, updated_at
                FROM landing_pages ORDER BY name ASC';

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function listForSelect(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM landing_pages ORDER BY name ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM landing_pages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            $stmt = $this->db->prepare('SELECT 1 FROM landing_pages WHERE slug = :s LIMIT 1');
            $stmt->execute(['s' => $slug]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM landing_pages WHERE slug = :s AND id != :id LIMIT 1'
            );
            $stmt->execute(['s' => $slug, 'id' => $exceptId]);
        }

        return (bool) $stmt->fetchColumn();
    }

    public function create(
        string $name,
        string $slug,
        ?string $pageTitle,
        string $contentHtml,
        bool $showFeedbackForm,
        bool $credentialCapture
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO landing_pages (name, slug, page_title, content_html, show_feedback_form, credential_capture)
             VALUES (:n, :s, :pt, :ch, :sf, :cc)'
        );
        $stmt->execute([
            'n' => $name,
            's' => $slug,
            'pt' => $pageTitle !== null && $pageTitle !== '' ? $pageTitle : null,
            'ch' => $contentHtml,
            'sf' => $showFeedbackForm ? 1 : 0,
            'cc' => $credentialCapture ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $slug,
        ?string $pageTitle,
        string $contentHtml,
        bool $showFeedbackForm,
        bool $credentialCapture
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE landing_pages SET name = :n, slug = :s, page_title = :pt, content_html = :ch,
             show_feedback_form = :sf, credential_capture = :cc, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'n' => $name,
            's' => $slug,
            'pt' => $pageTitle !== null && $pageTitle !== '' ? $pageTitle : null,
            'ch' => $contentHtml,
            'sf' => $showFeedbackForm ? 1 : 0,
            'cc' => $credentialCapture ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM landing_pages WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
