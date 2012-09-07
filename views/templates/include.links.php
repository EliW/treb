<?php foreach ($this->links as $l): ?>
<link href="<?= $l->href; ?>" rel="<?= $l->rel; ?>" <?= $l->type ? "type=\"{$l->type}\"" : '';
        ?> <?= $l->title ? "title=\"{$l->title}\"" : ''; ?>/>
<?php endforeach; ?>
