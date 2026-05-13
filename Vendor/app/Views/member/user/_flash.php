<?php if ($msg = session()->getFlashdata('message')) : ?>
    <div class="ok"><?= esc($msg) ?></div>
<?php endif ?>

<?php if (! empty($errors)) : ?>
    <div class="err">
        <?php foreach ($errors as $k => $e) : ?>
            <div><strong><?= esc((string) $k) ?>:</strong> <?= esc(is_array($e) ? implode(' ', $e) : (string) $e) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>
