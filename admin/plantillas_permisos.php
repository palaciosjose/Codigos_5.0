<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';
check_session(true, '../index.php');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset('utf8mb4');

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $conn->query("INSERT INTO user_permission_templates (name, description, created_by) VALUES ('$name','$description',".$_SESSION['user_id'].")");
}

$templates=[];
$res=$conn->query("SELECT id, name, description FROM user_permission_templates ORDER BY created_at DESC");
if($res){ while($row=$res->fetch_assoc()){ $templates[]=$row; } }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Plantillas de Permisos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles/modern_global.css">
</head>
<body class="bg-dark text-white">
<div class="container py-4">
    <h1 class="mb-4">Plantillas de Permisos</h1>
    <form method="post" class="mb-3">
        <div class="mb-3"><input type="text" name="name" class="form-control" placeholder="Nombre" required></div>
        <div class="mb-3"><textarea name="description" class="form-control" placeholder="Descripci&oacute;n"></textarea></div>
        <button type="submit" class="btn btn-primary">Crear</button>
        <a href="admin.php?tab=asignaciones" class="btn btn-secondary ms-2">Volver</a>
    </form>
    <table class="table table-dark">
        <thead><tr><th>Nombre</th><th>Descripci&oacute;n</th></tr></thead>
        <tbody>
            <?php foreach($templates as $tpl): ?>
            <tr><td><?php echo htmlspecialchars($tpl['name']); ?></td><td><?php echo htmlspecialchars($tpl['description']); ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
