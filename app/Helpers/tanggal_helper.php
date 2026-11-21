<?php

/**
 * Pembantu format tanggal bahasa Indonesia.
 * Dipakai di controller lewat: protected $helpers = ['tanggal'];
 * atau di view lewat: helper('tanggal');
 */
if (! function_exists('tanggal_indonesia')) {
    /** 2026-12-01 atau 2026-12-01 09:30:00 menjadi "1 Des 2026". */
    function tanggal_indonesia(?string $tanggal): string
    {
        if ($tanggal === null || $tanggal === '') {
            return '-';
        }

        $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $t = new DateTime($tanggal);

        return $t->format('j') . ' ' . $bulan[(int) $t->format('n')] . ' ' . $t->format('Y');
    }
}
