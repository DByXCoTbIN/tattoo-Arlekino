<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
$role = $_POST['role'] ?? 'client';
?>
<div class="card" style="max-width: 480px; margin: 0 auto;">
    <h1 class="card-title">Регистрация</h1>
    <?php if ($success): ?>
        <div class="alert alert-success">Вы зарегистрированы. <a href="<?= htmlspecialchars($root . 'login.php') ?>">Войти</a></div>
    <?php else: ?>
        <p style="margin-bottom: 16px; color: var(--text-muted); font-size: 0.95rem;">Быстрая регистрация для клиентов — имя, аватар и телефон привяжутся автоматически:</p>
        <div class="oauth-buttons" style="margin-bottom: 20px;">
            <?php if ($hasTelegram ?? false): ?>
            <script async src="https://telegram.org/js/telegram-widget.js?22" data-telegram-login="<?= htmlspecialchars($oauthConfig['telegram']['bot_username'] ?? '') ?>" data-size="large" data-auth-url="<?= htmlspecialchars(\App\OAuthService::buildAbsoluteUrl($base . '/auth_telegram.php')) ?>" data-request-access="write"></script>
            <?php else: ?>
            <button type="button" class="btn" style="width:100%; justify-content:center; opacity:.65; cursor:not-allowed;" disabled title="Нужно заполнить TELEGRAM_BOT_USERNAME и TELEGRAM_BOT_TOKEN в .env">
                Telegram (не настроен)
            </button>
            <?php endif; ?>
            <?php if ($hasVk ?? false): ?>
            <a href="<?= htmlspecialchars($root . 'auth_vk.php') ?>" class="btn btn-vk" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #0077ff; color: #fff; border-radius: var(--radius-sm); text-decoration: none; margin-top: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.408 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.525-2.049-1.727-1.033-1-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.575c0 .424-.135.678-1.253.678-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4.03 8.57 4.03 8.096c0-.254.102-.491.593-.491h1.744c.44 0 .61.203.78.678.863 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.203.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.17.508.271.508.22 0 .407-.136.813-.542 1.254-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.763-.491h1.744c.525 0 .644.27.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.049.17.49-.085.744-.576.744z"/></svg>
                Регистрация через ВКонтакте
            </a>
            <?php else: ?>
            <button type="button" class="btn" style="width:100%; justify-content:center; margin-top: 8px; opacity:.65; cursor:not-allowed;" disabled title="Нужно заполнить VK_CLIENT_ID и VK_CLIENT_SECRET в .env">
                ВКонтакте (не настроен)
            </button>
            <?php endif; ?>
        </div>
        <?php if (!($hasTelegram ?? false) && !($hasVk ?? false)): ?>
        <p style="margin: 0 0 12px; color: var(--text-muted); font-size: 0.85rem;">
            Для включения регистрации через мессенджеры заполните переменные OAuth в файле <code>.env</code>.
        </p>
        <?php endif; ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; margin: 16px 0;">— или —</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($root . 'register.php') ?>">
            <div class="form-group">
                <label>Имя</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Пароль (не менее 6 символов)</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <div class="form-group" id="phoneGroup">
                <label>Телефон для связи <span style="color: var(--danger);">*</span></label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+7 999 123-45-67">
                <small style="color: var(--text-muted); display: block; margin-top: 6px;">Обязателен для клиентов — мастера смогут связаться с вами при записи.</small>
            </div>
            <div class="form-group">
                <label>Я регистрируюсь как</label>
                <select name="role" id="roleSelect" class="form-group select" style="width:100%; padding:12px; background: var(--bg-dark); border: 1px solid var(--border); color: var(--text); font-family: var(--font); border-radius: var(--radius-sm);">
                    <option value="client" <?= $role === 'client' ? 'selected' : '' ?>>Я клиент — смотреть работы и писать мастерам</option>
                    <option value="master" <?= $role === 'master' ? 'selected' : '' ?>>Я мастер — заявка будет отправлена администратору</option>
                </select>
                <small style="color: var(--text-muted); display: block; margin-top: 6px;">При выборе «Я мастер» администратор рассмотрит заявку и откроет личный кабинет мастера.</small>
            </div>
            <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
            <a href="<?= htmlspecialchars($root . 'login.php') ?>" style="margin-left: 12px;">Уже есть аккаунт</a>
        </form>
        <script>
        (function(){
            var sel = document.getElementById('roleSelect');
            var phoneGroup = document.getElementById('phoneGroup');
            var phoneInput = phoneGroup && phoneGroup.querySelector('input[name="phone"]');
            function update() {
                var isClient = sel && sel.value === 'client';
                if (phoneInput) phoneInput.required = isClient;
            }
            if (sel) sel.addEventListener('change', update);
            update();
        })();
        </script>
    <?php endif; ?>
</div>
