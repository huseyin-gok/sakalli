<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Helpers\View;
use App\Middlewares\CsrfMiddleware;
use App\Services\AuditLogService;
use App\Services\IntegrationSettingsService;
use App\Services\SecretEncryptionService;
use RuntimeException;

/**
 * LDAP ve SMTP — DB’de şifreli paket; .env yedek kaynak.
 */
final class SettingsIntegrationsController
{
    public function ldapForm(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $v = IntegrationSettingsService::getLdapFormValues();
        View::render('settings/ldap', [
            'title' => 'LDAP / Active Directory',
            'csrf' => CsrfMiddleware::ensureToken(),
            'v' => $v,
            'saved' => isset($_GET['saved']),
            'error' => $_GET['error'] ?? null,
            'from_db' => IntegrationSettingsService::hasStoredLdap(),
            'encryption_ready' => SecretEncryptionService::isKeyConfigured(),
            'encryption_source' => SecretEncryptionService::keySourceDescription(),
        ]);
    }

    public function ldapSave(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        try {
            IntegrationSettingsService::saveLdapFromPost($_POST);
        } catch (RuntimeException $e) {
            header('Location: ' . url('/settings/ldap') . '?error=' . rawurlencode($e->getMessage()));
            return;
        }
        try {
            $audit = new AuditLogService(Database::connection());
            $audit->log(current_user_id(), 'settings_ldap_saved', 'integration', 'ldap', []);
        } catch (\Throwable) {
        }
        header('Location: ' . url('/settings/ldap') . '?saved=1');
    }

    public function smtpForm(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        $v = IntegrationSettingsService::getSmtpFormValues();
        View::render('settings/smtp', [
            'title' => 'SMTP (e-posta gönderimi)',
            'csrf' => CsrfMiddleware::ensureToken(),
            'v' => $v,
            'saved' => isset($_GET['saved']),
            'error' => $_GET['error'] ?? null,
            'from_db' => IntegrationSettingsService::hasStoredSmtp(),
            'encryption_ready' => SecretEncryptionService::isKeyConfigured(),
            'encryption_source' => SecretEncryptionService::keySourceDescription(),
        ]);
    }

    public function smtpSave(): void
    {
        require_any_role(['super_admin', 'security_manager']);
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            echo 'CSRF';
            return;
        }
        try {
            IntegrationSettingsService::saveSmtpFromPost($_POST);
        } catch (RuntimeException $e) {
            header('Location: ' . url('/settings/smtp') . '?error=' . rawurlencode($e->getMessage()));
            return;
        }
        try {
            $audit = new AuditLogService(Database::connection());
            $audit->log(current_user_id(), 'settings_smtp_saved', 'integration', 'smtp', []);
        } catch (\Throwable) {
        }
        header('Location: ' . url('/settings/smtp') . '?saved=1');
    }
}
