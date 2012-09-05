<?php foreach ($this->meta as $m): ?>
<meta <?php echo $m->property ? 'property' : 'name'; ?>="<?php echo $m->name; ?>" content="<?php echo H::escape($m->content); ?>" />
<?php endforeach; ?>
