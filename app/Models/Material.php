<?php
require_once __DIR__ . '/../../core/Model.php';

class Material extends Model {
    protected $table = 'classroom_materials';
    
    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    /**
     * Get all materials
     */
    public function getAll() {
        $sql = "SELECT cm.*, u.name as uploader_name 
                FROM {$this->table} cm 
                JOIN users u ON cm.uploaded_by = u.id 
                ORDER BY cm.created_at DESC";
        return $this->query($sql);
    }
    
    /**
     * Get materials by type
     */
    public function getByType($type) {
        return $this->all(['type' => $type], 'created_at DESC');
    }
    
    /**
     * Create material
     */
    public function createMaterial($data) {
        return $this->create($data);
    }
}

