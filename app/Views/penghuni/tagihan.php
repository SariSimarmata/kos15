<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$belumLunas = array_filter($tagihan, fn($t) => $t['status'] !== 'lunas');
$sisa       = array_sum(array_map(fn($t) => (float) $t['jumlah'], $belumLunas));
?>
<div class="head">
  <div>
    <h1>Tagihan saya</h1>
    <p class="sub">Daftar tagihan kos dan statusnya. Bayar tunai ke pemilik, lalu ajukan di sini.</p>
  </div>
  <?php if ($belumLunas): ?>
  <div class="stats">
    <div class="stat"><small>Belum lunas</small><b><?= count($belumLunas) ?> tagihan</b></div>
    <div class="stat"><small>Sisa bayar</small><b>Rp<?= number_format($sisa, 0, ',', '.') ?></b></div>
  </div>
  <?php endif; ?>
</div>

<?php if (! $tagihan): ?>
  <div class="kosong">
    <div class="kosong-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg></div>
    <b>Belum ada tagihan</b>
    <p>Tagihan kos kamu akan muncul di sini setiap periodenya.</p>
  </div>
<?php else: ?>
<table>
  <thead><tr><th>Periode</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($tagihan as $t): ?>
  <tr>
    <td data-l="Periode" class="periode"><?= esc($t['periode']) ?></td>
    <td data-l="Jumlah" class="uang">Rp<?= number_format($t['jumlah'], 0, ',', '.') ?></td>
    <td data-l="Status">
      <?php if ($t['status'] === 'lunas'): ?><span class="pill ok">Lunas</span>
      <?php elseif ($t['jml_menunggu'] > 0): ?><span class="pill warn">Menunggu konfirmasi</span>
      <?php else: ?><span class="pill bad">Belum bayar</span><?php endif; ?>
    </td>
    <td class="aksi-td">
      <?php if ($t['status'] === 'lunas'): ?><span class="muted">&mdash;</span>
      <?php elseif ($t['jml_menunggu'] > 0): ?><button disabled>Menunggu pemilik</button>
      <?php else: ?>
        <form method="post" action="<?= site_url('penghuni/tagihan/' . $t['id_tagihan'] . '/ajukan-tunai') ?>">
          <?= csrf_field() ?><button>Ajukan bayar tunai</button>
        </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?= $this->endSection() ?>
