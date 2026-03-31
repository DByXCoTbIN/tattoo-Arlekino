<?php
$base = defined('BASE_PATH') ? BASE_PATH : '';
$root = ($base !== '') ? rtrim($base, '/') . '/' : '/';
?>
<div class="card" style="max-width: 420px; margin: 0 auto;">
    <h1 class="card-title">Вход на арену</h1>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($root . 'login.php') ?>">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Войти</button>
        <a href="<?= htmlspecialchars($root . 'register.php') ?>" style="margin-left: 12px;">Регистрация</a>
    </form>
</div>
