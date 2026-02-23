<?php
// ─── Config ───────────────────────────────────────────────────────────────────
define('DATA_FILE', __DIR__ . '/EmployeeList.json');

// ─── CORS + JSON headers ──────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Router ───────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path   = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
// Extract last segment e.g. "api.php/employees/123" → id = 123
$segments = explode('/', $path);
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':
        getEmployees();
        break;
    case 'POST':
        addEmployee();
        break;
    case 'DELETE':
        deleteEmployee($id);
        break;
    default:
        respond(405, ['error' => 'Method not allowed']);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function respond(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function readData(): array {
    if (!file_exists(DATA_FILE)) return [];
    $json = file_get_contents(DATA_FILE);
    return json_decode($json, true) ?? [];
}

function writeData(array $employees): void {
    file_put_contents(DATA_FILE, json_encode($employees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ─── Validation ───────────────────────────────────────────────────────────────

function validateEmployee(array $data): array {
    $errors = [];

    // Full Name
    if (empty(trim($data['employeeName'] ?? ''))) {
        $errors['employeeName'] = 'Name is required';
    } elseif (strlen(trim($data['employeeName'])) < 2) {
        $errors['employeeName'] = 'Name is too short';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $data['employeeName'])) {
        $errors['employeeName'] = 'Name contains invalid characters';
    }

    // Gender
    $validGenders = ['Male', 'Female', 'Other'];
    if (empty($data['gender']) || !in_array($data['gender'], $validGenders)) {
        $errors['gender'] = 'Please select a valid gender';
    }

    // Marital Status
    $validStatuses = ['Single', 'Married', 'Divorced', 'Widowed'];
    if (empty($data['maritalStatus']) || !in_array($data['maritalStatus'], $validStatuses)) {
        $errors['maritalStatus'] = 'Please select a valid marital status';
    }

    // Phone
    if (empty(trim($data['phoneNo'] ?? ''))) {
        $errors['phoneNo'] = 'Phone number is required';
    } elseif (!preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $data['phoneNo'])) {
        $errors['phoneNo'] = 'Invalid phone number format';
    }

    // Email
    if (empty(trim($data['email'] ?? ''))) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email address';
    }

    // Address
    if (empty(trim($data['address'] ?? ''))) {
        $errors['address'] = 'Address is required';
    }

    // Date of Birth
    if (empty($data['dateOfBirth'])) {
        $errors['dateOfBirth'] = 'Date of birth is required';
    } else {
        $dob = DateTime::createFromFormat('Y-m-d', $data['dateOfBirth']);
        if (!$dob) {
            $errors['dateOfBirth'] = 'Invalid date format';
        } else {
            $age = $dob->diff(new DateTime())->y;
            if ($age < 16) $errors['dateOfBirth'] = 'Must be at least 16 years old';
            if ($age > 80) $errors['dateOfBirth'] = 'Invalid date of birth';
        }
    }

    // Nationality
    if (empty(trim($data['nationality'] ?? ''))) {
        $errors['nationality'] = 'Nationality is required';
    }

    // Hire Date
    if (empty($data['hireDate'])) {
        $errors['hireDate'] = 'Hire date is required';
    } else {
        $hire = DateTime::createFromFormat('Y-m-d', $data['hireDate']);
        if (!$hire) {
            $errors['hireDate'] = 'Invalid date format';
        } elseif ($hire > new DateTime()) {
            $errors['hireDate'] = 'Hire date cannot be in the future';
        }
    }

    // Department
    $validDepts = ['IT','HR','Finance','Marketing'];
    if (empty($data['department']) || !in_array($data['department'], $validDepts)) {
        $errors['department'] = 'Please select a valid department';
    }

    // Emergency Contact (optional but if provided, validate phone)
    if (!empty($data['emergencyPhone']) && !preg_match('/^\+?[\d\s\-\(\)]{7,15}$/', $data['emergencyPhone'])) {
        $errors['emergencyPhone'] = 'Invalid emergency contact phone';
    }

    // Salary (optional but if provided, must be numeric and positive)
    if (!empty($data['salary']) && (!is_numeric($data['salary']) || (float)$data['salary'] < 0)) {
        $errors['salary'] = 'Salary must be a positive number';
    }

    return $errors;
}

// ─── Sanitize input ───────────────────────────────────────────────────────────

function sanitize(array $data): array {
    $sanitized = [];
    $stringFields = [
        'employeeName','gender','maritalStatus','phoneNo','email','address',
        'dateOfBirth','nationality','hireDate','department','employeeId',
        'position','emergencyName','emergencyPhone','notes'
    ];
    foreach ($stringFields as $field) {
        $sanitized[$field] = htmlspecialchars(trim($data[$field] ?? ''), ENT_QUOTES, 'UTF-8');
    }
    $sanitized['salary'] = isset($data['salary']) ? (float)$data['salary'] : null;
    return $sanitized;
}

// ─── GET /api.php — list all employees ───────────────────────────────────────

function getEmployees(): void {
    $employees = readData();
    respond(200, [
        'success' => true,
        'count'   => count($employees),
        'data'    => $employees
    ]);
}

// ─── POST /api.php — add employee ────────────────────────────────────────────

function addEmployee(): void {
    $raw = json_decode(file_get_contents('php://input'), true);
    if (!$raw) {
        respond(400, ['success' => false, 'error' => 'Invalid JSON body']);
    }

    // Backend validation
    $errors = validateEmployee($raw);
    if (!empty($errors)) {
        respond(422, ['success' => false, 'errors' => $errors]);
    }

    // Sanitize + build record
    $data = sanitize($raw);
    $employees = readData();

    // Check duplicate email
    foreach ($employees as $emp) {
        if (strtolower($emp['email']) === strtolower($data['email'])) {
            respond(409, ['success' => false, 'errors' => ['email' => 'This email is already registered']]);
        }
    }

    // Auto-generate employee ID: EMP-0001, EMP-0002, etc.
    $lastId = 0;
    foreach ($employees as $emp) {
        if (isset($emp['employeeId']) && preg_match('/EMP-(\d+)/', $emp['employeeId'], $m)) {
            $lastId = max($lastId, (int)$m[1]);
        }
    }
    $data['employeeId'] = 'EMP-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
    $data['id']         = time() . rand(100, 999);
    $data['createdAt']  = date('Y-m-d H:i:s');

    $employees[] = $data;
    writeData($employees);

    respond(201, ['success' => true, 'data' => $data]);
}

// ─── DELETE /api.php?id=123 — remove employee ─────────────────────────────────

function deleteEmployee(?int $id): void {
    if (!$id) {
        respond(400, ['success' => false, 'error' => 'ID is required']);
    }
    $employees = readData();
    $filtered  = array_filter($employees, fn($e) => (int)$e['id'] !== $id);

    if (count($filtered) === count($employees)) {
        respond(404, ['success' => false, 'error' => 'Employee not found']);
    }

    writeData(array_values($filtered));
    respond(200, ['success' => true, 'message' => 'Employee deleted']);
}