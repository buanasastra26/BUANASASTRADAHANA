<?php
namespace App\Services;

final class EstimatorService
{
    /**
     * Sengaja tidak membuat harga/volume otomatis tanpa rumus bisnis CV BUANA.
     * Codex boleh mengembangkan fungsi ini setelah rumus dan master harga disetujui Owner.
     */
    public function evaluate(array $input): array
    {
        return [
            'status' => 'PERLU SURVEY',
            'estimated_total' => null,
            'note' => 'Rumus perhitungan dan master harga CV BUANA belum dikonfigurasi. Sistem tidak akan mengarang harga.',
        ];
    }
}
