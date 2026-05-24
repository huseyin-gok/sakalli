<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Middlewares\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuditLogService;
use App\Services\IntegrationSettingsService;
use App\Services\LdapAuthService;
use App\Services\LoginRateLimiter;
use App\Core\Database;

/**
 * LDAP giriş ve oturum yönetimi
 */
final class AuthController
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . url('/dashboard'));
            return;
        }
        $token = CsrfMiddleware::ensureToken();
        View::render('auth/login', [
            'csrf' => $token,
            'error' => $_GET['error'] ?? null,
            'ldap_debug' => '',
        ]);
    }

    public function login(): void
    {
        if (!CsrfMiddleware::validate($_POST['_csrf'] ?? null)) {
            header('Location: ' . url('/login') . '?error=csrf');
            return;
        }
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0';

        $storageDir = dirname(__DIR__, 2) . '/storage/cache/ratelimit';
        $key = $ip . '|' . $username;

        $rateLimitOff = filter_var($_ENV['LOGIN_RATE_LIMIT_DISABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $limiter = null;
        if (!$rateLimitOff) {
            $maxAttempts = max(1, min(1000, (int) ($_ENV['LOGIN_RATE_LIMIT_MAX'] ?? 5)));
            $windowSeconds = max(60, min(86400, (int) ($_ENV['LOGIN_RATE_LIMIT_WINDOW_SECONDS'] ?? 900)));
            $limiter = new LoginRateLimiter($storageDir, $maxAttempts, $windowSeconds);
            if ($limiter->isBlocked($key)) {
                $this->auditLogin(null, $username, false, 'rate_limited');
                View::render('auth/login', [
                    'csrf' => CsrfMiddleware::ensureToken(),
                    'error' => 'Çok fazla deneme. Lütfen daha sonra tekrar deneyin.',
                    'ldap_debug' => '',
                ]);
                return;
            }
        }

        LdapAuthService::debugLogLoginDispatch($username);

        $ldap = IntegrationSettingsService::createLdapAuthService();

        $userInfo = $ldap->authenticate($username, $password);
        $ldap->disconnect();

        if ($userInfo === null) {
            $limiter?->hit($key);
            $this->auditLogin(null, $username, false, 'bad_credentials');
            $ldapDebug = '';
            if (filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
                $ldapDebug = $ldap->getLastFailureSummary();
            }
            View::render('auth/login', [
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => 'Kullanıcı adı veya parola hatalı.',
                'ldap_debug' => $ldapDebug,
            ]);
            return;
        }

        if (!$this->isLdapDnAllowedForPanel($userInfo)) {
            $limiter?->hit($key);
            $this->auditLogin(null, $username, false, 'ldap_ou_denied');
            $ldapDebug = '';
            if (filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
                $ldapDebug = 'AD DN bu uygulama için uygun OU altında değil: ' . (string) ($userInfo['dn'] ?? '');
            }
            View::render('auth/login', [
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => 'Bu uygulamaya yalnızca Bilgi İşlem birimi (AD OU) hesapları erişebilir.',
                'ldap_debug' => $ldapDebug,
            ]);
            return;
        }

        $limiter?->clear($key);
        $repo = new UserRepository();

        $resolvedEmail = trim((string) ($userInfo['email'] ?? ''));
        if ($resolvedEmail === '' && str_contains($username, '@')) {
            $resolvedEmail = $username;
        }

        $local = $repo->findByEmail($resolvedEmail);
        if ($local === null) {
            $sam = trim((string) ($userInfo['username'] ?? ''));
            if ($sam !== '') {
                $local = $repo->findByUsername($sam);
            }
        }

        $ldapEnv = IntegrationSettingsService::getLdapEnv();
        if ($local === null && filter_var($ldapEnv['LDAP_AUTO_PROVISION'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
            $local = $repo->createFromLdap([
                'username' => $userInfo['username'] ?? '',
                'email' => $resolvedEmail,
                'first_name' => $userInfo['first_name'] ?? null,
                'last_name' => $userInfo['last_name'] ?? null,
                'display_name' => $userInfo['display_name'] ?? null,
            ]);
            if ($local !== null) {
                $repo->assignRoleBySlug((int) $local['id'], trim((string) ($ldapEnv['LDAP_AUTO_PROVISION_ROLE'] ?? 'report_viewer')));
                $audit = new AuditLogService(Database::connection());
                $audit->log(null, 'ldap_auto_provision', 'user', (string) $local['id'], [
                    'username' => $userInfo['username'] ?? '',
                    'email' => $resolvedEmail,
                ]);
            }
        }

        if ($local === null) {
            $this->auditLogin(null, $username, false, 'no_local_user');
            $hint = '';
            if (filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
                $hint = ' .env içinde LDAP_AUTO_PROVISION=true deneyin veya MySQL users tablosuna bu e-posta/kullanıcı adı ile kayıt ekleyin.';
                if ($resolvedEmail === '') {
                    $hint .= ' AD’de mail ve userPrincipalName boş olabilir; UPN doldurun.';
                }
            }
            View::render('auth/login', [
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => 'Hesabınız sistemde tanımlı değil. Yöneticinize başvurun.' . $hint,
                'ldap_debug' => '',
            ]);
            return;
        }
        if (!(bool) $local['is_active']) {
            $this->auditLogin((int) $local['id'], $username, false, 'inactive');
            View::render('auth/login', [
                'csrf' => CsrfMiddleware::ensureToken(),
                'error' => 'Hesap pasif.',
            ]);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $local['id'];
        $_SESSION['roles'] = $this->loadRoles((int) $local['id']);
        $panelName = trim((string) ($local['display_name'] ?? ''));
        if ($panelName === '') {
            $panelName = trim((string) ($local['email'] ?? ''));
        }
        if ($panelName === '') {
            $panelName = trim((string) ($local['username'] ?? ''));
        }
        $_SESSION['user_display_name'] = $panelName;
        $_SESSION['user_email'] = trim((string) ($local['email'] ?? ''));
        $_SESSION['user_username'] = trim((string) ($local['username'] ?? ''));

        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => (int) $local['id']]);

        $this->auditLogin((int) $local['id'], $username, true, 'ok');
        header('Location: ' . url('/dashboard'));
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], true);
        }
        session_destroy();
        header('Location: ' . url('/login'));
    }

    /**
     * LDAP_PANEL_ALLOWED_DN_SUBSTRING boşsa kontrol yok (geriye dönük).
     * Örn. OU=ITDept — DistinguishedName içinde bu alt dize aranır (büyük/küçük harf duyarsız).
     *
     * @param array<string, string|null> $userInfo
     */
    private function isLdapDnAllowedForPanel(array $userInfo): bool
    {
        $marker = trim((string) (IntegrationSettingsService::getLdapEnv()['LDAP_PANEL_ALLOWED_DN_SUBSTRING'] ?? ''));
        if ($marker === '') {
            return true;
        }
        $dn = (string) ($userInfo['dn'] ?? '');

        return $dn !== '' && stripos($dn, $marker) !== false;
    }

    /**
     * @return list<string>
     */
    private function loadRoles(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.slug FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :uid'
        );
        $stmt->execute(['uid' => $userId]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'slug');
    }

    private function auditLogin(?int $userId, string $username, bool $ok, string $reason): void
    {
        $audit = new AuditLogService(Database::connection());
        $audit->log(
            $userId,
            $ok ? 'login_success' : 'login_failure',
            'auth',
            $username,
            ['reason' => $reason]
        );
    }

}
