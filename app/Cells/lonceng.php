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

    <div class="dropdown-menu dropdown-menu-end shadow-sm p-0" style="width: 320px;">
        <div class="px-3 py-2 border-bottom fw-semibold">Notifikasi</div>

        <?php if ($terbaru === []): ?>
            <div class="px-3 py-3 text-muted small">Belum ada notifikasi.</div>
        <?php endif; ?>

        <?php foreach ($terbaru as $n): ?>
            <div class="px-3 py-2 border-bottom small <?= $n['status_baca'] ? '' : 'bg-light' ?>">
                <div><?= esc($n['pesan']) ?></div>
                <div class="text-muted"><?= esc(date('d M Y H:i', strtotime($n['waktu_kirim']))) ?></div>
            </div>
        <?php endforeach; ?>

        <!-- Halaman daftar notifikasi menyusul (UC-18 dan UC-32) -->
        <div class="px-3 py-2 text-center small text-muted">Lihat semua (segera)</div>
    </div>
</div>
