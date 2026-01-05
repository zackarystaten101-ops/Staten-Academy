<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($conn, $action);
        break;
    case 'POST':
        handlePost($conn, $action);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($conn, $action) {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'student';
    $teacherId = $_GET['teacherId'] ?? $userId;

    // Students can only view their teacher's vocabulary
    if ($userRole === 'student' && $teacherId != $userId) {
        // Verify teacher-student relationship
        $stmt = $conn->prepare("
            SELECT id FROM lessons 
            WHERE teacher_id = ? AND student_id = ? 
            LIMIT 1
        ");
        $stmt->bind_param("ii", $teacherId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
    }

    switch ($action) {
        case 'export':
            handleExport($conn, $teacherId);
            break;
        default:
            handleList($conn, $teacherId);
            break;
    }
}

function handleList($conn, $teacherId) {
    $category = $_GET['category'] ?? '';
    
    $sql = "SELECT * FROM vocabulary_words WHERE teacher_id = ?";
    $params = [$teacherId];
    $types = "i";
    
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($types === "i") {
        $stmt->bind_param($types, $params[0]);
    } else {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $words = [];
    while ($row = $result->fetch_assoc()) {
        $words[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['words' => $words]);
}

function handlePost($conn, $action) {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'student';
    
    // Only teachers can add vocabulary
    if ($userRole !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can add vocabulary']);
        return;
    }

    switch ($action) {
        case 'import':
            handleImport($conn, $userId);
            break;
        default:
            handleCreate($conn, $userId);
            break;
    }
}

function handleCreate($conn, $teacherId) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $word = $data['word'] ?? '';
    $definition = $data['definition'] ?? '';
    $example_sentence = $data['example_sentence'] ?? '';
    $category = $data['category'] ?? 'general';
    $audio_url = $data['audio_url'] ?? null;

    if (empty($word) || empty($definition)) {
        http_response_code(400);
        echo json_encode(['error' => 'Word and definition are required']);
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO vocabulary_words (teacher_id, word, definition, example_sentence, category, audio_url)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssss", $teacherId, $word, $definition, $example_sentence, $category, $audio_url);
    
    if ($stmt->execute()) {
        $wordId = $conn->insert_id;
        $stmt->close();
        
        // Fetch created word
        $stmt = $conn->prepare("SELECT * FROM vocabulary_words WHERE id = ?");
        $stmt->bind_param("i", $wordId);
        $stmt->execute();
        $result = $stmt->get_result();
        $word = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode(['word' => $word, 'success' => true]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create word']);
    }
}

function handlePut($conn) {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'student';
    
    if ($userRole !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can update vocabulary']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $wordId = $data['id'] ?? $_GET['id'] ?? 0;

    if ($wordId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid word ID']);
        return;
    }

    // Verify ownership
    $stmt = $conn->prepare("SELECT teacher_id FROM vocabulary_words WHERE id = ?");
    $stmt->bind_param("i", $wordId);
    $stmt->execute();
    $result = $stmt->get_result();
    $word = $result->fetch_assoc();
    $stmt->close();

    if (!$word || $word['teacher_id'] != $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $word_text = $data['word'] ?? '';
    $definition = $data['definition'] ?? '';
    $example_sentence = $data['example_sentence'] ?? '';
    $category = $data['category'] ?? 'general';
    $audio_url = $data['audio_url'] ?? null;

    $stmt = $conn->prepare("
        UPDATE vocabulary_words 
        SET word = ?, definition = ?, example_sentence = ?, category = ?, audio_url = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $word_text, $definition, $example_sentence, $category, $audio_url, $wordId);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update word']);
    }
}

function handleDelete($conn) {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'] ?? 'student';
    
    if ($userRole !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can delete vocabulary']);
        return;
    }

    $wordId = $_GET['id'] ?? 0;

    if ($wordId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid word ID']);
        return;
    }

    // Verify ownership
    $stmt = $conn->prepare("SELECT teacher_id FROM vocabulary_words WHERE id = ?");
    $stmt->bind_param("i", $wordId);
    $stmt->execute();
    $result = $stmt->get_result();
    $word = $result->fetch_assoc();
    $stmt->close();

    if (!$word || $word['teacher_id'] != $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM vocabulary_words WHERE id = ?");
    $stmt->bind_param("i", $wordId);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete word']);
    }
}

function handleExport($conn, $teacherId) {
    $format = $_GET['format'] ?? 'csv';

    $stmt = $conn->prepare("SELECT * FROM vocabulary_words WHERE teacher_id = ? ORDER BY category, word");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $words = [];
    while ($row = $result->fetch_assoc()) {
        $words[] = $row;
    }
    $stmt->close();

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="vocabulary-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Word', 'Definition', 'Example Sentence', 'Category']);
        
        foreach ($words as $word) {
            fputcsv($output, [
                $word['word'],
                $word['definition'],
                $word['example_sentence'] ?? '',
                $word['category']
            ]);
        }
        
        fclose($output);
    } else {
        // PDF export would require a library like TCPDF or FPDF
        // For now, return JSON
        echo json_encode(['words' => $words, 'format' => 'pdf', 'message' => 'PDF export not yet implemented']);
    }
}

function handleImport($conn, $teacherId) {
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        return;
    }

    $file = $_FILES['file'];
    if ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Only CSV files are supported']);
        return;
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read file']);
        return;
    }

    // Skip header row
    fgetcsv($handle);

    $imported = 0;
    $stmt = $conn->prepare("
        INSERT INTO vocabulary_words (teacher_id, word, definition, example_sentence, category)
        VALUES (?, ?, ?, ?, ?)
    ");

    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 2) {
            $word = $data[0] ?? '';
            $definition = $data[1] ?? '';
            $example = $data[2] ?? '';
            $category = $data[3] ?? 'general';

            if (!empty($word) && !empty($definition)) {
                $stmt->bind_param("issss", $teacherId, $word, $definition, $example, $category);
                if ($stmt->execute()) {
                    $imported++;
                }
            }
        }
    }

    $stmt->close();
    fclose($handle);

    echo json_encode(['success' => true, 'imported' => $imported]);
}











