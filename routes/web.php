<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CampaignController;
use App\Controllers\DashboardController;
use App\Controllers\LandingPageController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\SettingsIntegrationsController;
use App\Controllers\TemplateController;
use App\Controllers\TrackingController;
use App\Controllers\TrackingEventsController;
use App\Controllers\UserController;
use App\Core\Router;

$router = new Router();

$router->get('/', static function (): void {
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . url('/dashboard'));
    } else {
        header('Location: ' . url('/login'));
    }
});

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/users', [UserController::class, 'index']);
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/reports/export', [ReportController::class, 'exportCsv']);

$router->get('/templates', [TemplateController::class, 'index']);
$router->get('/templates/create', [TemplateController::class, 'createForm']);
$router->post('/templates/create', [TemplateController::class, 'createSave']);
$router->get('/templates/{id}/preview', [TemplateController::class, 'preview']);
$router->post('/templates/preview-draft', [TemplateController::class, 'previewDraft']);
$router->post('/templates/{id}/test-email', [TemplateController::class, 'testEmail']);
$router->get('/templates/edit/{id}', [TemplateController::class, 'editForm']);
$router->post('/templates/edit/{id}', [TemplateController::class, 'editSave']);
$router->post('/templates/delete/{id}', [TemplateController::class, 'delete']);

$router->get('/campaigns', [CampaignController::class, 'index']);
$router->get('/campaigns/create', [CampaignController::class, 'createForm']);
$router->post('/campaigns/create', [CampaignController::class, 'createSave']);
$router->post('/campaigns/{id}/smtp-from-name', [CampaignController::class, 'saveSmtpFromName']);
$router->post('/campaigns/{id}/landing-page', [CampaignController::class, 'saveLandingPage']);
$router->post('/campaigns/{id}/interaction-mode', [CampaignController::class, 'saveInteractionMode']);
$router->post('/campaigns/{id}/targets', [CampaignController::class, 'saveTargets']);
$router->post('/campaigns/{id}/targets/clear', [CampaignController::class, 'clearTargets']);
$router->post('/campaigns/{id}/send/queue', [CampaignController::class, 'startSendQueue']);
$router->post('/campaigns/{id}/send/now', [CampaignController::class, 'resendNow']);
$router->post('/campaigns/delete/{id}', [CampaignController::class, 'delete']);
$router->get('/campaigns/{id}/preview', [CampaignController::class, 'preview']);
$router->get('/campaigns/{id}/preview/email', [CampaignController::class, 'previewEmail']);
$router->get('/campaigns/{id}/preview/flow', [CampaignController::class, 'previewFlow']);
$router->get('/campaigns/{id}/preview/thanks', [CampaignController::class, 'previewThanks']);
$router->get('/campaigns/{id}', [CampaignController::class, 'show']);

$router->get('/events', [TrackingEventsController::class, 'index']);
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/save', [SettingsController::class, 'save']);
$router->post('/settings/save-awareness', [SettingsController::class, 'saveAwareness']);
$router->post('/settings/save-branding', [SettingsController::class, 'saveBranding']);
$router->get('/settings/ldap', [SettingsIntegrationsController::class, 'ldapForm']);
$router->post('/settings/ldap', [SettingsIntegrationsController::class, 'ldapSave']);
$router->get('/settings/smtp', [SettingsIntegrationsController::class, 'smtpForm']);
$router->post('/settings/smtp', [SettingsIntegrationsController::class, 'smtpSave']);
$router->get('/settings/landing-pages', [LandingPageController::class, 'index']);
$router->get('/settings/landing-pages/create', [LandingPageController::class, 'createForm']);
$router->post('/settings/landing-pages/create', [LandingPageController::class, 'createSave']);
$router->get('/settings/landing-pages/edit/{id}', [LandingPageController::class, 'editForm']);
$router->post('/settings/landing-pages/edit/{id}', [LandingPageController::class, 'editSave']);
$router->post('/settings/landing-pages/delete/{id}', [LandingPageController::class, 'delete']);

$router->get('/track/click/{token}', [TrackingController::class, 'click']);
$router->post('/track/feedback', [TrackingController::class, 'feedback']);
$router->post('/track/credentials', [TrackingController::class, 'credentials']);
$router->get('/track/credential-thanks/{token}', [TrackingController::class, 'credentialThanks']);
$router->get('/track/thanks/{token}', [TrackingController::class, 'thanks']);
$router->get('/track/open/{token}', [TrackingController::class, 'open']);

return $router;
