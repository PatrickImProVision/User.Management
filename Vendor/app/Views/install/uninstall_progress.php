<?= $this->extend('layouts/site') ?>
<?= $this->section('main') ?>
<h1>Uninstall progress</h1>
<p class="lead">One step completed. Continue to run the next queued action.</p>

<div class="card prose">
    <p><strong>Completed:</strong> <?= esc($step_labels[$completed_step] ?? $completed_step) ?></p>
    <p><?= esc($summary) ?></p>
    <?php if (! empty($remaining)) : ?>
        <p style="margin-top:1rem;"><strong>Still queued:</strong></p>
        <ol style="margin:0.35rem 0 0 1.25rem;color:var(--muted);">
            <?php foreach ($remaining as $key) : ?>
                <li><?= esc($step_labels[$key] ?? $key) ?></li>
            <?php endforeach ?>
        </ol>
    <?php endif ?>
</div>

<div class="actions" style="margin-top:1.25rem;">
    <a class="btn btn-primary" href="<?= esc(site_url('install/uninstall/next')) ?>">Continue to next step</a>
    <a class="btn btn-secondary" href="<?= esc(site_url('/')) ?>">Cancel (queue kept — finish or reopen uninstall)</a>
</div>
<?= $this->endSection() ?>
