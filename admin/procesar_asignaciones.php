<?php
// Asegurarse de que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir dependencias
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';

// Verificar autenticación (admin requerido)
check_session(true, '../index.php');

// Crear conexión a la base de datos
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4");

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos: ' . $conn->connect_error]);
    exit();
}

$action = $_REQUEST['action'] ?? null;

// Manejar diferentes métodos HTTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'assign_emails_to_user':
            $success = assignEmailsToUser($conn);
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                if ($success) {
                    echo json_encode(['success' => true, 'message' => $_SESSION['assignment_message'] ?? '']);
                } else {
                    echo json_encode(['success' => false, 'error' => $_SESSION['assignment_error'] ?? '']);
                }
            } else {
                header('Location: admin.php?tab=asignaciones');
            }
            exit();
        case 'remove_email_from_user':
            removeEmailFromUser($conn);
            break;
        case 'apply_template':
            $success = applyTemplate($conn);
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                if ($success) {
                    echo json_encode(['success' => true, 'message' => $_SESSION['assignment_message'] ?? '']);
                } else {
                    echo json_encode(['success' => false, 'error' => $_SESSION['assignment_error'] ?? '']);
                }
            } else {
                header('Location: asignaciones_masivas.php');
            }
            exit();
        case 'get_user_emails':
            getUserEmails($conn);
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Acción POST no válida.']);
            exit();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'get_user_emails':
            getUserEmails($conn);
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Acción GET no válida.']);
            exit();
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método de solicitud no soportado.']);
    exit();
}

function assignEmailsToUser($conn) {
    $user_id   = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $email_ids = $_POST['email_ids'] ?? [];
    $assigned_by = $_SESSION['user_id'] ?? null;

    if (!$user_id || !is_array($email_ids)) {
        $_SESSION['assignment_error'] = 'Datos incompletos para la asignación.';
        return false;
    }
    
    // Verificar que el usuario existe
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    if (!$stmt_check) {
        $_SESSION['assignment_error'] = 'Error al preparar consulta de verificación de usuario: ' . $conn->error;
        return false;
    }
    
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['assignment_error'] = 'Usuario no encontrado.';
        $stmt_check->close();
        return false;
    }
    $stmt_check->close();
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Eliminar asignaciones existentes para este usuario
        $stmt_delete = $conn->prepare("DELETE FROM user_authorized_emails WHERE user_id = ?");
        if (!$stmt_delete) {
            throw new Exception('Error al preparar eliminación de asignaciones: ' . $conn->error);
        }
        
        $stmt_delete->bind_param("i", $user_id);
        if (!$stmt_delete->execute()) {
            throw new Exception('Error al eliminar asignaciones existentes: ' . $stmt_delete->error);
        }
        $stmt_delete->close();
        
        // Insertar nuevas asignaciones
        if (!empty($email_ids)) {
            $stmt_insert = $conn->prepare("INSERT INTO user_authorized_emails (user_id, authorized_email_id, assigned_by) VALUES (?, ?, ?)");
            if (!$stmt_insert) {
                throw new Exception('Error al preparar inserción de asignaciones: ' . $conn->error);
            }
            
            $inserted = 0;
            foreach ($email_ids as $email_id) {
                $email_id_int = filter_var($email_id, FILTER_VALIDATE_INT);
                if ($email_id_int) {
                    $stmt_insert->bind_param("iii", $user_id, $email_id_int, $assigned_by);
                    if ($stmt_insert->execute()) {
                        $inserted++;
                    } else {
                        error_log("Error insertando asignación para user_id: $user_id, email_id: $email_id_int - " . $stmt_insert->error);
                    }
                }
            }
            $stmt_insert->close();
            
            $_SESSION['assignment_message'] = "Se asignaron $inserted correos al usuario correctamente.";
        } else {
            $_SESSION['assignment_message'] = "Se removieron todos los correos asignados al usuario.";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['assignment_error'] = 'Error en la transacción de asignación: ' . $e->getMessage();
        error_log("Error en asignación de emails: " . $e->getMessage());
        return false;
    }

    unset($_SESSION['assignment_error']);
    return true;
}

function removeEmailFromUser($conn) {
    header('Content-Type: application/json');
    
    $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $email_id = filter_var($_POST['email_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$user_id || !$email_id) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos para eliminar asignación']);
        exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM user_authorized_emails WHERE user_id = ? AND authorized_email_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error al preparar eliminación: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("ii", $user_id, $email_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar asignación: ' . $stmt->error]);
    }
    
    $stmt->close();
    exit();
}

function getUserEmails($conn) {
    // Limpiar cualquier salida previa
    if (ob_get_level()) {
        ob_clean();
    }
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    $user_id = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'ID de usuario inválido']);
        exit();
    }
    
    $query = "
        SELECT ae.id, ae.email, uae.assigned_at 
        FROM user_authorized_emails uae 
        JOIN authorized_emails ae ON uae.authorized_email_id = ae.id 
        WHERE uae.user_id = ? 
        ORDER BY ae.email ASC
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = [
                'id' => $row['id'],
                'email' => $row['email'],
                'assigned_at' => $row['assigned_at']
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'emails' => $emails,
            'count' => count($emails),
            'user_id' => $user_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al ejecutar la consulta: ' . $stmt->error]);
    }
    
    $stmt->close();
    exit();
}

function applyTemplate($conn) {
    $template_id = filter_var($_POST['template_id'] ?? null, FILTER_VALIDATE_INT);
    $user_ids = $_POST['user_ids'] ?? [];

    if(!$template_id || !is_array($user_ids) || empty($user_ids)) {
        $_SESSION['assignment_error'] = 'Datos incompletos para aplicar plantilla.';
        return false;
    }

    $tpl = $conn->query("SELECT email_ids FROM user_permission_templates WHERE id = " . intval($template_id));
    if(!$tpl || !$tpl->num_rows) {
        $_SESSION['assignment_error'] = 'Plantilla no encontrada';
        return false;
    }

    $row = $tpl->fetch_assoc();
    $email_ids = json_decode($row['email_ids'], true) ?? [];

    $successCount = 0;
    foreach($user_ids as $uid){
        $_POST['user_id'] = $uid;
        $_POST['email_ids'] = $email_ids;
        if(assignEmailsToUser($conn)) {
            $successCount++;
        }
    }

    $total = count($user_ids);
    if ($successCount === $total) {
        $_SESSION['assignment_message'] = 'Plantilla aplicada correctamente a todos los usuarios.';
        unset($_SESSION['assignment_error']);
        return true;
    }

    $_SESSION['assignment_error'] = "Plantilla aplicada parcialmente. Usuarios exitosos: $successCount de $total.";
    return false;
}

// Cerrar conexión
$conn->close();
?>