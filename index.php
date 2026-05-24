<?php

declare(strict_types=1);

/**
 * Proje köküne (http://localhost/sakalli) gelindiğinde dizin listesi yerine
 * ön yüz klasörüne yönlendir — WAMP (göreli Location bazı istemcilerde hatalı olabilir)
 */
$baseDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/sakalli'));
$baseDir = rtrim($baseDir, '/') ?: '';
header('Location: ' . $baseDir . '/public/', true, 302);
exit;
