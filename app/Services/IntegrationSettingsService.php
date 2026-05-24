<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\IntegrationSettingsRepository;
use JsonException;
use RuntimeException;

/**
 * LDAP ve SMTP: önce DB’deki şifreli paket, yoksa / bozuksa .env yedeği.
 */
final class IntegrationSettingsService
{
    /** @var array<string, string>|null */
    private static ?array $ldapCache = null;

    /** @var array<string, string>|null */
    private static ?array $smtpCache = null;

    public static function resetCache(): void
    {
        self::$ldapCache = null;
        self::$smtpCache = null;
    }

    /**
     * Çalışma anı LDAP_* değerleri (dize; filter_var ile bool sayı kullanılır).
     *
     * @return array<string, string>
     */
    public static function getLdapEnv(): array
    {
        if (self::$ldapCache !== null) {
            return self::$ldapCache;
        }
        $base = self::ldapDefaultsFromEnv();
        $repo = new IntegrationSettingsRepository();
        $blob = $repo->getBlob(IntegrationSettingsRepository::KEY_LDAP);
        if ($blob === null) {
            self::$ldapCache = $base;

            return self::$ldapCache;
        }
        $plain = SecretEncryptionService::decrypt($blob);
        if ($plain === null || $plain === '') {
            self::$ldapCache = $base;

            return self::$ldapCache;
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::$ldapCache = $base;

            return self::$ldapCache;
        }
        self::$ldapCache = self::mergeLdap($base, $data);

        return self::$ldapCache;
    }

    /**
     * Formda gösterim: parola alanları boş (yalnızca değiştirilirken doldurulur).
     *
     * @return array<string, string>
     */
    public static function getLdapFormValues(): array
    {
        $e = self::getLdapEnv();

        return array_merge($e, [
            'LDAP_BIND_PASSWORD' => '',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getSmtpEnv(): array
    {
        if (self::$smtpCache !== null) {
            return self::$smtpCache;
        }
        $base = self::smtpDefaultsFromEnv();
        $repo = new IntegrationSettingsRepository();
        $blob = $repo->getBlob(IntegrationSettingsRepository::KEY_SMTP);
        if ($blob === null) {
            self::$smtpCache = $base;

            return self::$smtpCache;
        }
        $plain = SecretEncryptionService::decrypt($blob);
        if ($plain === null || $plain === '') {
            self::$smtpCache = $base;

            return self::$smtpCache;
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::$smtpCache = $base;

            return self::$smtpCache;
        }
        self::$smtpCache = self::mergeSmtp($base, $data);

        return self::$smtpCache;
    }

    /**
     * @return array<string, string>
     */
    public static function getSmtpFormValues(): array
    {
        $e = self::getSmtpEnv();

        return array_merge($e, [
            'SMTP_PASSWORD' => '',
        ]);
    }

    public static function createLdapAuthService(): LdapAuthService
    {
        $e = self::getLdapEnv();

        return new LdapAuthService(
            host: $e['LDAP_HOST'],
            port: (int) $e['LDAP_PORT'],
            useTls: filter_var($e['LDAP_USE_TLS'], FILTER_VALIDATE_BOOLEAN),
            bindDn: $e['LDAP_BIND_DN'],
            bindPassword: $e['LDAP_BIND_PASSWORD'],
            baseDn: $e['LDAP_BASE_DN'],
            userFilterTemplate: $e['LDAP_USER_FILTER'],
            userFilterUpnTemplate: $e['LDAP_USER_FILTER_UPN'] !== ''
                ? $e['LDAP_USER_FILTER_UPN']
                : null
        );
    }

    /**
     * Kampanya özel gönderen adı; boşsa SMTP_FROM_NAME.
     */
    public static function resolveSmtpFromName(?string $campaignFromName): string
    {
        $custom = trim((string) $campaignFromName);
        if ($custom !== '') {
            return mb_substr($custom, 0, 191);
        }

        return trim((string) (self::getSmtpEnv()['SMTP_FROM_NAME'] ?? 'Sakallı'));
    }

    public static function createSmtpEmailServiceForQueue(): SmtpEmailService
    {
        $e = self::getSmtpEnv();

        return new SmtpEmailService(
            host: $e['SMTP_HOST'],
            port: (int) $e['SMTP_PORT'],
            encryption: $e['SMTP_ENCRYPTION'],
            username: $e['SMTP_USER'],
            password: $e['SMTP_PASSWORD'],
            fromAddress: $e['SMTP_FROM'],
            fromName: $e['SMTP_FROM_NAME']
        );
    }

    public static function createSmtpEmailServiceForTest(string $fromNameSuffix): SmtpEmailService
    {
        $e = self::getSmtpEnv();

        return new SmtpEmailService(
            host: $e['SMTP_HOST'],
            port: (int) $e['SMTP_PORT'],
            encryption: $e['SMTP_ENCRYPTION'],
            username: $e['SMTP_USER'],
            password: $e['SMTP_PASSWORD'],
            fromAddress: $e['SMTP_FROM'],
            fromName: $e['SMTP_FROM_NAME'] . $fromNameSuffix
        );
    }

    /**
     * @param array<string, mixed> $post $_POST
     *
     * @throws RuntimeException
     */
    public static function saveLdapFromPost(array $post): void
    {
        if (!SecretEncryptionService::isKeyConfigured()) {
            throw new RuntimeException(
                'Şifreleme anahtarı hazır değil. storage/secrets klasörüne yazma izni verin veya .env içinde SETTINGS_ENCRYPTION_KEY / APP_SECRET tanımlayın.'
            );
        }
        $current = self::getLdapEnv();
        $bindPw = trim((string) ($post['ldap_bind_password'] ?? ''));
        if ($bindPw === '') {
            $bindPw = $current['LDAP_BIND_PASSWORD'];
        }

        $data = [
            'host' => trim((string) ($post['ldap_host'] ?? '')),
            'port' => max(1, min(65535, (int) ($post['ldap_port'] ?? 389))),
            'use_tls' => !empty($post['ldap_use_tls']),
            'bind_dn' => trim((string) ($post['ldap_bind_dn'] ?? '')),
            'bind_password' => $bindPw,
            'base_dn' => trim((string) ($post['ldap_base_dn'] ?? '')),
            'user_filter' => trim((string) ($post['ldap_user_filter'] ?? '')),
            'user_filter_upn' => trim((string) ($post['ldap_user_filter_upn'] ?? '')),
            'panel_allowed_dn_substring' => trim((string) ($post['ldap_panel_allowed_dn_substring'] ?? '')),
            'auto_provision' => !empty($post['ldap_auto_provision']),
            'auto_provision_role' => trim((string) ($post['ldap_auto_provision_role'] ?? 'report_viewer')),
            'ou_search_base' => trim((string) ($post['ldap_ou_search_base'] ?? '')),
            'ou_name_filter' => trim((string) ($post['ldap_ou_name_filter'] ?? '')),
            'ou_target_role' => trim((string) ($post['ldap_ou_target_role'] ?? 'report_viewer')),
            'ou_target_email_domain' => trim((string) ($post['ldap_ou_target_email_domain'] ?? '')),
            'ou_fetch_limit' => max(1, min(100000, (int) ($post['ldap_ou_fetch_limit'] ?? 10000))),
        ];
        if ($data['user_filter'] === '') {
            $data['user_filter'] = '(&(objectClass=user)(sAMAccountName=%s))';
        }
        if ($data['user_filter_upn'] === '') {
            $data['user_filter_upn'] = '(&(objectClass=user)(userPrincipalName=%s))';
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $enc = SecretEncryptionService::encrypt($json);
        $repo = new IntegrationSettingsRepository();
        $repo->saveBlob(IntegrationSettingsRepository::KEY_LDAP, $enc);
        self::resetCache();
    }

    /**
     * @param array<string, mixed> $post
     *
     * @throws RuntimeException
     */
    public static function saveSmtpFromPost(array $post): void
    {
        if (!SecretEncryptionService::isKeyConfigured()) {
            throw new RuntimeException(
                'Şifreleme anahtarı hazır değil. storage/secrets klasörüne yazma izni verin veya .env içinde SETTINGS_ENCRYPTION_KEY / APP_SECRET tanımlayın.'
            );
        }
        $current = self::getSmtpEnv();
        $pw = trim((string) ($post['smtp_password'] ?? ''));
        if ($pw === '') {
            $pw = $current['SMTP_PASSWORD'];
        }
        $encType = strtolower(trim((string) ($post['smtp_encryption'] ?? 'tls')));
        if (!in_array($encType, ['tls', 'ssl', 'none'], true)) {
            $encType = 'tls';
        }

        $data = [
            'host' => trim((string) ($post['smtp_host'] ?? '')),
            'port' => max(1, min(65535, (int) ($post['smtp_port'] ?? 587))),
            'encryption' => $encType,
            'username' => trim((string) ($post['smtp_user'] ?? '')),
            'password' => $pw,
            'from' => trim((string) ($post['smtp_from'] ?? '')),
            'from_name' => trim((string) ($post['smtp_from_name'] ?? '')),
        ];
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $enc = SecretEncryptionService::encrypt($json);
        $repo = new IntegrationSettingsRepository();
        $repo->saveBlob(IntegrationSettingsRepository::KEY_SMTP, $enc);
        self::resetCache();
    }

    public static function hasStoredLdap(): bool
    {
        $repo = new IntegrationSettingsRepository();

        return $repo->getBlob(IntegrationSettingsRepository::KEY_LDAP) !== null;
    }

    public static function hasStoredSmtp(): bool
    {
        $repo = new IntegrationSettingsRepository();

        return $repo->getBlob(IntegrationSettingsRepository::KEY_SMTP) !== null;
    }

    /**
     * @return array<string, string>
     */
    private static function ldapDefaultsFromEnv(): array
    {
        return [
            'LDAP_HOST' => trim((string) ($_ENV['LDAP_HOST'] ?? '127.0.0.1')),
            'LDAP_PORT' => (string) (int) ($_ENV['LDAP_PORT'] ?? 389),
            'LDAP_USE_TLS' => filter_var($_ENV['LDAP_USE_TLS'] ?? 'false', FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'LDAP_BIND_DN' => trim((string) ($_ENV['LDAP_BIND_DN'] ?? '')),
            'LDAP_BIND_PASSWORD' => (string) ($_ENV['LDAP_BIND_PASSWORD'] ?? ''),
            'LDAP_BASE_DN' => trim((string) ($_ENV['LDAP_BASE_DN'] ?? '')),
            'LDAP_USER_FILTER' => trim((string) ($_ENV['LDAP_USER_FILTER'] ?? '(&(objectClass=user)(sAMAccountName=%s))')),
            'LDAP_USER_FILTER_UPN' => trim((string) ($_ENV['LDAP_USER_FILTER_UPN'] ?? '(&(objectClass=user)(userPrincipalName=%s))')),
            'LDAP_PANEL_ALLOWED_DN_SUBSTRING' => trim((string) ($_ENV['LDAP_PANEL_ALLOWED_DN_SUBSTRING'] ?? '')),
            'LDAP_AUTO_PROVISION' => filter_var($_ENV['LDAP_AUTO_PROVISION'] ?? 'false', FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'LDAP_AUTO_PROVISION_ROLE' => trim((string) ($_ENV['LDAP_AUTO_PROVISION_ROLE'] ?? 'report_viewer')),
            'LDAP_OU_SEARCH_BASE' => trim((string) ($_ENV['LDAP_OU_SEARCH_BASE'] ?? '')),
            'LDAP_OU_NAME_FILTER' => trim((string) ($_ENV['LDAP_OU_NAME_FILTER'] ?? '')),
            'LDAP_OU_TARGET_ROLE' => trim((string) ($_ENV['LDAP_OU_TARGET_ROLE'] ?? 'report_viewer')),
            'LDAP_OU_TARGET_EMAIL_DOMAIN' => trim((string) ($_ENV['LDAP_OU_TARGET_EMAIL_DOMAIN'] ?? '')),
            'LDAP_OU_FETCH_LIMIT' => (string) (int) ($_ENV['LDAP_OU_FETCH_LIMIT'] ?? 10000),
        ];
    }

    /**
     * @param array<string, string> $base
     * @param array<string, mixed> $db
     *
     * @return array<string, string>
     */
    private static function mergeLdap(array $base, array $db): array
    {
        $map = [
            'host' => 'LDAP_HOST',
            'port' => 'LDAP_PORT',
            'use_tls' => 'LDAP_USE_TLS',
            'bind_dn' => 'LDAP_BIND_DN',
            'bind_password' => 'LDAP_BIND_PASSWORD',
            'base_dn' => 'LDAP_BASE_DN',
            'user_filter' => 'LDAP_USER_FILTER',
            'user_filter_upn' => 'LDAP_USER_FILTER_UPN',
            'panel_allowed_dn_substring' => 'LDAP_PANEL_ALLOWED_DN_SUBSTRING',
            'auto_provision' => 'LDAP_AUTO_PROVISION',
            'auto_provision_role' => 'LDAP_AUTO_PROVISION_ROLE',
            'ou_search_base' => 'LDAP_OU_SEARCH_BASE',
            'ou_name_filter' => 'LDAP_OU_NAME_FILTER',
            'ou_target_role' => 'LDAP_OU_TARGET_ROLE',
            'ou_target_email_domain' => 'LDAP_OU_TARGET_EMAIL_DOMAIN',
            'ou_fetch_limit' => 'LDAP_OU_FETCH_LIMIT',
        ];
        $out = $base;
        foreach ($map as $jsonKey => $envKey) {
            if (!array_key_exists($jsonKey, $db)) {
                continue;
            }
            $v = $db[$jsonKey];
            if ($jsonKey === 'port' || $jsonKey === 'ou_fetch_limit') {
                $out[$envKey] = (string) (int) $v;
            } elseif ($jsonKey === 'use_tls' || $jsonKey === 'auto_provision') {
                $out[$envKey] = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            } else {
                $out[$envKey] = trim((string) $v);
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function smtpDefaultsFromEnv(): array
    {
        return [
            'SMTP_HOST' => trim((string) ($_ENV['SMTP_HOST'] ?? '127.0.0.1')),
            'SMTP_PORT' => (string) (int) ($_ENV['SMTP_PORT'] ?? 587),
            'SMTP_ENCRYPTION' => strtolower(trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? 'tls'))) ?: 'tls',
            'SMTP_USER' => trim((string) ($_ENV['SMTP_USER'] ?? '')),
            'SMTP_PASSWORD' => (string) ($_ENV['SMTP_PASSWORD'] ?? ''),
            'SMTP_FROM' => trim((string) ($_ENV['SMTP_FROM'] ?? 'noreply@localhost')),
            'SMTP_FROM_NAME' => trim((string) ($_ENV['SMTP_FROM_NAME'] ?? 'Sakallı')),
        ];
    }

    /**
     * @param array<string, string> $base
     * @param array<string, mixed> $db
     *
     * @return array<string, string>
     */
    private static function mergeSmtp(array $base, array $db): array
    {
        $map = [
            'host' => 'SMTP_HOST',
            'port' => 'SMTP_PORT',
            'encryption' => 'SMTP_ENCRYPTION',
            'username' => 'SMTP_USER',
            'password' => 'SMTP_PASSWORD',
            'from' => 'SMTP_FROM',
            'from_name' => 'SMTP_FROM_NAME',
        ];
        $out = $base;
        foreach ($map as $jsonKey => $envKey) {
            if (!array_key_exists($jsonKey, $db)) {
                continue;
            }
            $v = $db[$jsonKey];
            if ($jsonKey === 'port') {
                $out[$envKey] = (string) (int) $v;
            } elseif ($jsonKey === 'encryption') {
                $e = strtolower(trim((string) $v));
                $out[$envKey] = in_array($e, ['tls', 'ssl', 'none'], true) ? $e : 'tls';
            } else {
                $out[$envKey] = trim((string) $v);
            }
        }

        return $out;
    }
}
