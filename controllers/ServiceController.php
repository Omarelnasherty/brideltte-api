<?php
require_once __DIR__ . '/../models/Service.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../middleware/RoleAuth.php';

class ServiceController {
    private Service $serviceModel;
    private Vendor $vendorModel;

    public function __construct() {
        $this->serviceModel = new Service();
        $this->vendorModel = new Vendor();
    }

    public function listByVendor(array $params): void {
        $vendorId = (int)($params['vendorId'] ?? 0);
        $services = $this->serviceModel->findByVendor($vendorId);
        Response::success($services);
    }

    public function create(): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $body = Validator::getBody();

        $v = new Validator($body);
        $v->required('name', 'Service name')
          ->minLength('name', 2, 'Service name')
          ->required('description', 'Description')
          ->required('price', 'Price')
          ->numeric('price', 'Price')
          ->min('price', 0, 'Price')
          ->required('duration', 'Duration')
          ->required('category', 'Category')
          ->validate();

        $serviceId = $this->serviceModel->create([
            'vendor_id' => $vendor['id'],
            'name' => Validator::sanitize($body['name']),
            'description' => Validator::sanitize($body['description']),
            'price' => (float)$body['price'],
            'duration' => Validator::sanitize($body['duration']),
            'category' => Validator::sanitize($body['category']),
        ]);

        $service = $this->serviceModel->findById($serviceId);
        Response::success($service, 'Service created successfully', 201);
    }

    public function update(array $params): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $serviceId = (int)($params['id'] ?? 0);
        $service = $this->serviceModel->findById($serviceId);
        if (!$service) {
            Response::notFound('Service not found');
        }

        if ((int)$service['vendor_id'] !== (int)$vendor['id']) {
            Response::forbidden('You can only update your own services');
        }

        $body = Validator::getBody();
        $updateData = [];

        foreach (['name', 'description', 'duration', 'category'] as $field) {
            if (isset($body[$field])) {
                $updateData[$field] = Validator::sanitize($body[$field]);
            }
        }
        if (isset($body['price'])) {
            if (!is_numeric($body['price']) || $body['price'] < 0) {
                Response::error('Price must be a positive number', 400);
            }
            $updateData['price'] = (float)$body['price'];
        }
        if (isset($body['available'])) {
            $updateData['available'] = $body['available'] ? 1 : 0;
        }

        if (empty($updateData)) {
            Response::error('No data to update', 400);
        }

        $this->serviceModel->update($serviceId, $updateData);
        $updated = $this->serviceModel->findById($serviceId);
        Response::success($updated, 'Service updated successfully');
    }

    public function delete(array $params): void {
        $user = RoleAuth::requireVendor();
        $vendor = $this->vendorModel->findByUserId($user['id']);
        if (!$vendor) {
            Response::notFound('Vendor profile not found');
        }

        $serviceId = (int)($params['id'] ?? 0);
        $service = $this->serviceModel->findById($serviceId);
        if (!$service) {
            Response::notFound('Service not found');
        }

        if ((int)$service['vendor_id'] !== (int)$vendor['id']) {
            Response::forbidden('You can only delete your own services');
        }

        $this->serviceModel->delete($serviceId);
        Response::success(null, 'Service deleted successfully');
    }
}
