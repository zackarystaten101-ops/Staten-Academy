<?php
require_once __DIR__ . '/../../core/Model.php';

class User extends Model {
    protected $table = 'users';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
    
    /**
     * Find user by Google ID
     */
    public function findByGoogleId($googleId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE google_id = ?");
        $stmt->bind_param("s", $googleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
    
    /**
     * Create user with password
     */
    public function createWithPassword($data) {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        return $this->create($data);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hashedPassword) {
        return password_verify($password, $hashedPassword);
    }
    
    /**
     * Get teachers
     */
    public function getTeachers($conditions = []) {
        $conditions['role'] = 'teacher';
        return $this->all($conditions, 'name ASC');
    }
    
    /**
     * Get students
     */
    public function getStudents($conditions = []) {
        $conditions['role'] = 'student';
        return $this->all($conditions, 'name ASC');
    }
    
    /**
     * Update profile
     */
    public function updateProfile($id, $data) {
        return $this->update($id, $data);
    }
}

