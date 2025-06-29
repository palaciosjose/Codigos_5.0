<?php
/**
 * API para obtener estadísticas del dashboard en tiempo real
 * Archivo: admin/get_dashboard_stats.php
 */

session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';

// Verificar autenticación de admin
check_session(true, '../index.php');

// Establecer headers para JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

try {
    // 1. BÚSQUEDAS HOY
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    
    $searches_today_query = "SELECT COUNT(*) as count FROM logs WHERE fecha BETWEEN ? AND ?";
    $stmt = $conn->prepare($searches_today_query);
    $stmt->bind_param("ss", $today_start, $today_end);
    $stmt->execute();
    $searches_today = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // 2. TASA DE ÉXITO (últimos 7 días para mejor estadística)
    $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    $success_rate_query = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN resultado LIKE '%Éxito%' OR resultado LIKE '%encontrado%' THEN 1 END) as successful
        FROM logs 
        WHERE fecha >= ?
    ";
    $stmt = $conn->prepare($success_rate_query);
    $stmt->bind_param("s", $week_ago);
    $stmt->execute();
    $success_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $success_rate = $success_data['total'] > 0 ? 
        round(($success_data['successful'] / $success_data['total']) * 100) : 0;

    // 3. USUARIOS ACTIVOS (conectados en las últimas 24 horas)
    $day_ago = date('Y-m-d H:i:s', strtotime('-24 hours'));
    
    $active_users_query = "
        SELECT COUNT(DISTINCT user_id) as count 
        FROM logs 
        WHERE fecha >= ? AND user_id IS NOT NULL
    ";
    $stmt = $conn->prepare($active_users_query);
    $stmt->bind_param("s", $day_ago);
    $stmt->execute();
    $active_users = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // 4. TIEMPO PROMEDIO DE RESPUESTA (simulado basado en actividad)
    // En un sistema real, medirías el tiempo real de respuesta
    $avg_time_query = "
        SELECT COUNT(*) as searches_last_hour
        FROM logs 
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    $recent_activity = $conn->query($avg_time_query)->fetch_assoc()['searches_last_hour'];
    
    // Calcular tiempo promedio basado en carga del servidor
    if ($recent_activity > 100) {
        $avg_response_time = 3.2; // Sistema ocupado
    } elseif ($recent_activity > 50) {
        $avg_response_time = 2.1; // Carga moderada
    } elseif ($recent_activity > 10) {
        $avg_response_time = 1.8; // Carga baja
    } else {
        $avg_response_time = 1.3; // Sistema libre
    }

    // 5. ESTADÍSTICAS ADICIONALES
    
    // Total de usuarios registrados
    $total_users_query = "SELECT COUNT(*) as count FROM users WHERE status = 1";
    $total_users = $conn->query($total_users_query)->fetch_assoc()['count'];
    
    // Servidores IMAP activos
    $active_servers_query = "SELECT COUNT(*) as count FROM email_servers WHERE enabled = 1";
    $active_servers = $conn->query($active_servers_query)->fetch_assoc()['count'];
    
    // Plataformas configuradas
    $platforms_query = "SELECT COUNT(*) as count FROM platforms";
    $total_platforms = $conn->query($platforms_query)->fetch_assoc()['count'];
    
    // Búsquedas de esta semana
    $week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $week_searches_query = "SELECT COUNT(*) as count FROM logs WHERE fecha >= ?";
    $stmt = $conn->prepare($week_searches_query);
    $stmt->bind_param("s", $week_start);
    $stmt->execute();
    $week_searches = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // 6. DATOS PARA GRÁFICO (búsquedas por hora del día actual)
    $hourly_data = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $hour_start = date('Y-m-d ' . sprintf('%02d', $hour) . ':00:00');
        $hour_end = date('Y-m-d ' . sprintf('%02d', $hour) . ':59:59');
        
        $hourly_query = "SELECT COUNT(*) as count FROM logs WHERE fecha BETWEEN ? AND ?";
        $stmt = $conn->prepare($hourly_query);
        $stmt->bind_param("ss", $hour_start, $hour_end);
        $stmt->execute();
        $hour_count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $hourly_data[] = [
            'hour' => $hour,
            'searches' => (int)$hour_count
        ];
    }

    // Respuesta JSON
    $stats = [
        'success' => true,
        'timestamp' => time(),
        'main_stats' => [
            'searches_today' => (int)$searches_today,
            'success_rate' => (int)$success_rate,
            'active_users' => (int)$active_users,
            'avg_response_time' => $avg_response_time
        ],
        'additional_stats' => [
            'total_users' => (int)$total_users,
            'active_servers' => (int)$active_servers,
            'total_platforms' => (int)$total_platforms,
            'week_searches' => (int)$week_searches
        ],
        'hourly_chart' => $hourly_data,
        'last_updated' => date('H:i:s')
    ];

    echo json_encode($stats);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
