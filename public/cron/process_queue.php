<?php

declare(strict_types=1);

/**
 * E-posta kuyruğu — cron veya Windows Görev Zamanlayıcı ile periyodik çalıştırın
 * Örnek: php c:\wamp64\www\sakalli\public\cron\process_queue.php
 */

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\Database;
use App\Repositories\TemplateRepository;
use App\Services\CampaignEmailQueueProcessor;
use App\Services\IntegrationSettingsService;

$pdo = Database::connection();

$smtp = IntegrationSettingsService::createSmtpEmailServiceForQueue();

$processor = new CampaignEmailQueueProcessor(
    $pdo,
    $smtp,
    new TemplateRepository($pdo)
);

$done = $processor->processBatch();

echo 'İşlenen kuyruk satırı: ' . $done . PHP_EOL;
