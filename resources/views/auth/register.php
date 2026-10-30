<?php /** @var string $next */ $next = (string) ($next ?? ''); ?>
<section class="auth-card">
    <h1>Daftar Akun</h1>
    <?php if ($next !== ''): ?>
        <p class="muted-note">Setelah mendaftar, Anda kembali ke kegiatan yang tadi dibuka.</p>
    <?php endif; ?>
    <?php require dirname(__DIR__) . '/partials/form-errors.php'; ?>
    <form method="post" action="<?= e(url('/daftar')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="honeypot" aria-hidden="true">
            <label for="website">Situs web</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <label for="name">Nama</label>
        <input id="name" name="name" type="text" autocomplete="name" required value="<?= e(old('name')) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>">
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
        <label for="password_confirmation">Ulangi kata sandi</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
        <button type="submit" class="button">Daftar</button>
    </form>
    <p class="auth-links"><a href="<?= e(url('/masuk')) ?>">Sudah punya akun? Masuk</a></p>
</section>
