<div class="dropdown">
    <button class="tombol-ikon position-relative" type="button"
        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifikasi">
        <i class="bi bi-bell fs-5"></i>
        <?php if ($jumlahBelumDibaca > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background: var(--kos-merah)">
                <?= $jumlahBelumDibaca > 99 ? '99+' : $jumlahBelumDibaca ?>
            </span>
        <?php endif; ?>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow-sm dropdown-notif">
        <div class="d-flex justify-content-between align-items-center px-2 pt-1 pb-2">
            <span class="fw-semibold">Notifikasi</span>
            <?php if ($jumlahBelumDibaca > 0): ?>
                <span class="lencana lencana-biru"><?= esc($jumlahBelumDibaca) ?> baru</span>
            <?php endif; ?>
        </div>

        <?php if ($terbaru === []): ?>
            <div class="px-2 py-3 text-muted small">Belum ada notifikasi.</div>
        <?php endif; ?>

        <?php foreach ($terbaru as $n): ?>
            <!-- Klik: tandai dibaca lalu buka halaman terkait (kartu Notifikasi dan Pengingat Harian) -->
            <a href="<?= site_url('notifikasi/' . $n['id_notifikasi'] . '/buka') ?>"
                class="notif-item <?= $n['status_baca'] ? '' : 'baru' ?>">
                <span class="notif-titik" aria-hidden="true"></span>
                <span class="min-w-0 flex-grow-1">
                    <span class="judul d-block small"><?= esc($n['pesan']) ?></span>
                    <span class="meta"><?= esc(date('d M Y, H.i', strtotime($n['waktu_kirim']))) ?> WIB</span>
                </span>
            </a>
        <?php endforeach; ?>

        <a href="<?= site_url('notifikasi') ?>" class="d-block pt-2 pb-1 text-center small fw-medium">Lihat semua notifikasi</a>
    </div>
</div>
