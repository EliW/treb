<?php foreach ($this->meta as $m): ?>
<meta <?= $m->property ? 'property' : 'name' ?>="<?= $m->name ?>" content="<?= H::escape($m->content) ?>" />
<?php endforeach; ?>
