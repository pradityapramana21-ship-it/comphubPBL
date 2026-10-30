<?php /** @var array<string, mixed> $user */ ?>
<div class="page-head">
    <div>
        <p class="eyebrow">Profil</p>
        <h1>Pengaturan akun</h1>
        <p class="muted-note">Ubah nama, email, atau kata sandi Anda.</p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/dasbor')) ?>">Kembali ke dasbor</a>
</div>

<?php require dirname(__DIR__) . '/partials/form-errors.php'; ?>

<div class="form-grid">
    <section class="auth-card glass" aria-labelledby="profil-heading">
        <h2 id="profil-heading">Data profil</h2>
        <form method="post" action="<?= e(url('/profil')) ?>" novalidate>
            <?= csrf_field() ?>
            <label for="name">Nama</label>
            <input id="name" name="name" type="text" autocomplete="name" required value="<?= e(old('name', $user['name'])) ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email', $user['email'])) ?>">

            <button type="submit" class="button">Simpan profil</button>
        </form>
    </section>

    <section class="auth-card glass" aria-labelledby="sandi-heading">
        <h2 id="sandi-heading">Ganti kata sandi</h2>
        <form method="post" action="<?= e(url('/profil/sandi')) ?>" novalidate>
            <?= csrf_field() ?>
            <label for="current_password">Kata sandi saat ini</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>

            <label for="password">Kata sandi baru</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">

            <label for="password_confirmation">Ulangi kata sandi baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8">

            <button type="submit" class="button">Perbarui kata sandi</button>
        </form>
    </section>
</div>
