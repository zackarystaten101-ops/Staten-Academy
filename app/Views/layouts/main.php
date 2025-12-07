<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' - ' : ''; ?>Staten Academy</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/mobile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php echo $content; ?>
</body>
</html>

