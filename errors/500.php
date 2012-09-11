<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?= (string)config()->env->charset ?>" />
    <title>Treb - Server Error!</title>
    <link rel="icon" href="/favicon.ico" />
</head>
<body>
    <h1>Error 500: Server Error</h1>
    <p><em>-- Generic Treb Framework error message</em></p>
    <?= H::ifWrap($extra, '<p>'); ?>
</body>
</html>
