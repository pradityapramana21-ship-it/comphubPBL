<?php $all = errors(); ?>
<?php if ($all !== []): ?>
    <div class="alert alert-error" role="alert">
        <ul>
            <?php foreach ($all as $messages): ?>
                <?php foreach ((array) $messages as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
