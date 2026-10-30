<?php
/** @var App\Core\Paginator $paginator */
?>
<?php if ($paginator->hasPages()): ?>
    <nav class="pagination" aria-label="Navigasi halaman">
        <?php if ($paginator->page > 1): ?>
            <a href="<?= e($paginator->url($paginator->page - 1)) ?>">Sebelumnya</a>
        <?php endif; ?>
        <span>Halaman <?= $paginator->page ?> dari <?= $paginator->lastPage() ?></span>
        <?php if ($paginator->page < $paginator->lastPage()): ?>
            <a href="<?= e($paginator->url($paginator->page + 1)) ?>">Berikutnya</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
