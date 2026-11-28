<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="head">
  <div>
    <h1>Notifikasi</h1>
    <p class="sub">Kabar terbaru tentang tagihan dan pembayaranmu.</p>
  </div>
  <?php if ($daftar): ?>
  <form method="post" action="<?= site_url('notifikasi/baca-semua') ?>"><?= csrf_field() ?><button class="btn-ghost">Tandai semua dibaca</button></form>
  <?php endif; ?>
</div>

<?php if (! $daftar): ?>
  <div class="kosong">
    <div class="kosong-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8.5 3 8.5H3S6 15 6 8"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/></svg></div>
    <b>Belum ada notifikasi</b>
    <p>Kabar soal tagihan dan konfirmasi pembayaran akan masuk ke sini.</p>
  </div>
<?php else: ?>
<table>
  <tbody>
  <?php foreach ($daftar as $n): ?>
  <?php $sudah = in_array($n['dibaca'], [true, 't', '1', 1], true); ?>
  <tr class="notif-row<?= $sudah ? ' dibaca' : '' ?>">
    <td class="stub"><?= view('layouts/tgl', ['waktu' => $n['created_at']]) ?></td>
    <td class="pesan">
      <div class="judul"><?php if (! $sudah): ?><span class="titik" title="Belum dibaca"></span><?php endif; ?><?= esc($n['judul']) ?></div>
      <div class="isi"><?= esc($n['pesan']) ?></div>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?= $this->endSection() ?>
