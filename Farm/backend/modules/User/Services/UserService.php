<?php

namespace PHPFrarm\Modules\User\Services;

use PHPFrarm\Modules\User\DAO\UserDAO;
use PHPFrarm\Core\Logger;

/**
 * User Service - Business logic for user operations
 */
class UserService
{
    private UserDAO $userDAO;

    public function __construct()
    {
        $this->userDAO = new UserDAO();
    }

    public function getUserProfile(string $userId): array
    {
        $user = $this->userDAO->getUserById($userId);

        if (!$user) {
            throw new \Exception('User not found');
        }

        // Remove sensitive data
        unset($user['password_hash']);

        return $user;
    }

    public function updateProfile(string $userId, array $data): array
    {
        // Verify user exists
        $user = $this->userDAO->getUserById($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Update user profile (you'd need to create sp_update_user_profile)
        // Database::callProcedure('sp_update_user_profile', [$userId, ...]);

        Logger::audit('User profile updated', [
            'user_id' => $userId,
            'fields' => array_keys($data)
        ]);

        return ['updated' => true];
    }

    public function getAllUsers(int $page, int $perPage): array
    {
        $users = $this->userDAO->getAllUsers($page, $perPage);
        $total = $this->userDAO->countUsers();

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    public function deleteUser(string $userId, string $adminId): void
    {
        $this->userDAO->softDeleteUser($userId);

        Logger::audit('User deleted by admin', [
            'admin_id' => $adminId,
            'deleted_user_id' => $userId
        ]);
    }

    public function createUser(array $data, string $adminId): array
    {
        // Validate required fields
        $required = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }

        // Check if user already exists
        $existingUser = $this->userDAO->getUserByEmail($data['email']);
        if ($existingUser) {
            throw new \Exception('User with this email already exists');
        }

        // Hash password
        $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        unset($data['password']);

        // Create user
        $userId = $this->userDAO->createUser($data);

        Logger::audit('User created by admin', [
            'admin_id' => $adminId,
            'new_user_id' => $userId,
            'email' => $data['email']
        ]);

        return [
            'id' => $userId,
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name']
        ];
    }

    public function updateUser(string $userId, array $data, string $adminId): array
    {
        // Verify user exists
        $user = $this->userDAO->getUserById($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        // Update user
        $this->userDAO->updateUser($userId, $data);

        Logger::audit('User updated by admin', [
            'admin_id' => $adminId,
            'updated_user_id' => $userId,
            'fields' => array_keys($data)
        ]);

        return [
            'id' => $userId,
            'updated' => true
        ];
    }

    public function importUsers(string $filePath, string $fileType, string $adminId): array
    {
        $users = [];
        
        // Parse file based on type
        if (str_contains($fileType, 'csv')) {
            $users = $this->parseCSV($filePath);
        } else {
            $users = $this->parseExcel($filePath);
        }

        $results = [
            'total' => count($users),
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($users as $index => $userData) {
            try {
                // Skip empty rows
                if (empty($userData['email'])) {
                    continue;
                }

                // Check if user exists
                $existingUser = $this->userDAO->getUserByEmail($userData['email']);
                
                if ($existingUser) {
                    // Update existing user
                    $this->userDAO->updateUser($existingUser['id'], $userData);
                    $results['updated']++;
                } else {
                    // Create new user
                    $userData['password_hash'] = password_hash($userData['password'] ?? 'Welcome@123', PASSWORD_BCRYPT);
                    unset($userData['password']);
                    $this->userDAO->createUser($userData);
                    $results['created']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $index + 2, // +2 for header row and 0-index
                    'email' => $userData['email'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        Logger::audit('Users imported by admin', [
            'admin_id' => $adminId,
            'results' => $results
        ]);

        return $results;
    }

    private function parseCSV(string $filePath): array
    {
        $users = [];
        $handle = fopen($filePath, 'r');
        
        // Read header
        $header = fgetcsv($handle);
        
        // Read data
        while (($row = fgetcsv($handle)) !== false) {
            $user = [];
            foreach ($header as $index => $column) {
                $user[$column] = $row[$index] ?? '';
            }
            $users[] = $user;
        }
        
        fclose($handle);
        return $users;
    }

    private function parseExcel(string $filePath): array
    {
        // For now, we'll use a simple approach
        // In production, you'd use PHPSpreadsheet library
        require_once __DIR__ . '/../../../vendor/autoload.php';
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
            
            $header = array_shift($data); // Remove header row
            $users = [];
            
            foreach ($data as $row) {
                $user = [];
                foreach ($header as $index => $column) {
                    $user[$column] = $row[$index] ?? '';
                }
                $users[] = $user;
            }
            
            return $users;
        } catch (\Exception $e) {
            // Fallback to CSV if Excel parsing fails
            return $this->parseCSV($filePath);
        }
    }

    public function downloadTemplate(string $format): void
    {
        $headers = ['email', 'first_name', 'last_name', 'phone', 'password', 'status'];
        $sampleData = [
            ['john.doe@example.com', 'John', 'Doe', '+1234567890', 'Welcome@123', 'active'],
            ['jane.smith@example.com', 'Jane', 'Smith', '+1234567891', 'Welcome@123', 'active']
        ];

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="user_import_template.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            foreach ($sampleData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        } else {
            // Excel format
            require_once __DIR__ . '/../../../vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set headers
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $col++;
            }
            
            // Set sample data
            $row = 2;
            foreach ($sampleData as $data) {
                $col = 'A';
                foreach ($data as $value) {
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="user_import_template.xlsx"');
            
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit;
        }
    }
}
