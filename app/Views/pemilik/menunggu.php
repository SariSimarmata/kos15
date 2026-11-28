<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $total = array_sum(array_map(fn($d) => (float) $d['jumlah'], $daftar)); ?>
<div class="head">
  <div>
    <h1>Pengajuan bayar tunai</h1>
    <p class="sub">Konfirmasi jika uang tunai sudah kamu terima, atau tolak jika belum.</p>
  </div>
  <?php if ($daftar): ?>
  <div class="stats">
    <div class="stat"><small>Menunggu</small><b><?= count($daftar) ?> pengajuan</b></div>
    <div class="stat"><small>Total uang</small><b>Rp<?= number_format($total, 0, ',', '.') ?></b></div>
  </div>
  <?php endif; ?>
</div>

<?php if (! $daftar): ?>
  <div class="kosong">
    <div class="kosong-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
    <b>Semua sudah beres</b>
    <p>Belum ada pengajuan bayar tunai yang menunggu. Penghuni yang mengajukan akan muncul di sini.</p>
  </div>
<?php else: ?>
<table>
  <thead><tr><th>Diajukan</th><th>Penghuni</th><th>Periode</th><th>Jumlah</th><th>Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($daftar as $d): ?>
  <tr>
    <td class="stub"><?= view('layouts/tgl', ['waktu' => $d['tanggal_ajuan']]) ?></td>
    <td data-l="Penghuni"><div class="orang"><span class="avatar"><?= esc(mb_strtoupper(mb_substr($d['nama_penghuni'], 0, 1))) ?></span><?= esc($d['nama_penghuni']) ?></div></td>
    <td data-l="Periode" class="periode"><?= esc($d['periode']) ?></td>
    <td data-l="Jumlah" class="uang">Rp<?= number_format($d['jumlah'], 0, ',', '.') ?></td>
    <td class="aksi-td">
      <div class="aksi">
        <form method="post" action="<?= site_url('pemilik/pembayaran/' . $d['id_pembayaran'] . '/konfirmasi') ?>">
          <?= csrf_field() ?><button class="btn-ok">Konfirmasi</button>
        </form>
        <form method="post" action="<?= site_url('pemilik/pembayaran/' . $d['id_pembayaran'] . '/tolak') ?>">
          <?= csrf_field() ?><input name="alasan" placeholder="Alasan (opsional)" aria-label="Alasan menolak"><button class="btn-danger">Tolak</button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?= $this->endSection() ?>
