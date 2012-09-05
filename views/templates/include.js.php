<?php foreach ((array)$this->externals->js->normal as $js): // JS Include Loop ?>
<script type="text/javascript" src="<?php echo H::version('js', $js . '.js'); ?>"></script>
<?php endforeach; ?>
