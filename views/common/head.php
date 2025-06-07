<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <base href="<?= env('APP_FOLDER') ?>/" />

    <title>Travelfuse HOTELS API</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.6.3/css/all.min.css" />
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <link href="https://cdn.datatables.net/v/bs5/dt-2.3.2/datatables.min.css" rel="stylesheet" integrity="sha384-nt2TuLL4RlRQ9x6VTFgp009QD7QLRCYX17dKj9bj51w2jtWUGFMVTveRXfdgrUdx" crossorigin="anonymous">
 
    <script src="https://cdn.datatables.net/v/bs5/dt-2.3.2/datatables.min.js" integrity="sha384-rL0MBj9uZEDNQEfrmF51TAYo90+AinpwWp2+duU1VDW/RG7flzbPjbqEI3hlSRUv" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="<?= env('APP_FOLDER') ?>/css/style.css">

    <script type="module" src="<?= env('APP_FOLDER') ?>/js/common.js" defer></script>

    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script type="module" src="<?= env('APP_FOLDER') ?>/js/<?= $script ?>" defer></script>
        <?php endforeach ?>
    <?php endif ?>
</head>