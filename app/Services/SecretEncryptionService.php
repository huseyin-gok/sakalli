<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Entegrasyon JSON blob'larını şifreler.
 * Öncelik: .env SETTINGS_ENCRYPTION_KEY veya APP_SECRET (SHA-256 → 32 bayt).
 * Yoksa: storage/secrets/app_encryption.key (ilk kayıtta otomatik 32 bayt üretilir).
 */
final class SecretEncryptionService
{
    private const PREFIX_SODIUM = 's1:';

    private const PREFIX_OPENSSL = 'o1:';

    private const KEY_FILE = 'app_encryption.key';

    public static function isKeyConfigured(): bool
    {
        if (self::hasEnvKey()) {
            return true;
        }

        $path = self::keyFilePath();
        if (is_file($path) && filesize($path) >= 32) {
            return true;
        }

        return self::tryCreateKeyFile();
    }

    public static function keySourceDescription(): string
    {
        if (self::hasEnvKey()) {
            return '.env (SETTINGS_ENCRYPTION_KEY veya APP_SECRET)';
        }
        if (is_file(self::keyFilePath()) && filesize(self::keyFilePath()) >= 32) {
            return 'storage/secrets/' . self::KEY_FILE . ' (otomatik)';
        }

        return 'henüz yok — storage/secrets yazılabilir olmalı veya .env anahtarı ekleyin';
    }

    /**
     * @throws RuntimeException
     */
    public static function deriveKey32(): string
    {
        if (self::hasEnvKey()) {
            $raw = trim((string) ($_ENV['SETTINGS_ENCRYPTION_KEY'] ?? $_ENV['APP_SECRET'] ?? ''));

            return hash('sha256', $raw, true);
        }

        $path = self::keyFilePath();
        if (!is_file($path) || filesize($path) < 32) {
            if (!self::tryCreateKeyFile()) {
                throw new RuntimeException(
                    'Şifreleme anahtarı oluşturulamadı. '
                    . 'storage/secrets klasörüne yazma izni verin veya .env içinde SETTINGS_ENCRYPTION_KEY / APP_SECRET tanımlayın.'
                );
            }
        }
        $bytes = file_get_contents($path, false, null, 0, 32);
        if ($bytes === false || strlen($bytes) !== 32) {
            throw new RuntimeException('Şifreleme anahtar dosyası okunamadı: ' . $path);
        }

        return $bytes;
    }

    /**
     * @throws RuntimeException
     */
    public static function encrypt(string $plain): string
    {
        $key = self::deriveKey32();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
            if ($cipher === false) {
                throw new RuntimeException('sodium_crypto_secretbox başarısız.');
            }

            return self::PREFIX_SODIUM . base64_encode($nonce . $cipher);
        }

        return self::PREFIX_OPENSSL . self::encryptOpenssl($plain, $key);
    }

    public static function decrypt(string $blob): ?string
    {
        if ($blob === '') {
            return null;
        }
        try {
            $key = self::deriveKey32();
        } catch (RuntimeException) {
            return null;
        }
        if (str_starts_with($blob, self::PREFIX_SODIUM)) {
            return self::decryptSodium(substr($blob, strlen(self::PREFIX_SODIUM)), $key);
        }
        if (str_starts_with($blob, self::PREFIX_OPENSSL)) {
            return self::decryptOpenssl(substr($blob, strlen(self::PREFIX_OPENSSL)), $key);
        }

        return null;
    }

    private static function hasEnvKey(): bool
    {
        $a = trim((string) ($_ENV['SETTINGS_ENCRYPTION_KEY'] ?? ''));
        $b = trim((string) ($_ENV['APP_SECRET'] ?? ''));

        return $a !== '' || $b !== '';
    }

    private static function keyFilePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/secrets/' . self::KEY_FILE;
    }

    private static function tryCreateKeyFile(): bool
    {
        $path = self::keyFilePath();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return false;
        }
        if (!is_writable($dir)) {
            return false;
        }
        if (is_file($path) && filesize($path) >= 32) {
            return true;
        }
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tmp, random_bytes(32), LOCK_EX);
        if ($written !== 32) {
            @unlink($tmp);

            return false;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }
        @chmod($path, 0600);

        return true;
    }

    private static function decryptSodium(string $b64, string $key): ?string
    {
        if (!function_exists('sodium_crypto_secretbox_open')) {
            return null;
        }
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if ($plain === false) {
            return null;
        }

        return $plain;
    }

    /**
     * @throws RuntimeException
     */
    private static function encryptOpenssl(string $plain, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false || $tag === '') {
            throw new RuntimeException('openssl_encrypt (aes-256-gcm) başarısız.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    private static function decryptOpenssl(string $b64, string $key): ?string
    {
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) < 12 + 16) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            return null;
        }

        return $plain;
    }
}
