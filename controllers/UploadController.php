<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

class UploadController {

    public function uploadImage(): void {
        Auth::authenticate();

        if (!isset($_FILES['image'])) {
            Response::error('No image provided', 400);
        }

        $file = $_FILES['image'];
        $type = $_POST['type'] ?? 'general'; // vendors, reviews, avatars, general

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload failed with error code: ' . $file['error'], 400);
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            Response::error('File size exceeds maximum allowed (' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB)', 400);
        }

        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            Response::error('Invalid file type. Allowed: JPEG, PNG, WebP, GIF', 400);
        }

        $subDir = in_array($type, ['vendors', 'reviews', 'avatars']) ? $type : 'general';
        $uploadDir = UPLOAD_DIR . $subDir . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $subDir . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::error('Failed to save uploaded file', 500);
        }

        $url = 'uploads/' . $subDir . '/' . $filename;
        Response::success(['url' => $url], 'Image uploaded successfully', 201);
    }
}
