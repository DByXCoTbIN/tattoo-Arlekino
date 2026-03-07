<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Repositories\UserRepo;

Auth::init();
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';

if (Auth::user()) {
    header('Location: ' . $root);
    exit;
}

$pending = $_SESSION['oauth_pending'] ?? null;
if (!$pending || !in_array($pending['provider'] ?? '', ['telegram', 'vk'], true)) {
    header('Location: ' . $root . 'login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    if (strlen($phone) < 10) {
        $error = 'Введите корректный номер телефона (не менее 10 цифр).';
    } else {
        $phoneFormatted = '+7' . (strlen($phone) === 10 ? $phone : substr($phone, -10));
        if (strlen($phone) === 11 && $phone[0] === '8') {
            $phoneFormatted = '+7' . substr($phone, 1);
        } elseif (strlen($phone) === 11 && $phone[0] === '7') {
            $phoneFormatted = '+' . $phone;
        } elseif (strlen($phone) === 10) {
            $phoneFormatted = '+7' . $phone;
        }

        $repo = new UserRepo();
        if ($repo->findByPhone($phoneFormatted)) {
            $error = 'Этот номер телефона уже привязан к другому аккаунту.';
        } else {
            try {
                $userId = $repo->createOAuth(
                    $pending['provider'],
                    $pending['oauth_id'],
                    $pending['full_name'],
                    $pending['avatar_url'] ?? null,
                    $phoneFormatted
                );
            } catch (\Throwable $e) {
                header('Location: ' . $root . 'login.php?error=oauth_not_ready');
                exit;
            }
            unset($_SESSION['oauth_pending']);
            Auth::login($userId);
            header('Location: ' . $root);
            exit;
        }
    }
}

$config = require dirname(__DIR__) . '/config/config.php';
$siteName = \App\Settings::get('site_name', $config['site']['name']);
$pageTitle = 'Укажите телефон для связи';
require __DIR__ . '/../templates/layout/header.php';
?>
<div class="card" style="max-width: 420px; margin: 0 auto;">
    <h1 class="card-title">Остался последний шаг</h1>
    <p style="margin-bottom: 20px; color: var(--text-muted);">
        Номер телефона нужен для связи с мастерами при записи.
    </p>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <div class="form-group">
            <label>Телефон <span style="color: var(--danger);">*</span></label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+7 999 123-45-67" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Завершить регистрацию</button>
    </form>
</div>
<?php require __DIR__ . '/../templates/layout/footer.php';
