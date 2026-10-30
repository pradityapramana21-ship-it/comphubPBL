<section class="auth-card">
    <h1>Lupa Kata Sandi</h1>
    <?php require dirname(__DIR__) . '/partials/form-errors.php'; ?>
    <form method="post" action="<?= e(url('/lupa-sandi')) ?>" novalidate>
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>">
        <button type="submit" class="button">Kirim tautan</button>
    </form>
    <p class="auth-links"><a href="<?= e(url('/masuk')) ?>">Kembali ke masuk</a></p>
</section>
