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
