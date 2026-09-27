<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\View;
use App\Services\UploadService;

final class ClientController
{
    private function projects(): array
    {
        $st = DB::conn()->prepare('SELECT * FROM projects WHERE user_id=? ORDER BY id DESC');
        $st->execute([Auth::id()]);
        return $st->fetchAll();
    }

    public function dashboard(): void
    {
        Auth::requireRole('client');
        $pdo = DB::conn();
        $projects = $this->projects();
        $est = $pdo->prepare('SELECT * FROM estimates WHERE user_id=? ORDER BY id DESC LIMIT 5'); $est->execute([Auth::id()]);
        $sur = $pdo->prepare('SELECT * FROM surveys WHERE user_id=? ORDER BY id DESC LIMIT 5'); $sur->execute([Auth::id()]);
        View::render('client/dashboard', ['title'=>'Portal Client','projects'=>$projects,'estimates'=>$est->fetchAll(),'surveys'=>$sur->fetchAll()]);
    }

    public function progress(): void
    {
        Auth::requireRole('client');
        $st = DB::conn()->prepare('SELECT pu.*, p.title project_title FROM progress_updates pu JOIN projects p ON p.id=pu.project_id WHERE p.user_id=? ORDER BY pu.update_date DESC, pu.id DESC');
        $st->execute([Auth::id()]);
        View::render('client/progress', ['title'=>'Progress Project','rows'=>$st->fetchAll()]);
    }

    public function rabContract(): void
    {
        Auth::requireRole('client');
        $pdo = DB::conn();
        $r = $pdo->prepare('SELECT r.*, p.title project_title FROM rabs r JOIN projects p ON p.id=r.project_id WHERE p.user_id=? ORDER BY r.id DESC'); $r->execute([Auth::id()]);
        $c = $pdo->prepare('SELECT c.*, p.title project_title FROM contracts c JOIN projects p ON p.id=c.project_id WHERE p.user_id=? ORDER BY c.id DESC'); $c->execute([Auth::id()]);
        View::render('client/rab_contract', ['title'=>'Kontrak & RAB','rabs'=>$r->fetchAll(),'contracts'=>$c->fetchAll()]);
    }

    public function approveRab(string $id): void
    {
        Auth::requireRole('client'); Csrf::enforce();
        $st = DB::conn()->prepare("UPDATE rabs r JOIN projects p ON p.id=r.project_id SET r.status='disetujui', r.approved_at=NOW(), r.locked_at=NOW(), r.updated_at=NOW() WHERE r.id=? AND p.user_id=? AND r.type='final' AND r.status='menunggu_persetujuan'");
        $st->execute([(int)$id, Auth::id()]);
        flash('success', 'RAB Final telah disetujui dan dikunci.'); redirect('client/rab-kontrak');
    }

    public function signContract(string $id): void
    {
        Auth::requireRole('client'); Csrf::enforce();
        $st = DB::conn()->prepare("UPDATE contracts c JOIN projects p ON p.id=c.project_id SET c.status='telah_ditandatangani', c.signed_at=NOW(), c.updated_at=NOW() WHERE c.id=? AND p.user_id=? AND c.status='menunggu_tanda_tangan'");
        $st->execute([(int)$id, Auth::id()]);
        flash('success', 'Kontrak telah disetujui. Mekanisme ini bukan TTE tersertifikasi.'); redirect('client/rab-kontrak');
    }

    public function payments(): void
    {
        Auth::requireRole('client');
        $st = DB::conn()->prepare('SELECT py.*, p.title project_title, r.receipt_no FROM payments py JOIN projects p ON p.id=py.project_id LEFT JOIN receipts r ON r.payment_id=py.id WHERE p.user_id=? ORDER BY py.id DESC');
        $st->execute([Auth::id()]);
        View::render('client/payments', ['title'=>'Pembayaran','rows'=>$st->fetchAll(),'projects'=>$this->projects()]);
    }

    public function uploadPayment(): void
    {
        Auth::requireRole('client'); Csrf::enforce();
        $projectId=(int)($_POST['project_id']??0); $amount=(float)($_POST['amount']??0); $type=trim((string)($_POST['payment_type']??''));
        $check=DB::conn()->prepare('SELECT id FROM projects WHERE id=? AND user_id=?'); $check->execute([$projectId,Auth::id()]);
        if(!$check->fetch() || $amount<=0 || !in_array($type,['dp','termin','pelunasan'],true)){flash('error','Data pembayaran tidak valid.');redirect('client/pembayaran');}
        try { $path=(new UploadService())->save($_FILES['proof']??[], 'payments/' . $projectId); }
        catch(\Throwable $e){flash('error',$e->getMessage());redirect('client/pembayaran');}
        $st=DB::conn()->prepare("INSERT INTO payments (project_id,payment_type,amount,method,proof_path,status,created_at,updated_at) VALUES (?,?,?,'transfer_bank',?,'menunggu_verifikasi',NOW(),NOW())");
        $st->execute([$projectId,$type,$amount,$path]);
        flash('success','Bukti pembayaran berhasil diunggah dan menunggu verifikasi Admin.');redirect('client/pembayaran');
    }

    public function documents(): void
    {
        Auth::requireRole('client');
        $st=DB::conn()->prepare('SELECT d.*, p.title project_title FROM documents d JOIN projects p ON p.id=d.project_id WHERE p.user_id=? AND d.client_visible=1 ORDER BY d.id DESC');$st->execute([Auth::id()]);
        View::render('client/documents',['title'=>'Dokumen','rows'=>$st->fetchAll()]);
    }

    public function receipt(string $id): void
    {
        Auth::requireRole('client');
        $st=DB::conn()->prepare('SELECT r.*, py.amount, py.payment_type, py.method, py.verified_at, p.project_no, p.title project_title, u.name customer_name FROM receipts r JOIN payments py ON py.id=r.payment_id JOIN projects p ON p.id=py.project_id JOIN users u ON u.id=p.user_id WHERE r.id=? AND p.user_id=? LIMIT 1');
        $st->execute([(int)$id, Auth::id()]);
        $row=$st->fetch(); if(!$row){http_response_code(404);exit('Kwitansi tidak ditemukan.');}
        View::render('client/receipt',['title'=>'Kwitansi','row'=>$row],'layouts/print');
    }
}
