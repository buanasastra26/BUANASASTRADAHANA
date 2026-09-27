<?php
$legalCards = [
    ['NIB', 'Nomor Induk Berusaha', '1808260078269', ['Tanggal Terbit: 18 Agustus 2026'], 'M6 3h9l4 4v14H6V3Zm8 0v5h5M9 12h7M9 16h7'],
    ['NPWP', 'Nomor Pokok Wajib Pajak', '1000000010767902', ['KPP Pratama Purwakarta', 'Tanggal Terdaftar: 14 Agustus 2026'], 'M5 3h14v18H5V3Zm3 3h8v4H8V6Zm0 8h1m3 0h1m3 0h1M8 17h1m3 0h1m3 0h1'],
    ['AHU', 'Pengesahan Badan Usaha', 'AHU-0053793-AH.01.14', ['Tahun 2026'], 'M3 9l9-6 9 6H3Zm2 3v6m5-6v6m4-6v6m5-6v6M3 21h18'],
    ['AKTA PENDIRIAN', 'Akta Pendirian Perusahaan', 'Akta Nomor 05', ['Tanggal: 07 Agustus 2026', 'Notaris: Lucyana Dhika Sandy, S.H., M.Kn.', 'Wilayah: Kabupaten Subang'], 'M5 3h14v18H5V3Zm4 4h6M9 11h6M8 17l3-3 2 2 3-3'],
    ['KBLI UTAMA', 'Klasifikasi Baku Lapangan Usaha Indonesia', '46738', ['Perdagangan Besar Berbagai Macam Material Bangunan'], 'M3 7h18v14H3V7Zm5 0V3h8v4M3 12c6 3 12 3 18 0M10 13h4v3h-4'],
    ['PENANAMAN MODAL', 'Status Penanaman Modal', 'PMDN', ['Penanaman Modal Dalam Negeri'], 'M4 3v18h17M8 17v-4m5 4V9m5 8V5M7 9l6-5 5 1']
];
?>
<div class="legal-cards"><?php foreach ($legalCards as $card): ?><article class="legal-card"><div class="legal-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($card[4]) ?>"/></svg><span><?= e($card[0]) ?></span></div><h3><?= e($card[1]) ?></h3><strong class="legal-number"><?= e($card[2]) ?></strong><div class="legal-card-details"><?php foreach ($card[3] as $detail): ?><p><?= e($detail) ?></p><?php endforeach; ?></div></article><?php endforeach; ?></div>
