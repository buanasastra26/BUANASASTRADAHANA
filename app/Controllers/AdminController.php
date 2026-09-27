<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\View;
use App\Services\ReceiptService;

final class AdminController
{
    private function guard(): void { Auth::requireRole('admin'); }

    public function dashboard(): void
    {
        $this->guard(); $pdo=DB::conn();
        $counts=[
            'customers'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(),
            'estimates'=>(int)$pdo->query('SELECT COUNT(*) FROM estimates')->fetchColumn(),
            'surveys'=>(int)$pdo->query("SELECT COUNT(*) FROM surveys WHERE status='menunggu_konfirmasi'")->fetchColumn(),
            'projects'=>(int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status NOT IN ('selesai','dibatalkan')")->fetchColumn(),
            'payments'=>(int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='menunggu_verifikasi'")->fetchColumn(),
        ];
        View::render('admin/dashboard',['title'=>'Admin Dashboard','counts'=>$counts]);
    }

    private function list(string $title,string $sql): void { $this->guard(); $rows=DB::conn()->query($sql)->fetchAll(); View::render('admin/list',['title'=>$title,'rows'=>$rows]); }
    public function customers(): void { $this->list('Customer / Leads',"SELECT id,name,whatsapp,email,created_at FROM users WHERE role='client' ORDER BY id DESC"); }
    public function estimates(): void { $this->list('Estimasi',"SELECT e.id,u.name customer,e.job_type,e.status,e.estimated_total,e.created_at FROM estimates e LEFT JOIN users u ON u.id=e.user_id ORDER BY e.id DESC"); }
    public function surveys(): void { $this->list('Permintaan Survey',"SELECT s.id,u.name customer,s.job_type,s.address,s.preferred_date,s.status,s.created_at FROM surveys s JOIN users u ON u.id=s.user_id ORDER BY s.id DESC"); }
    public function rabs(): void { $this->list('RAB Final',"SELECT r.id,p.project_no,p.title,r.type,r.status,r.grand_total,r.created_at FROM rabs r JOIN projects p ON p.id=r.project_id ORDER BY r.id DESC"); }
    public function contracts(): void { $this->list('Kontrak',"SELECT c.id,p.project_no,p.title,c.status,c.contract_value,c.signed_at FROM contracts c JOIN projects p ON p.id=c.project_id ORDER BY c.id DESC"); }
    public function projects(): void { $this->list('Project Aktif',"SELECT p.id,p.project_no,p.title,u.name customer,p.status,p.progress_percent,p.contract_value,p.target_end_date FROM projects p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC"); }
    public function documents(): void { $this->list('Dokumen',"SELECT d.id,p.project_no,p.title,d.document_type,d.title document_title,d.client_visible,d.created_at FROM documents d JOIN projects p ON p.id=d.project_id ORDER BY d.id DESC"); }

    public function payments(): void
    {
        $this->guard();
        $rows=DB::conn()->query("SELECT py.id,p.project_no,p.title,u.name customer,py.payment_type,py.amount,py.status,py.verified_at FROM payments py JOIN projects p ON p.id=py.project_id JOIN users u ON u.id=p.user_id ORDER BY py.id DESC")->fetchAll();
        View::render('admin/payments',['title'=>'Verifikasi Pembayaran','rows'=>$rows]);
    }

    public function verifyPayment(string $id): void
    {
        $this->guard(); Csrf::enforce(); $pdo=DB::conn();
        $st=$pdo->prepare("UPDATE payments SET status='terverifikasi', verified_by=?, verified_at=NOW(), updated_at=NOW() WHERE id=? AND status='menunggu_verifikasi'");
        $st->execute([Auth::id(),(int)$id]);
        if($st->rowCount()){(new ReceiptService())->createForPayment((int)$id);flash('success','Pembayaran diverifikasi dan kwitansi dibuat.');}
        else flash('error','Pembayaran tidak dapat diverifikasi.');
        redirect('admin/payments');
    }

    public function masterPrices(): void
    {
        $this->guard(); $rows=DB::conn()->query('SELECT * FROM master_prices ORDER BY category,name')->fetchAll();
        View::render('admin/master_prices',['title'=>'Master Harga Internal','rows'=>$rows]);
    }

    public function saveMasterPrice(): void
    {
        $this->guard(); Csrf::enforce();
        $name=trim((string)($_POST['name']??''));$category=trim((string)($_POST['category']??''));$unit=trim((string)($_POST['unit']??''));$price=(float)($_POST['price']??0);
        if($name===''||$category===''||$unit===''||$price<0){flash('error','Data master harga belum lengkap.');redirect('admin/master-harga');}
        $st=DB::conn()->prepare('INSERT INTO master_prices (category,name,unit,price,is_active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW())');$st->execute([$category,$name,$unit,$price]);
        flash('success','Master harga ditambahkan.');redirect('admin/master-harga');
    }
}
