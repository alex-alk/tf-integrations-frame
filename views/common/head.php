<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <base href="<?= env('APP_FOLDER') ?>/" />

    <title>Travelfuse HOTELS API</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
        integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.6.3/css/all.min.css" />
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"
        integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous">
    </script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"
        integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <link href="https://cdn.datatables.net/v/bs4/jq-3.7.0/dt-2.0.4/datatables.min.css" rel="stylesheet">

    <script src="https://cdn.datatables.net/v/bs4/jq-3.7.0/dt-2.0.4/datatables.min.js"></script>
    <link rel="stylesheet" href="<?= env('APP_FOLDER') ?>/css/style.css">

    <script type="module" src="<?= env('APP_FOLDER') ?>/js/common.js" defer></script>

    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script type="module" src="<?= env('APP_FOLDER') ?>/js/<?= $script ?>" defer></script>
        <?php endforeach ?>
    <?php endif ?>
</head>