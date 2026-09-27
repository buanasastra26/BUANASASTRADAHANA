<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\View;

final class SurveyController
{
    public function create(): void { Auth::requireLogin(); View::render('public/survey', ['title' => 'Ajukan Survey']); }
    public function store(): void
    {
        Auth::requireLogin(); Csrf::enforce();
        $address = trim((string)($_POST['address'] ?? ''));
        $jobType = trim((string)($_POST['job_type'] ?? ''));
        $date = trim((string)($_POST['preferred_date'] ?? '')) ?: null;
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($address === '' || $jobType === '') { flash('error', 'Alamat dan jenis pekerjaan wajib diisi.'); redirect('survey'); }
        $st = DB::conn()->prepare("INSERT INTO surveys (user_id, address, job_type, preferred_date, notes, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'menunggu_konfirmasi', NOW(), NOW())");
        $st->execute([Auth::id(), $address, $jobType, $date, $notes]);
        flash('success', 'Permintaan survey berhasil dikirim.');
        redirect('client');
    }
}
