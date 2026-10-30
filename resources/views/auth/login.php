<?php /** @var string $next */ $registerUrl = '/daftar' . ($next !== '' ? '?next=' . rawurlencode($next) : ''); ?>
<section class="auth-card">
    <h1>Masuk</h1>
    <?php require dirname(__DIR__) . '/partials/form-errors.php'; ?>
    <form method="post" action="<?= e(url('/masuk')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>">
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <label class="checkbox"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
        <button type="submit" class="button">Masuk</button>
    </form>
    <p class="auth-links">
        <a href="<?= e(url('/lupa-sandi')) ?>">Lupa kata sandi?</a>
        <a href="<?= e(url($registerUrl)) ?>">Buat akun</a>
    </p>
</section>
