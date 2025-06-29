<?php
// Inicia una sesión para almacenar datos temporales si no hay una sesión activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define("LOG_FILE", "decode.log");

function log_error($message) {
    $log_entry = date("Y-m-d H:i:s") . " - " . $message . PHP_EOL;
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

function exceptionHandler($exception) {
    log_error($exception->getMessage());
    $_SESSION["error_message"] = "Se ha producido un error al procesar el mensaje. Por favor, revisa los datos ingresados y vuelve a intentarlo. Si el problema persiste, contacta al soporte técnico.";
    header("Location: inicio.php");
    exit;
}

set_exception_handler("exceptionHandler");

// ================================================
// FUNCIONES DE VALIDACIÓN (CONSERVADAS)
// ================================================

function validate_body($body) {
    if (empty($body)) {
        throw new Exception("Error: El cuerpo del mensaje está vacío. Asegúrate de que el contenido no esté en blanco y vuelve a intentarlo.");
    }
}

function validate_size($body, $min_size = 4, $max_size = 1048576) {
    $size = strlen($body);
    if ($size < $min_size || $size > $max_size) {
        throw new Exception("Error: El tamaño del cuerpo no es válido. Actualmente tiene {$size} caracteres, y debe estar entre {$min_size} y {$max_size} caracteres.");
    }
}

function validate_quoted_printable_characters($body) {
    $valid_chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~ \t\r\n=";
    $invalid_chars = [];
    for ($i = 0; $i < strlen($body); $i++) {
        if (strpos($valid_chars, $body[$i]) === false) {
            $invalid_chars[] = $body[$i];
        }
    }
    if (!empty($invalid_chars)) {
        $invalid_chars_list = implode(", ", array_unique($invalid_chars));
        throw new Exception("Error: El cuerpo contiene caracteres no válidos para quoted-printable: '{$invalid_chars_list}'. Asegúrate de que el contenido solo contenga caracteres ASCII válidos.");
    }
}

function validate_and_sanitize_base64_characters($body) {
    $valid_chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
    $body = str_replace(array("\r", "\n", "\t", " "), '', $body);
    if (strlen($body) % 4 !== 0) {
        throw new Exception("Error: El cuerpo debe ser un múltiplo de 4 caracteres para Base64. Actualmente tiene " . strlen($body) . " caracteres. Asegúrate de que la longitud sea correcta.");
    }
    $invalid_chars = [];
    for ($i = 0; $i < strlen($body); $i++) {
        if (strpos($valid_chars, $body[$i]) === false) {
            $invalid_chars[] = $body[$i];
        }
    }
    if (!empty($invalid_chars)) {
        $invalid_chars_list = implode(", ", array_unique($invalid_chars));
        throw new Exception("Error: El cuerpo contiene caracteres no válidos para Base64: '{$invalid_chars_list}'. Asegúrate de que solo incluya caracteres válidos: A-Z, a-z, 0-9, +, / y =.");
    }
    return $body;
}

function validate_utf16_characters($body) {
    if (!mb_check_encoding($body, "UTF-16")) {
        throw new Exception("Error: El cuerpo contiene caracteres no válidos para UTF-16. Verifica que esté correctamente codificado en UTF-16.");
    }
}

function detect_and_convert_charset($body) {
    // Buscar charset en el HTML con regex más robusta
    if (preg_match("/charset\s*=\s*[\"']?([a-zA-Z0-9\-_]+)[\"']?/i", $body, $matches)) {
        $charset = strtoupper(trim($matches[1]));
        if ($charset === "UTF-8") {
            return $body;
        }
        
        // Lista de charsets soportados
        $supported_charsets = ['ISO-8859-1', 'US-ASCII', 'WINDOWS-1252', 'UTF-16', 'UTF-16LE', 'UTF-16BE'];
        
        if (in_array($charset, $supported_charsets)) {
            $converted = mb_convert_encoding($body, "UTF-8", $charset);
            if ($converted !== false && mb_check_encoding($converted, "UTF-8")) {
                return $converted;
            }
        }
    }
    return $body;
}

// ================================================
// FUNCIONES DE DECODIFICACIÓN ESPECÍFICAS (CONSERVADAS)
// ================================================

function decode_quoted_printable($body) {
    validate_body($body);
    validate_size($body);
    // validate_quoted_printable_characters($body); // Comentado para ser más permisivo
    $decoded_body = quoted_printable_decode($body);
    if ($decoded_body === false) {
        throw new Exception("Error: No se pudo decodificar el cuerpo del mensaje como quoted-printable.");
    }
    $decoded_body = detect_and_convert_charset($decoded_body);
    return $decoded_body;
}

function decode_base64($body) {
    $body = validate_and_sanitize_base64_characters($body);
    validate_body($body);
    validate_size($body);
    $decoded_body = base64_decode($body, true);
    if ($decoded_body === false) {
        throw new Exception("Error: No se pudo decodificar el cuerpo del mensaje en Base64.");
    }
    $decoded_body = detect_and_convert_charset($decoded_body);
    return $decoded_body;
}

function decode_utf16($body) {
    validate_body($body);
    validate_size($body);
    validate_utf16_characters($body);
    $decoded_body = mb_convert_encoding($body, "UTF-8", "UTF-16");
    if ($decoded_body === false) {
        throw new Exception("Error: No se pudo decodificar el cuerpo del mensaje en UTF-16.");
    }
    $decoded_body = detect_and_convert_charset($decoded_body);
    return $decoded_body;
}

// ================================================
// FUNCIÓN AUXILIAR PARA OBTENER PARTES DE EMAIL (CONSERVADA)
// ================================================

function get_email_part($inbox, $email_number, $part, $part_number = '') {
    // Si es multipart, procesar todas las partes
    if ($part->type == 1) { // multipart
        $html_content = '';
        $plain_content = '';
        
        if (isset($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $index => $subpart) {
                $prefix = $part_number ? $part_number . '.' : '';
                $current_part_number = $prefix . ($index + 1);
                $subpart_data = get_email_part($inbox, $email_number, $subpart, $current_part_number);
                
                if (is_array($subpart_data) && isset($subpart_data['mime']) && isset($subpart_data['content'])) {
                    if ($subpart_data['mime'] == 'html' && empty($html_content)) {
                        $html_content = $subpart_data['content'];
                    } else if ($subpart_data['mime'] == 'plain' && empty($plain_content)) {
                        $plain_content = $subpart_data['content'];
                    }
                }
            }
        }
        
        // Preferir HTML sobre plain text
        if (!empty($html_content)) {
            return ['mime' => 'html', 'content' => $html_content];
        } else if (!empty($plain_content)) {
            return ['mime' => 'plain', 'content' => $plain_content];
        }
        
        return null;
    }
    
    // Obtener el contenido de esta parte específica
    $part_number_to_use = $part_number ?: '1';
    $message = imap_fetchbody($inbox, $email_number, $part_number_to_use);
    
    if ($message === false || $message === '') {
        return null;
    }
    
    // Decodificar según el tipo de encoding
    switch ($part->encoding ?? 0) {
        case 0: // 7BIT
        case 1: // 8BIT
            // No necesita decodificación
            break;
        case 2: // BINARY
            // No necesita decodificación especial
            break;
        case 3: // BASE64
            $decoded = base64_decode($message);
            if ($decoded !== false) {
                $message = $decoded;
            }
            break;
        case 4: // QUOTED-PRINTABLE
            $decoded = quoted_printable_decode($message);
            if ($decoded !== false) {
                $message = $decoded;
            }
            break;
        case 5: // OTHER
            // Intentar quoted_printable si contiene caracteres de escape
            if (strpos($message, '=') !== false && preg_match('/=[0-9A-F]{2}/', $message)) {
                $temp = quoted_printable_decode($message);
                if ($temp !== false && $temp !== $message) {
                    $message = $temp;
                }
            }
            break;
    }
    
    // Determinar MIME type
    $mime_type = strtolower($part->subtype ?? 'plain');
    $charset = '';
    
    // Extraer charset de los parámetros
    if (isset($part->parameters) && is_array($part->parameters)) {
        foreach ($part->parameters as $param) {
            if (isset($param->attribute) && isset($param->value) && 
                strtolower($param->attribute) == 'charset') {
                $charset = $param->value;
                break;
            }
        }
    }
    
    // Buscar charset en dparameters si no se encontró
    if (!$charset && isset($part->dparameters) && is_array($part->dparameters)) {
        foreach ($part->dparameters as $param) {
            if (isset($param->attribute) && isset($param->value) && 
                strtolower($param->attribute) == 'charset') {
                $charset = $param->value;
                break;
            }
        }
    }
    
    // Convertir charset si es necesario
    if ($charset && strtoupper($charset) != 'UTF-8') {
        // Lista de charset comunes que podemos convertir
        $convertible_charsets = [
            'ISO-8859-1', 'US-ASCII', 'WINDOWS-1252', 
            'UTF-16', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-15'
        ];
        
        if (in_array(strtoupper($charset), $convertible_charsets)) {
            $converted = mb_convert_encoding($message, 'UTF-8', $charset);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $message = $converted;
            }
        }
    }
    
    return ['mime' => $mime_type, 'content' => $message];
}

// ================================================
// FUNCIÓN PRINCIPAL DE EXTRACCIÓN DE CUERPO DE EMAIL (CONSERVADA)
// ================================================

function get_email_body($inbox, $email_number, $header = null) {
    if (!$inbox || !$email_number) {
        error_log("Error: Parámetros inválidos para get_email_body");
        return '';
    }
    
    $structure = imap_fetchstructure($inbox, $email_number);
    if (!$structure) {
        error_log("Error: No se pudo obtener la estructura del email #$email_number");
        return '';
    }
    
    $html_body = '';
    $plain_body = '';
    
    try {
        // Procesar el email según su estructura
        if (isset($structure->parts) && is_array($structure->parts) && count($structure->parts) > 0) {
            // Email multipart
            foreach ($structure->parts as $index => $part) {
                $part_data = get_email_part($inbox, $email_number, $part, (string)($index + 1));
                
                if (is_array($part_data) && isset($part_data['mime']) && isset($part_data['content'])) {
                    if ($part_data['mime'] == 'html' && empty($html_body)) {
                        $html_body = $part_data['content'];
                    } else if ($part_data['mime'] == 'plain' && empty($plain_body)) {
                        $plain_body = $part_data['content'];
                    }
                }
            }
        } else {
            // Email simple (no multipart)
            $body_content = imap_body($inbox, $email_number);
            
            if ($body_content !== false && $body_content !== '') {
                // Decodificar según el encoding del mensaje principal
                if (isset($structure->encoding)) {
                    switch ($structure->encoding) {
                        case 3: // BASE64
                            $decoded = base64_decode($body_content);
                            if ($decoded !== false) {
                                $body_content = $decoded;
                            }
                            break;
                        case 4: // QUOTED-PRINTABLE
                            $decoded = quoted_printable_decode($body_content);
                            if ($decoded !== false) {
                                $body_content = $decoded;
                            }
                            break;
                    }
                }
                
                // Determinar si es HTML o texto plano
                if (preg_match('/<\s*(html|body|div|p|span|table|tr|td)\s*[^>]*>/i', $body_content)) {
                    $html_body = $body_content;
                } else {
                    $plain_body = $body_content;
                }
                
                // Intentar detectar y convertir charset para emails simples
                if (!empty($html_body)) {
                    $html_body = detect_and_convert_charset($html_body);
                }
                if (!empty($plain_body)) {
                    $plain_body = detect_and_convert_charset($plain_body);
                }
            }
        }
        
        // Retornar el mejor contenido disponible
        if (!empty($html_body) && trim($html_body) !== '') {
            return $html_body;
        } else if (!empty($plain_body) && trim($plain_body) !== '') {
            return $plain_body;
        }
        
        // Último recurso: intentar obtener el cuerpo de forma básica
        $basic_body = imap_fetchbody($inbox, $email_number, 1);
        if ($basic_body !== false && trim($basic_body) !== '') {
            // Intentar decodificar si es base64 o quoted-printable
            if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', $basic_body)) {
                $decoded = base64_decode($basic_body, true);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
            return $basic_body;
        }
        
    } catch (Exception $e) {
        error_log("Error procesando cuerpo del email #$email_number: " . $e->getMessage());
    }
    
    return '';
}

// ================================================
// FUNCIÓN DE PROCESAMIENTO MINIMALISTA - CONSERVA EL CONTENIDO ORIGINAL
// ================================================

function process_email_body($body) {
    if (empty($body) || trim($body) === '') {
        return '<div style="font-family: Arial, sans-serif; padding: 20px; color: #666; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    <i style="font-size: 24px;">📧</i><br>
                    <strong>Sin contenido disponible</strong><br>
                    No se pudo obtener el contenido del email.
                </div>';
    }
    
    // Log para debugging
    error_log("PROCESAMIENTO MINIMALISTA - Longitud original: " . strlen($body));
    
    // SOLO aplicar conversiones de charset básicas
    if (preg_match('/charset\s*=\s*["\']?([a-zA-Z0-9\-_]+)["\']?/i', $body, $matches)) {
        $charset = strtoupper(trim($matches[1]));
        if ($charset !== 'UTF-8') {
            $converted = mb_convert_encoding($body, 'UTF-8', $charset);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $body = $converted;
            }
        }
    }
    
    // Limpiar saltos de línea inconsistentes ÚNICAMENTE
    $body = preg_replace('/\r\n/', "\n", $body);
    $body = preg_replace('/\r/', "\n", $body);
    
    // Detectar si es HTML
    $is_html = preg_match('/<\s*(html|body|div|p|span|table|tr|td|h[1-6]|strong|em|br|img|a)\s*[^>]*>/i', $body);
    
    if (!$is_html) {
        // TEXTO PLANO: Conversión mínima
        $body = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
        $body = nl2br($body);
        
        // Envolver en contenedor básico
        $body = '<div style="font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; background: #ffffff; color: #333333; border-radius: 8px;">' . $body . '</div>';
    } else {
        // HTML: PROCESAMIENTO MÍNIMO - Solo seguridad básica
        
        // Remover solo elementos realmente peligrosos
        $body = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $body);
        $body = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $body);
        $body = preg_replace('/javascript\s*:/i', '#blocked:', $body);
        
        // Hacer enlaces seguros (abrir en nueva pestaña)
        $body = preg_replace('/<a\s+(?![^>]*target\s*=)([^>]*?)href\s*=/i', '<a target="_blank" rel="noopener noreferrer" $1href=', $body);
        
        // SOLO arreglar imágenes rotas básicas
        $body = preg_replace('/src\s*=\s*["\']cid:([^"\']+)["\']/i', 'src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xNiAxNkg4VjI0SDE2VjE2WiIgZmlsbD0iIzlDQTNBRiIvPgo8cGF0aCBkPSJNMzIgMTZIMjRWMjRIMzJWMTZaIiBmaWxsPSIjOUNBM0FGIi8+CjxwYXRoIGQ9Ik0yNCAxNkgxNlYyNEgyNFYxNloiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+"', $body);
    }
    
    // NO agregar estructura HTML completa - dejar que el navegador maneje el contenido original
    error_log("PROCESAMIENTO MINIMALISTA COMPLETADO - Longitud final: " . strlen($body));
    
    return $body;
}

// ================================================
// FUNCIÓN DE DEBUG (CONSERVADA)
// ================================================

function debug_email_structure($inbox, $email_number) {
    if (!$inbox || !$email_number) {
        echo "<div style='color: red;'>Error: Parámetros inválidos para debug</div>";
        return;
    }
    
    $structure = imap_fetchstructure($inbox, $email_number);
    if (!$structure) {
        echo "<div style='color: red;'>Error: No se pudo obtener la estructura del email</div>";
        return;
    }
    
    echo "<div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; border: 1px solid #dee2e6;'>";
    echo "<h3 style='color: #007bff; margin-top: 0;'>🔍 Debug - Estructura del Email #$email_number</h3>";
    echo "<pre style='background: white; padding: 10px; border-radius: 3px; overflow-x: auto; border: 1px solid #e9ecef;'>";
    
    echo "Encoding principal: " . ($structure->encoding ?? 'No definido') . " (";
    switch ($structure->encoding ?? 0) {
        case 0: echo "7BIT"; break;
        case 1: echo "8BIT"; break;
        case 2: echo "BINARY"; break;
        case 3: echo "BASE64"; break;
        case 4: echo "QUOTED-PRINTABLE"; break;
        case 5: echo "OTHER"; break;
        default: echo "DESCONOCIDO";
    }
    echo ")\n";
    
    echo "Tipo: " . ($structure->type ?? 'No definido') . "\n";
    echo "Subtipo: " . ($structure->subtype ?? 'No definido') . "\n";
    echo "Tamaño: " . ($structure->bytes ?? 'No definido') . " bytes\n";
    
    if (isset($structure->parts) && is_array($structure->parts)) {
        echo "Número de partes: " . count($structure->parts) . "\n\n";
        
        foreach ($structure->parts as $index => $part) {
            echo "--- Parte " . ($index + 1) . " ---\n";
            echo "Tipo: " . ($part->type ?? 'No definido') . " (";
            switch ($part->type ?? 0) {
                case 0: echo "TEXT"; break;
                case 1: echo "MULTIPART"; break;
                case 2: echo "MESSAGE"; break;
                case 3: echo "APPLICATION"; break;
                case 4: echo "AUDIO"; break;
                case 5: echo "IMAGE"; break;
                case 6: echo "VIDEO"; break;
                case 7: echo "OTHER"; break;
                default: echo "DESCONOCIDO";
            }
            echo ")\n";
            echo "Subtipo: " . ($part->subtype ?? 'No definido') . "\n";
            echo "Encoding: " . ($part->encoding ?? 'No definido') . " (";
            switch ($part->encoding ?? 0) {
                case 0: echo "7BIT"; break;
                case 1: echo "8BIT"; break;
                case 2: echo "BINARY"; break;
                case 3: echo "BASE64"; break;
                case 4: echo "QUOTED-PRINTABLE"; break;
                case 5: echo "OTHER"; break;
                default: echo "DESCONOCIDO";
            }
            echo ")\n";
            echo "Tamaño: " . ($part->bytes ?? 'No definido') . " bytes\n";
            
            if (isset($part->parameters) && is_array($part->parameters)) {
                echo "Parámetros:\n";
                foreach ($part->parameters as $param) {
                    if (isset($param->attribute) && isset($param->value)) {
                        echo "  " . $param->attribute . " = " . $param->value . "\n";
                    }
                }
            }
            
            if (isset($part->dparameters) && is_array($part->dparameters)) {
                echo "DParámetros:\n";
                foreach ($part->dparameters as $param) {
                    if (isset($param->attribute) && isset($param->value)) {
                        echo "  " . $param->attribute . " = " . $param->value . "\n";
                    }
                }
            }
            echo "\n";
        }
    } else {
        echo "Email simple (sin partes múltiples)\n";
    }
    echo "</pre>";
    echo "</div>";
    
    // Test de extracción de contenido
    echo "<div style='background: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #b8daff;'>";
    echo "<h4 style='color: #004085; margin-top: 0;'>🧪 Test de Extracción de Contenido</h4>";
    
    $test_body = get_email_body($inbox, $email_number);
    if (!empty($test_body)) {
        echo "<p style='color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;'>✅ Contenido extraído exitosamente (" . strlen($test_body) . " caracteres)</p>";
        echo "<p><strong>Primeros 300 caracteres del contenido original:</strong></p>";
        echo "<div style='background: white; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; max-height: 150px; overflow-y: auto;'>";
        echo htmlspecialchars(substr($test_body, 0, 300)) . (strlen($test_body) > 300 ? '...' : '');
        echo "</div>";
    } else {
        echo "<p style='color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;'>❌ No se pudo extraer contenido del email</p>";
    }
    echo "</div>";
}

?>
