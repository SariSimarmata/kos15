<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="head">
  <div>
    <h1>Riwayat pembayaran</h1>
    <p class="sub">Semua pengajuan dan pembayaran yang pernah kamu lakukan.</p>
  </div>
</div>

<?php if (! $riwayat): ?>
  <div class="kosong">
    <div class="kosong-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
    <b>Belum ada pembayaran</b>
    <p>Setelah kamu mengajukan bayar tunai, riwayatnya tercatat di sini.</p>
  </div>
<?php else: ?>
<table>
  <thead><tr><th>Diajukan</th><th>Periode</th><th>Jumlah</th><th>Metode</th><th>Status</th><th>Bukti</th></tr></thead>
  <tbody>
  <?php foreach ($riwayat as $r): ?>
  <?php $kelas = ['menunggu' => 'warn', 'dikonfirmasi' => 'ok', 'berhasil' => 'ok', 'ditolak' => 'bad'][$r['status']] ?? 'warn'; ?>
  <tr>
    <td class="stub"><?= view('layouts/tgl', ['waktu' => $r['tanggal_ajuan']]) ?></td>
    <td data-l="Periode" class="periode"><?= esc($r['periode']) ?></td>
    <td data-l="Jumlah" class="uang">Rp<?= number_format($r['jumlah'], 0, ',', '.') ?></td>
    <td data-l="Metode"><?= esc(ucfirst($r['metode'])) ?></td>
    <td data-l="Status"><span class="pill <?= $kelas ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
    <td data-l="Bukti">
      <?php if (in_array($r['status'], ['dikonfirmasi', 'berhasil'], true)): ?>
        <a class="link" href="<?= site_url('penghuni/bukti/' . $r['id_pembayaran']) ?>" target="_blank">Lihat bukti</a>
      <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?= $this->endSection() ?>
