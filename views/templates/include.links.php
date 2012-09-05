<?php foreach ($this->links as $l): ?>
<link href="<?php echo $l->href; ?>" rel="<?php echo $l->rel; ?>" <?php echo $l->type ? "type=\"{$l->type}\"" : '';
        ?> <?php echo $l->title ? "title=\"{$l->title}\"" : ''; ?>/>
<?php endforeach; ?>
