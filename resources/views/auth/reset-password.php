<?php /** @var string $token */ /** @var string $email */ ?>
<section class="auth-card">
    <h1>Atur Ulang Kata Sandi</h1>
    <?php require dirname(__DIR__) . '/partials/form-errors.php'; ?>
    <form method="post" action="<?= e(url('/atur-sandi')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email !== '' ? $email : old('email')) ?>">
        <label for="password">Kata sandi baru</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
        <label for="password_confirmation">Ulangi kata sandi baru</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">
        <button type="submit" class="button">Simpan kata sandi</button>
    </form>
</section>
