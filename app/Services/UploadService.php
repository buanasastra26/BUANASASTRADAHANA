<?php
namespace App\Services;

final class UploadService
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    public function save(array $file, string $subdir): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new \RuntimeException('Upload file gagal.');
        $max = (int) env('UPLOAD_MAX_MB', 8) * 1024 * 1024;
        if (($file['size'] ?? 0) > $max) throw new \RuntimeException('Ukuran file terlalu besar.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) throw new \RuntimeException('Format file tidak diizinkan.');
        $dir = STORAGE_PATH . '/uploads/' . trim($subdir, '/');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) throw new \RuntimeException('Gagal menyimpan file.');
        return trim($subdir, '/') . '/' . $name;
    }
}
