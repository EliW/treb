<?php foreach ((array)$this->externals->css->normal as $css): // CSS Include Loop ?>
<link href="<?php echo H::version('css', $css . '.css'); ?>" rel="stylesheet" type="text/css" media="Screen"/>
<?php endforeach; ?>

<?php foreach ((array)$this->externals->css->mobile as $css): // CSS Mobile ?>
<link href="<?php echo H::version('css', $css . '.css'); ?>" rel="stylesheet" type="text/css" media="handheld"/>
<?php endforeach; ?>

<?php if ($this->externals->css->IE): ?>
<!--[if IE]>
    <?php foreach ((array)$this->externals->css->IE as $css): // CSS IE ?>
<link href="<?php echo H::version('css', $css . '.css'); ?>" rel="stylesheet" type="text/css" />
    <?php endforeach; ?>
<![endif]-->
<?php endif; ?>
