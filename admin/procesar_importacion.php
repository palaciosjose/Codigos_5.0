<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';
require_once '../cache/cache_helper.php';
check_session(true, '../index.php');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset('utf8mb4');
$response = ['success' => false, 'message' => '', 'error' => ''];

if ($conn->connect_error) {
    $response['error'] = 'Error de conexión a la base de datos';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'import_authorized_emails' && isset($_FILES['import_file'])) {
        $file = $_FILES['import_file'];
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $tmp = $file['tmp_name'];
            $emails = [];

            if ($ext === 'txt') {
                $contents = file_get_contents($tmp);
                $parts = preg_split('/[\r\n,;]+/', $contents);
                foreach ($parts as $part) {
                    $email = trim($part);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $email;
                    }
                }
            } elseif ($ext === 'csv') {
                if (($handle = fopen($tmp, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        foreach ($row as $cell) {
                            $email = trim($cell);
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $emails[] = $email;
                            }
                        }
                    }
                    fclose($handle);
                }
            } elseif (in_array($ext, ['xlsx','xls'])) {
                $emails = readXlsxEmails($tmp);
            } else {
                $response['error'] = 'Tipo de archivo no soportado';
                echo json_encode($response);
                exit();
            }

            $emails = array_unique($emails);
            $inserted = 0;
            $stmt = $conn->prepare('INSERT IGNORE INTO authorized_emails (email) VALUES (?)');
            foreach ($emails as $em) {
                $stmt->bind_param('s', $em);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $inserted++;
                }
            }
            $stmt->close();
            $response['success'] = true;
            $response['message'] = "Correos importados: $inserted";
            SimpleCache::auto_reset_with_notification('all', false);
        } else {
            $response['error'] = 'Error al subir el archivo';
        }
    } else {
        $response['error'] = 'Solicitud inválida';
    }
}

header('Content-Type: application/json');
echo json_encode($response);

function readXlsxEmails($filePath) {
    $emails = [];
    if (!class_exists('ZipArchive')) {
        return $emails;
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) === true) {
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sxml = simplexml_load_string($xml);
            foreach ($sxml->si as $si) {
                $shared[] = (string)$si->t;
            }
        }
        if (($sheet = $zip->getFromName('xl/worksheets/sheet1.xml')) !== false) {
            $sx = simplexml_load_string($sheet);
            foreach ($sx->sheetData->row as $row) {
                $cell = $row->c[0];
                if (!$cell) continue;
                $value = (string)$cell->v;
                $type = (string)$cell['t'];
                if ($type === 's') {
                    $value = $shared[(int)$value] ?? '';
                }
                $email = trim($value);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }
        $zip->close();
    }
    return $emails;
}
?>
