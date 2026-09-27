<?php
namespace App\Services;

use App\Core\DB;

final class ReceiptService
{
    public function createForPayment(int $paymentId): int
    {
        $pdo = DB::conn();
        $st = $pdo->prepare('SELECT p.*, pr.project_no, pr.title, u.name customer_name FROM payments p JOIN projects pr ON pr.id=p.project_id JOIN users u ON u.id=pr.user_id WHERE p.id=? LIMIT 1');
        $st->execute([$paymentId]);
        $payment = $st->fetch();
        if (!$payment) throw new \RuntimeException('Pembayaran tidak ditemukan.');
        if ($payment['status'] !== 'terverifikasi') throw new \RuntimeException('Pembayaran belum diverifikasi.');

        $existing = $pdo->prepare('SELECT id FROM receipts WHERE payment_id=? LIMIT 1');
        $existing->execute([$paymentId]);
        if ($id = $existing->fetchColumn()) return (int)$id;

        $number = 'KWT-' . date('Ymd') . '-' . str_pad((string)$paymentId, 5, '0', STR_PAD_LEFT);
        $ins = $pdo->prepare('INSERT INTO receipts (payment_id, receipt_no, issued_at, created_at) VALUES (?, ?, NOW(), NOW())');
        $ins->execute([$paymentId, $number]);
        return (int)$pdo->lastInsertId();
    }
}
