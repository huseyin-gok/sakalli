<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Kullanıcı domain modeli — tablo alanları için tip güvenli sarmalayıcı (iskelet)
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly ?string $displayName,
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
            username: (string) $row['username'],
            email: (string) $row['email'],
            displayName: isset($row['display_name']) ? (string) $row['display_name'] : null,
            isActive: (bool) ($row['is_active'] ?? true)
        );
    }
}
