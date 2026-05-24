<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Active Directory / LDAP kimlik doğrulama servisi
 * Üretimde ldap extension veya symfony/ldap kullanılabilir; burada iskelet ve güvenli bağlama akışı
 */
final class LdapAuthService
{
    /** @var resource|\LDAP\Connection|null */
    private $connection = null;

    /** Son başarısızlığın kısa açıklaması (APP_DEBUG iken detaylı) — parola asla yazılmaz */
    private string $lastFailureSummary = '';

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly bool $useTls,
        private readonly string $bindDn,
        private readonly string $bindPassword,
        private readonly string $baseDn,
        private readonly string $userFilterTemplate,
        private readonly ?string $userFilterUpnTemplate = null
    ) {
    }

    /**
     * APP_DEBUG açıkken giriş ekranında gösterilecek güvenli özet (parola yok)
     */
    public function getLastFailureSummary(): string
    {
        return $this->lastFailureSummary;
    }

    /**
     * ldap_connect için tam URI. Panelde host ldap://x:389 olarak kayıtlıysa bir kez daha port eklenmez.
     */
    private function buildConnectUri(): string
    {
        $h = trim($this->host);
        if ($h === '') {
            return 'ldap://127.0.0.1:' . $this->port;
        }
        if (!str_starts_with($h, 'ldap://') && !str_starts_with($h, 'ldaps://')) {
            $h = 'ldap://' . $h;
        }
        $schemeLen = str_starts_with($h, 'ldaps://') ? 8 : 7;
        $rest = substr($h, $schemeLen);
        if ($rest !== '' && preg_match('/:\d+$/', $rest) === 1 && !str_contains($rest, '/')) {
            return $h;
        }

        return $h . ':' . $this->port;
    }

    /**
     * Servis hesabı ile bağlan (senkronizasyon ve arama için)
     */
    public function connect(): bool
    {
        $this->lastFailureSummary = '';
        if (!function_exists('ldap_connect')) {
            $this->recordFailure('php_ldap_missing', null, 'PHP ldap eklentisi yüklü değil (php.ini extension=ldap).');

            return false;
        }

        $fullUri = $this->buildConnectUri();
        $this->debugStep('connect', 'uri=' . $this->redactUri($fullUri) . ' use_tls=' . ($this->useTls ? '1' : '0'));
        $conn = @ldap_connect($fullUri);
        if ($conn === false) {
            $this->recordFailure('connect_failed', null, 'ldap_connect başarısız. URI=' . $this->redactUri($fullUri));

            return false;
        }
        $this->debugStep('ldap_connect', 'ok resource_allocated=1');
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        if ($this->useTls && str_starts_with($fullUri, 'ldap://')) {
            $this->debugStep('start_tls', 'attempt');
            if (!@ldap_start_tls($conn)) {
                $this->recordFailure('start_tls_failed', $conn, 'STARTTLS isteği reddedildi veya sertifika uyumsuz.');

                @ldap_close($conn);

                return false;
            }
            $this->debugStep('start_tls', 'ok');
        } else {
            $this->debugStep('start_tls', 'skipped');
        }
        $this->debugStep(
            'service_bind',
            'attempt bind_dn_chars=' . (string) strlen($this->bindDn) . ' bind_pw_set=' . ($this->bindPassword !== '' ? '1' : '0')
        );
        if (!@ldap_bind($conn, $this->bindDn, $this->bindPassword)) {
            $this->recordFailure(
                'service_bind_failed',
                $conn,
                'Servis hesabı bind. BIND_DN=' . $this->redactDn($this->bindDn)
            );
            @ldap_close($conn);

            return false;
        }
        $this->debugStep('service_bind', 'ok');
        $this->connection = $conn;

        return true;
    }

    /**
     * Kullanıcı adı + parola ile doğrula (panel girişi)
     * Parola uygulama DB'sinde saklanmaz; sadece LDAP ile kontrol edilir
     */
    public function authenticate(string $username, string $password): ?array
    {
        $this->lastFailureSummary = '';
        $this->debugStep(
            'authenticate',
            'start user=' . self::maskUsernameForLog($username) . ' pw_empty=' . ($password === '' ? '1' : '0')
        );
        if ($this->connection === null && !$this->connect()) {
            $this->debugStep('authenticate', 'aborted connect_failed');

            return null;
        }
        $filter = $this->buildUserSearchFilter($username);
        $this->debugStep('ldap_search', 'base_dn=' . $this->baseDn . ' filter=' . $filter);
        $result = @ldap_search($this->connection, $this->baseDn, $filter, [
            'cn', 'mail', 'sAMAccountName', 'userPrincipalName', 'department', 'title', 'manager', 'givenName', 'sn',
        ]);
        if ($result === false) {
            $this->recordFailure(
                'search_failed',
                $this->connection,
                'baseDn=' . $this->baseDn . ' filter=' . $filter
            );

            return null;
        }
        $entries = ldap_get_entries($this->connection, $result);
        $this->debugStep('ldap_search', 'ok entries=' . (string) ($entries['count'] ?? 0));
        if ($entries['count'] === 0) {
            $this->recordFailure(
                'user_not_found',
                $this->connection,
                'Aranan kullanıcı yok. baseDn=' . $this->baseDn . ' filter=' . $filter
            );

            return null;
        }
        $userDn = $entries[0]['dn'] ?? null;
        if (!is_string($userDn)) {
            $this->recordFailure('invalid_entry', $this->connection, 'DN okunamadı.');

            return null;
        }
        // Kimlik doğrulama: kullanıcı DN ile bind (parola loglanmaz)
        $this->debugStep('user_bind', 'attempt user_dn=' . $this->redactDn($userDn));
        $test = @ldap_bind($this->connection, $userDn, $password);
        if (!$test) {
            $this->recordFailure(
                'user_bind_failed',
                $this->connection,
                'Kullanıcı bind. userDn=' . $this->redactDn($userDn)
            );

            return null;
        }

        $mapped = $this->mapUserEntry($entries[0]);
        $mapped['dn'] = $userDn;
        $this->debugStep('authenticate', 'success sam=' . (string) ($mapped['username'] ?? ''));

        return $mapped;
    }

    /**
     * APP_DEBUG’tan bağımsız: .env LDAP_LOG_DEBUG=true iken ldap.log’a HTTP giriş denemesi öncesi özet (parola yok).
     */
    public static function debugLogLoginDispatch(string $username): void
    {
        if (!self::ldapLogDebugEnabled()) {
            return;
        }
        $e = IntegrationSettingsService::getLdapEnv();
        self::appendLdapLogLine(sprintf(
            "[%s] DEBUG login_dispatch host=%s port=%s tls=%s bind_dn_chars=%d bind_pw_configured=%s base_dn=%s user=%s php_ldap=%s\n",
            date('c'),
            $e['LDAP_HOST'],
            $e['LDAP_PORT'],
            $e['LDAP_USE_TLS'],
            strlen($e['LDAP_BIND_DN']),
            ($e['LDAP_BIND_PASSWORD'] ?? '') !== '' ? '1' : '0',
            $e['LDAP_BASE_DN'],
            self::maskUsernameForLog($username),
            function_exists('ldap_connect') ? '1' : '0'
        ));
    }

    private static function ldapLogDebugEnabled(): bool
    {
        return filter_var($_ENV['LDAP_LOG_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    private static function maskUsernameForLog(string $username): string
    {
        $t = trim($username);
        if ($t === '') {
            return '(boş)';
        }
        if (str_contains($t, '@')) {
            [$local, $dom] = explode('@', $t, 2);

            return self::maskUsernameForLog($local) . '@' . $dom;
        }
        if (strlen($t) <= 2) {
            return '**';
        }

        return substr($t, 0, 2) . '***';
    }

    private function debugStep(string $phase, string $detail): void
    {
        if (!self::ldapLogDebugEnabled()) {
            return;
        }
        self::appendLdapLogLine(sprintf("[%s] DEBUG %s %s\n", date('c'), $phase, $detail));
    }

    /**
     * Kullanıcı arama filtresi: @ içeriyorsa UPN (e-posta ile giriş), yoksa sAMAccountName şablonu
     */
    private function buildUserSearchFilter(string $username): string
    {
        $escaped = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
        if (str_contains($username, '@')) {
            $tpl = $this->userFilterUpnTemplate
                ?? ($_ENV['LDAP_USER_FILTER_UPN'] ?? '(&(objectClass=user)(userPrincipalName=%s))');

            return str_replace('%s', $escaped, $tpl);
        }

        return str_replace('%s', $escaped, $this->userFilterTemplate);
    }

    /**
     * LDAP hata kodunu dosyaya yazar; ekranda yalnızca APP_DEBUG iken kısa özet
     */
    private function recordFailure(string $stage, $conn, string $detail): void
    {
        $errno = $conn !== null ? ldap_errno($conn) : 0;
        $err = $conn !== null ? ldap_error($conn) : '';

        $userMsg = match ($stage) {
            'php_ldap_missing' => 'Sunucuda PHP ldap eklentisi kapalı.',
            'connect_failed' => 'LDAP sunucusuna TCP bağlantısı kurulamadı (host/port/firewall).',
            'start_tls_failed' => 'STARTTLS başarısız (TLS kapalıysa LDAP_USE_TLS=false deneyin veya 636 ldaps).',
            'service_bind_failed' => $this->explainServiceBindFailure($errno, $err),
            'search_failed' => $this->explainSearchFailure($errno, $err),
            'user_not_found' => 'Bu kullanıcı adı ile AD kaydı bulunamadı (Base DN veya filtre kontrol edin).',
            'invalid_entry' => 'LDAP kaydı geçersiz.',
            'user_bind_failed' => 'Parola hatalı veya hesap kilitli / parola süresi dolmuş olabilir.',
            default => 'LDAP doğrulama başarısız.',
        };

        $logLine = sprintf(
            "[%s] %s errno=%s ldap_error=%s detail=%s\n",
            date('c'),
            $stage,
            (string) $errno,
            $err,
            $detail
        );
        self::appendLdapLogLine($logLine);

        if (filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
            $this->lastFailureSummary = $userMsg . ' [' . $stage . ' LDAP ' . $errno . ': ' . $err . ']';
        } else {
            $this->lastFailureSummary = '';
        }
    }

    /**
     * Servis hesabı bind: -1 / "Can't contact" = ağ; 49 = yanlış DN veya parola
     */
    private function explainServiceBindFailure(int $errno, string $err): string
    {
        $errLower = strtolower($err);
        if ($errno === -1 || str_contains($errLower, "can't contact")) {
            return 'LDAP sunucusuna ulaşılamıyor (ağ yolu, VPN, güvenlik duvarı veya LDAP_HOST/LDAP_PORT yanlış). '
                . 'WAMP makinesinden DC adresine 389/636 erişimini doğrulayın.';
        }
        if ($errno === 49 || str_contains($errLower, 'invalid credentials')) {
            return 'Servis hesabı BIND_DN veya LDAP_BIND_PASSWORD hatalı (veya hesap kilitli).';
        }

        return 'Servis hesabı bind reddedildi (LDAP hata kodu ile kontrol edin).';
    }

    /**
     * LDAP 32 "No such object" = arama tabanı (LDAP_BASE_DN) AD’de yok veya yanlış yazılmış
     */
    private function explainSearchFailure(int $errno, string $err): string
    {
        if ($errno === 32 || str_contains(strtolower($err), 'no such object')) {
            return 'LDAP_BASE_DN geçersiz veya dizinde yok (errno 32). Genelde domain kökü kullanın: DC=...,DC=... — OU yolu hatalı olabilir.';
        }

        return 'LDAP arama hatası (Base DN / yetki / filtre).';
    }

    /**
     * storage/logs yoksa oluşturur; yazılamazsa PHP error_log’a düşer (WAMP izin sorunları için)
     */
    private static function appendLdapLogLine(string $line): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                error_log('Sakallı LDAP log klasörü oluşturulamadı: ' . $dir);
            }
        }
        $logFile = $dir . '/ldap.log';
        $written = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log('Sakallı ldap.log yazılamadı (izin/yol kontrol): ' . $logFile . ' | ' . trim($line));
        }
    }

    private function redactUri(string $uri): string
    {
        return preg_replace('/:[^:@]+@/', ':***@', $uri) ?? $uri;
    }

    /** DN içinde hassas bilgi yok; çok uzunsa kısalt */
    private function redactDn(string $dn): string
    {
        if (strlen($dn) > 120) {
            return substr($dn, 0, 117) . '...';
        }

        return $dn;
    }

    /**
     * Seçim kutusu için kısa etiket: OU=Cloud, OU=Egitim_dept, ... → "Egitim_dept — Cloud"
     */
    private function organizationalUnitPickerLabel(string $dn, string $ouName): string
    {
        if (preg_match('/^OU=([^,]+),\s*OU=([^,]+),/i', $dn, $m)) {
            return $m[2] . ' — ' . $m[1];
        }
        if ($ouName !== '') {
            return $ouName . ' — ' . $dn;
        }

        return $dn;
    }

    /**
     * AD / LDAP altında organizationalUnit nesnelerini listeler (kampanya hedefi OU seçimi)
     *
     * @param ?string $ouNameEquals Boş değilse yalnızca ou= değeri buna eşleşen OU’lar (ör. Cloud)
     *
     * @return list<array{dn: string, label: string}>
     */
    public function listOrganizationalUnits(?string $searchBase = null, int $sizeLimit = 500, ?string $ouNameEquals = null): array
    {
        if ($this->connection === null && !$this->connect()) {
            return [];
        }
        $base = $searchBase !== null && $searchBase !== '' ? $searchBase : $this->baseDn;
        // AD: objectCategory daha güvenilir; genel LDAP için objectClass yedeği
        $typeFilter = '(|(objectCategory=organizationalUnit)(objectClass=organizationalUnit))';
        $nameTrim = $ouNameEquals !== null ? trim($ouNameEquals) : '';
        if ($nameTrim !== '') {
            $esc = ldap_escape($nameTrim, '', LDAP_ESCAPE_FILTER);
            $filter = '(&' . $typeFilter . '(ou=' . $esc . '))';
        } else {
            $filter = $typeFilter;
        }
        $result = @ldap_search(
            $this->connection,
            $base,
            $filter,
            ['ou', 'name', 'description'],
            0,
            $sizeLimit,
            30
        );
        if ($result === false) {
            return [];
        }
        $entries = ldap_get_entries($this->connection, $result);
        $out = [];
        $n = (int) ($entries['count'] ?? 0);
        for ($i = 0; $i < $n; $i++) {
            $e = $entries[$i];
            $dn = isset($e['dn']) && is_string($e['dn']) ? $e['dn'] : '';
            if ($dn === '') {
                continue;
            }
            $ouName = isset($e['ou'][0]) ? (string) $e['ou'][0] : '';
            if ($ouName === '' && isset($e['name'][0])) {
                $ouName = (string) $e['name'][0];
            }
            $label = $this->organizationalUnitPickerLabel($dn, $ouName);
            $out[] = ['dn' => $dn, 'label' => $label];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    /**
     * OU ağacı altındaki kişi hesapları (bilgisayar hesapları hariç, alt OU dahil)
     *
     * @return list<array<string, mixed>>
     */
    public function fetchUsersForOu(string $ouDn, int $sizeLimit = 10000): array
    {
        if ($this->connection === null && !$this->connect()) {
            return [];
        }
        $sizeLimit = max(500, min(50000, $sizeLimit));
        $filter = '(&(objectCategory=person)(objectClass=user))';
        $result = @ldap_search($this->connection, $ouDn, $filter, [
            'cn', 'mail', 'sAMAccountName', 'userPrincipalName', 'department', 'title', 'manager', 'givenName', 'sn', 'objectGUID',
        ], 0, $sizeLimit, 120);
        if ($result === false) {
            return [];
        }
        $entries = ldap_get_entries($this->connection, $result);
        $out = [];
        for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
            $row = $this->mapUserEntry($entries[$i]);
            if (isset($entries[$i]['dn']) && is_string($entries[$i]['dn'])) {
                $row['dn'] = $entries[$i]['dn'];
            }
            if (!empty($entries[$i]['objectguid'][0]) && is_string($entries[$i]['objectguid'][0])) {
                $row['object_guid_hex'] = bin2hex($entries[$i]['objectguid'][0]);
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * LDAP girişini uygulama alanlarına eşle
     *
     * @param array<string, mixed> $entry
     * @return array<string, string|null>
     *
     * authenticate() sonrası 'dn' alanı Distinguished Name ile tamamlanır
     */
    private function mapUserEntry(array $entry): array
    {
        $mail = isset($entry['mail'][0]) ? (string) $entry['mail'][0] : null;
        $upn = isset($entry['userprincipalname'][0]) ? (string) $entry['userprincipalname'][0] : null;
        // AD'de mail boş olabilir; UPN genelde dolu ve e-posta biçimindedir
        $email = $mail !== '' ? $mail : (str_contains((string) $upn, '@') ? $upn : null);

        $row = [
            'username' => $entry['samaccountname'][0] ?? $entry['uid'][0] ?? null,
            'email' => $email,
            'first_name' => $entry['givenname'][0] ?? null,
            'last_name' => $entry['sn'][0] ?? null,
            'department' => $entry['department'][0] ?? null,
            'title' => $entry['title'][0] ?? null,
            'manager_dn' => $entry['manager'][0] ?? null,
            'display_name' => $entry['cn'][0] ?? null,
        ];
        if (isset($entry['dn']) && is_string($entry['dn'])) {
            $row['dn'] = $entry['dn'];
        }

        return $row;
    }

    public function disconnect(): void
    {
        if ($this->connection !== null) {
            @ldap_close($this->connection);
            $this->connection = null;
        }
    }
}
