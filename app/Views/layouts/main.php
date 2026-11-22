<!DOCTYPE html>
<html lang="id">

<head>
    <?= $this->include('partials/head') ?>
    <?= $this->renderSection('head') ?>
</head>

<body>
    <?= $this->include('partials/navbar') ?>

    <main class="container py-4 py-lg-5">
        <?= $this->include('partials/flash') ?>
        <?= $this->renderSection('content') ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?= $this->renderSection('script') ?>
</body>

</html>
