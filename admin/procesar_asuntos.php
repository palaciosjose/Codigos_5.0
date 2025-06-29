<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';

check_session(true, '../index.php');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4");

$action = $_REQUEST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'get_assignments':
            header('Content-Type: application/json');
            $users = [];
            $platforms = [];

            $res = $conn->query("SELECT id, username FROM users WHERE id NOT IN (SELECT id FROM admin) ORDER BY username ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $users[$row['id']] = ['id' => $row['id'], 'username' => $row['username']];
                }
                $res->close();
            }

            $res = $conn->query("SELECT id, name FROM platforms ORDER BY sort_order ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $platforms[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'subjects' => []];
                }
                $res->close();
            }

            if (!empty($platforms)) {
                $ids = implode(',', array_keys($platforms));
                $res = $conn->query("SELECT platform_id, subject FROM platform_subjects WHERE platform_id IN ($ids) ORDER BY subject ASC");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $platforms[$row['platform_id']]['subjects'][] = $row['subject'];
                    }
                    $res->close();
                }
            }

            $assignments = [];
            $res = $conn->query("SELECT user_id, platform_id, subject_keyword FROM user_platform_subjects");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $assignments[$row['user_id']][$row['platform_id']][] = $row['subject_keyword'];
                }
                $res->close();
            }

            echo json_encode(['success' => true, 'users' => array_values($users), 'platforms' => array_values($platforms), 'assignments' => $assignments]);
            exit();

        case 'get_user_assignments':
            header('Content-Type: application/json');
            $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) {
                echo json_encode(['success' => false, 'error' => 'ID de usuario no válido']);
                exit();
            }

            // 1. Obtener todas las plataformas
            $platforms = [];
            $res_platforms = $conn->query("SELECT id, name FROM platforms ORDER BY sort_order ASC");
            if ($res_platforms) {
                while ($row = $res_platforms->fetch_assoc()) {
                    $platforms[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'subjects' => []];
                }
            }

            // 2. Obtener todos los asuntos para esas plataformas
            if (!empty($platforms)) {
                $platform_ids_str = implode(',', array_keys($platforms));
                $res_subjects = $conn->query("SELECT platform_id, subject FROM platform_subjects WHERE platform_id IN ($platform_ids_str) ORDER BY subject ASC");
                if ($res_subjects) {
                    while ($row = $res_subjects->fetch_assoc()) {
                        if (isset($platforms[$row['platform_id']])) {
                            $platforms[$row['platform_id']]['subjects'][] = $row['subject'];
                        }
                    }
                }
            }

            // 3. Obtener solo las asignaciones para ESTE usuario
            $user_assignments = [];
            $stmt = $conn->prepare("SELECT platform_id, subject_keyword FROM user_platform_subjects WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $user_assignments[$row['platform_id']][] = $row['subject_keyword'];
                }
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'platforms' => array_values($platforms),
                'assignments' => [$user_id => $user_assignments]
            ]);
            exit();
            
        default:
            // No hacer nada si la acción no coincide
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_assignment') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id'] ?? 0);
    $platform_id = intval($data['platform_id'] ?? 0);
    $subjects = $data['subjects'] ?? [];
    $resp = ['success' => false];
    if ($user_id && $platform_id && is_array($subjects)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM user_platform_subjects WHERE user_id=? AND platform_id=?");
            $stmt->bind_param('ii', $user_id, $platform_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($subjects)) {
                $stmt = $conn->prepare("INSERT INTO user_platform_subjects (user_id, platform_id, subject_keyword) VALUES (?, ?, ?)");
                foreach ($subjects as $subj) {
                    $stmt->bind_param('iis', $user_id, $platform_id, $subj);
                    $stmt->execute();
                }
                $stmt->close();
            }
            $conn->commit();
            $resp['success'] = true;
        } catch (Exception $e) {
            $conn->rollback();
            $resp['error'] = $e->getMessage();
        }
    } else {
        $resp['error'] = 'Datos inválidos';
    }
    echo json_encode($resp);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
