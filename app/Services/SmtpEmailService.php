<?php

declare(strict_types=1);

namespace App\Services;

/**
 * SMTP ile e-posta gönderimi (TLS + AUTH LOGIN)
 * Üretimde queue üzerinden çağrılmalı; burada doğrudan gönderim iskeleti
 */
final class SmtpEmailService
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption, // tls, ssl, none
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromAddress,
        private readonly string $fromName
    ) {
    }

    /**
     * HTML + düz metin alternatif gövde ile gönder
     *
     * @param list<string> $toAddresses
     */
    public function send(
        array $toAddresses,
        string $subject,
        string $htmlBody,
        string $plainBody,
        array $headers = [],
        ?string $fromNameOverride = null
    ): bool {
        $to = implode(', ', $toAddresses);
        $boundary = 'bnd_' . bin2hex(random_bytes(8));
        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $plainBody . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $htmlBody . "\r\n"
            . "--{$boundary}--\r\n";

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $displayName = trim((string) ($fromNameOverride ?? ''));
        if ($displayName === '') {
            $displayName = $this->fromName;
        }
        $fromHeader = sprintf(
            'From: %s <%s>',
            $this->encodeHeaderWord($displayName),
            $this->fromAddress
        );

        $data = $fromHeader . "\r\n"
            . 'To: ' . $to . "\r\n"
            . 'Subject: ' . $encodedSubject . "\r\n"
            . 'MIME-Version: 1.0' . "\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
        foreach ($headers as $k => $v) {
            $data .= $k . ': ' . $v . "\r\n";
        }
        $data .= "\r\n" . $message;

        return $this->smtpTransaction($toAddresses, $data);
    }

    private function encodeHeaderWord(string $s): string
    {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    /**
     * @param list<string> $toAddresses
     */
    private function smtpTransaction(array $toAddresses, string $data): bool
    {
        $remote = $this->encryption === 'ssl'
            ? 'ssl://' . $this->host . ':' . $this->port
            : $this->host . ':' . $this->port;

        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if ($fp === false) {
            error_log("SMTP bağlantı hatası: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($fp, 30);

        $read = fn (): string => (string) fgets($fp, 8192);
        // fwrite int döner; void arrow function ile uyumsuz — blok kullanıyoruz
        $write = static function (string $s) use ($fp): void {
            fwrite($fp, $s . "\r\n");
        };

        if (!$this->expect($read(), [220])) {
            fclose($fp);
            return false;
        }
        $ehloHost = 'sakalli.local';
        $write('EHLO ' . $ehloHost);
        $ehloResp = $this->readMultiline($fp);
        if (!$this->expect($ehloResp, [250])) {
            fclose($fp);
            return false;
        }

        if ($this->encryption === 'tls' && $this->port !== 465) {
            $write('STARTTLS');
            $r = $read();
            if (!$this->expect($r, [220])) {
                fclose($fp);
                return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $write('EHLO ' . $ehloHost);
            if (!$this->expect($this->readMultiline($fp), [250])) {
                fclose($fp);
                return false;
            }
        }

        if ($this->username !== '' && $this->password !== '') {
            $write('AUTH LOGIN');
            if (!$this->expect($read(), [334])) {
                fclose($fp);
                return false;
            }
            $write(base64_encode($this->username));
            if (!$this->expect($read(), [334])) {
                fclose($fp);
                return false;
            }
            $write(base64_encode($this->password));
            if (!$this->expect($read(), [235])) {
                fclose($fp);
                return false;
            }
        }

        $write('MAIL FROM:<' . $this->fromAddress . '>');
        if (!$this->expect($read(), [250])) {
            fclose($fp);
            return false;
        }
        foreach ($toAddresses as $addr) {
            $write('RCPT TO:<' . trim($addr) . '>');
            if (!$this->expect($read(), [250, 251])) {
                fclose($fp);
                return false;
            }
        }
        $write('DATA');
        if (!$this->expect($read(), [354])) {
            fclose($fp);
            return false;
        }
        // Satır başı nokta kaçışı
        $lines = preg_split("/\r\n|\n|\r/", $data) ?: [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            fwrite($fp, $line . "\r\n");
        }
        fwrite($fp, ".\r\n");
        if (!$this->expect($read(), [250])) {
            fclose($fp);
            return false;
        }
        $write('QUIT');
        fclose($fp);
        return true;
    }

    private function readMultiline($fp): string
    {
        $buf = '';
        while (!feof($fp)) {
            $line = fgets($fp, 8192);
            if ($line === false) {
                break;
            }
            $buf .= $line;
            // 250- çok satır; 250 boşluk ile biten tek satır
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $buf;
    }

    /**
     * @param list<int> $codes
     */
    private function expect(string $response, array $codes): bool
    {
        $code = (int) substr(trim($response), 0, 3);
        return in_array($code, $codes, true);
    }
}
