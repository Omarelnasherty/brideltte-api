<?php
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../middleware/RoleAuth.php';

class VendorController {
    private Vendor $vendorModel;
    private User $userModel;

    public function __construct() {
        $this->vendorModel = new Vendor();
        $this->userModel = new User();
    }

    public function list(): void {
        $query = Validator::getQuery();
        $pagination = Validator::getPagination();

        $category = $query['category'] ?? null;
        $search = $query['search'] ?? null;

        $result = $this->vendorModel->list(
            $pagination['limit'],
            $pagination['offset'],
            $category,
            $search
        );

        Response::paginated($result['data'], $result['total'], $pagination['page'], $pagination['limit']);
    }

    public function get(array $params): void {
        $id = (int)($params['id'] ?? 0);
        $vendor = $this->vendorModel->findById($id);
        if (!$vendor) {
            Response::notFound('Vendor not found');
        }
        Response::success($vendor);
    }

    public function create(): void {
        $user = Auth::authenticate();

        // Check if user already has a vendor profile
        $existing = $this->vendorModel->findByUserId($user['id']);
        if ($existing) {
            Response::error('You already have a vendor profile', 409);
        }

        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('business_name', 'Business name')
          ->minLength('business_name', 2, 'Business name')
          ->required('category', 'Category')
          ->in('category', ['Venues', 'Photography', 'Catering', 'Decoration', 'Music & Entertainment', 'Beauty & Makeup'], 'Category')
          ->required('description', 'Description')
          ->minLength('description', 20, 'Description')
          ->required('location', 'Location')
          ->required('phone', 'Phone')
          ->required('email', 'Email')
          ->email('email', 'Business email')
          ->required('price_range', 'Price range')
          ->validate();

        $vendorId = $this->vendorModel->create([
            'user_id' => $user['id'],
            'business_name' => Validator::sanitize($body['business_name']),
            'category' => $body['category'],
            'description' => Validator::sanitize($body['description']),
            'location' => Validator::sanitize($body['location']),
            'phone' => Validator::sanitize($body['phone']),
            'email' => strtolower(trim($body['email'])),
            'website' => isset($body['website']) ? Validator::sanitize($body['website']) : null,
            'price_range' => Validator::sanitize($body['price_range']),
            'images' => $body['images'] ?? [],
        ]);

        // Update user role to vendor
        $this->userModel->updateRole($user['id'], 'vendor');

        $vendor = $this->vendorModel->findById($vendorId);
        Response::success($vendor, 'Vendor profile created successfully. Awaiting admin verification.', 201);
    }

    public function me(): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }
        Response::success($vendor);
    }

    public function updateMe(): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $body = Validator::getBody();
        $updateData = [];

        $allowed = ['business_name', 'category', 'description', 'location', 'phone', 'email', 'website', 'price_range'];
        foreach ($allowed as $field) {
            if (isset($body[$field])) {
                $updateData[$field] = ($field === 'email') 
                    ? strtolower(trim($body[$field])) 
                    : Validator::sanitize($body[$field]);
            }
        }

        if (isset($body['category'])) {
            $v = new Validator($body);
            $v->in('category', ['Venues', 'Photography', 'Catering', 'Decoration', 'Music & Entertainment', 'Beauty & Makeup'], 'Category')
              ->validate();
        }

        if (empty($updateData)) {
            Response::error('No data to update', 400);
        }

        $this->vendorModel->update($vendor['id'], $updateData);
        $updated = $this->vendorModel->findById($vendor['id']);
        Response::success($updated, 'Vendor profile updated successfully');
    }

    public function uploadImages(): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        if (!isset($_FILES['images'])) {
            Response::error('No images provided', 400);
        }

        $uploadDir = UPLOAD_DIR . 'vendors/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $currentImages = is_array($vendor['images']) ? $vendor['images'] : [];
        $files = $_FILES['images'];
        $uploaded = [];

        // Handle single or multiple files
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $fileCount; $i++) {
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

            if ($error !== UPLOAD_ERR_OK) continue;
            if ($size > MAX_FILE_SIZE) continue;
            if (!in_array($type, ALLOWED_IMAGE_TYPES)) continue;

            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $filename = 'vendor_' . $vendor['id'] . '_' . time() . '_' . $i . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($tmpName, $filepath)) {
                $uploaded[] = 'uploads/vendors/' . $filename;
            }
        }

        if (empty($uploaded)) {
            Response::error('No valid images were uploaded', 400);
        }

        $allImages = array_merge($currentImages, $uploaded);
        $this->vendorModel->update($vendor['id'], ['images' => $allImages]);

        Response::success(['images' => $allImages], count($uploaded) . ' image(s) uploaded successfully');
    }

    public function deleteImage(array $params): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $index = (int)($params['index'] ?? -1);
        $images = is_array($vendor['images']) ? $vendor['images'] : [];

        if ($index < 0 || $index >= count($images)) {
            Response::error('Invalid image index', 400);
        }

        // Delete physical file
        $filepath = __DIR__ . '/../' . $images[$index];
        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        array_splice($images, $index, 1);
        $this->vendorModel->update($vendor['id'], ['images' => $images]);

        Response::success(['images' => $images], 'Image deleted successfully');
    }
}
