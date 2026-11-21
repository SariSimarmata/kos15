<?php if (session('sukses')): ?>
    <div class="alert alert-success py-2"><?= esc(session('sukses')) ?></div>
<?php endif; ?>

<?php if (session('gagal')): ?>
    <div class="alert alert-danger py-2"><?= esc(session('gagal')) ?></div>
<?php endif; ?>
