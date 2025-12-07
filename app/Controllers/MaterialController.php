<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../Models/Material.php';

class MaterialController extends Controller {
    private $materialModel;
    
    public function __construct($conn) {
        parent::__construct($conn);
        $this->materialModel = new Material($conn);
    }
    
    /**
     * View classroom materials
     */
    public function index() {
        $this->requireAuth();
        $this->requireRole('teacher');
        
        $materials = $this->materialModel->getAll();
        
        $this->render('materials/index', ['materials' => $materials]);
    }
    
    /**
     * Upload material
     */
    public function upload() {
        $this->requireAuth();
        $this->requireRole('teacher');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $type = $_POST['type'] ?? 'file';
            $uploaded_by = $_SESSION['user_id'];
            
            $file_path = null;
            $link_url = null;
            $video_url = null;
            
            if ($type === 'file' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($ext, $allowed) && $file['size'] <= 20 * 1024 * 1024) {
                    $filename = 'material_' . $uploaded_by . '_' . time() . '.' . $ext;
                    $target = __DIR__ . '/../../public/uploads/materials/' . $filename;
                    
                    if (!is_dir(__DIR__ . '/../../public/uploads/materials')) {
                        mkdir(__DIR__ . '/../../public/uploads/materials', 0755, true);
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $file_path = '/uploads/materials/' . $filename;
                    }
                }
            } elseif ($type === 'link') {
                $link_url = trim($_POST['link_url'] ?? '');
            } elseif ($type === 'video') {
                $video_url = trim($_POST['video_url'] ?? '');
            }
            
            $data = [
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'file_path' => $file_path,
                'link_url' => $link_url,
                'video_url' => $video_url,
                'uploaded_by' => $uploaded_by
            ];
            
            $this->materialModel->createMaterial($data);
            $this->redirect('/materials');
        }
        
        $this->redirect('/materials');
    }
    
    /**
     * Delete material (admin only)
     */
    public function delete($id) {
        $this->requireAuth();
        $this->requireRole('admin');
        
        $material = $this->materialModel->find($id);
        if ($material && $material['file_path'] && file_exists('../public/' . $material['file_path'])) {
            unlink('../public/' . $material['file_path']);
        }
        
        $this->materialModel->delete($id);
        $this->redirect('/materials');
    }
}

