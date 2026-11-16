<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected const PER_HALAMAN = 10;

    protected const PILIHAN_PER = [5, 10, 25, 50];

    protected function perHalaman(): int
    {
        $per = (int) request('per');

        return in_array($per, self::PILIHAN_PER, true) ? $per : self::PER_HALAMAN;
    }

    protected function pola(): ?string
    {
        $kata = mb_strtolower(trim((string) request('q', '')));

        return $kata === '' ? null : "%$kata%";
    }

    protected function urut(array $kolom): ?string
    {
        $kunci = (string) request()->query('urut');

        return isset($kolom[$kunci]) ? $kolom[$kunci].(request()->query('arah') === 'desc' ? ' desc' : ' asc') : null;
    }

    protected function halaman(LengthAwarePaginator $p): array
    {
        return ['saat_ini' => $p->currentPage(), 'terakhir' => $p->lastPage(), 'total' => $p->total(), 'dari' => $p->firstItem() ?? 0, 'ke' => $p->lastItem() ?? 0, 'per' => $p->perPage()];
    }
}
