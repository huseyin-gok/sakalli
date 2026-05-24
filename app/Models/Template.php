<?php

declare(strict_types=1);

namespace App\Models;

/**
 * E-posta şablonu — template_versions ile sürümlenir
 */
final class Template
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $category,
        public readonly bool $isActive
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            category: (string) $row['category'],
            isActive: (bool) ($row['is_active'] ?? true)
        );
    }
}
