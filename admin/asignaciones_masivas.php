<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';
check_session(true, '../index.php');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset('utf8mb4');

$users = [];
$res = $conn->query("SELECT id, username FROM users ORDER BY username");
if($res){
    while($row = $res->fetch_assoc()){ $users[] = $row; }
}

$templates = [];
$res = $conn->query("SELECT id, name FROM user_permission_templates ORDER BY name");
if($res){
    while($row = $res->fetch_assoc()){ $templates[] = $row; }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignaci&oacute;n Masiva</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles/modern_global.css">
</head>
<body class="bg-dark text-white">
<div class="container py-4">
    <h1 class="mb-4">Asignar Permisos Masivamente</h1>
    <form action="procesar_asignaciones.php" method="post">
        <input type="hidden" name="action" value="apply_template">
        <div class="mb-3">
            <label class="form-label">Usuarios</label>
            <select name="user_ids[]" class="form-select" multiple size="5">
                <?php foreach($users as $u): ?>
                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Plantilla</label>
            <select name="template_id" class="form-select">
                <?php foreach($templates as $t): ?>
                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Aplicar</button>
        <a href="admin.php?tab=asignaciones" class="btn btn-secondary ms-2">Cancelar</a>
    </form>
</div>
</body>
</html>
