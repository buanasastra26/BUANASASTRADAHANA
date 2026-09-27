<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\View;
use App\Services\EstimatorService;

final class EstimatorController
{
    public function create(): void { View::render('public/estimator', ['title' => 'Estimasi Biaya']); }

    public function store(): void
    {
        Csrf::enforce();
        $data = [
            'job_type' => trim((string)($_POST['job_type'] ?? '')),
            'land_area' => $_POST['land_area'] !== '' ? (float)$_POST['land_area'] : null,
            'building_area' => $_POST['building_area'] !== '' ? (float)$_POST['building_area'] : null,
            'budget' => $_POST['budget'] !== '' ? (float)$_POST['budget'] : null,
            'description' => trim((string)($_POST['description'] ?? '')),
        ];
        if (!in_array($data['job_type'], ['renovasi', 'aluminium', 'pembangunan_baru'], true)) {
            flash('error', 'Pilih jenis pekerjaan.'); redirect('estimasi');
        }
        $result = (new EstimatorService())->evaluate($data);
        if (Auth::check()) {
            $st = DB::conn()->prepare('INSERT INTO estimates (user_id, job_type, input_json, status, estimated_total, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $st->execute([Auth::id(), $data['job_type'], json_encode($data, JSON_UNESCAPED_UNICODE), $result['status'], $result['estimated_total'], $result['note']]);
        }
        View::render('public/estimate_result', ['title' => 'Hasil Estimasi', 'input' => $data, 'result' => $result]);
    }
}
