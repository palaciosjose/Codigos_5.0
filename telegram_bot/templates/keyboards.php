<?php
// telegram_bot/templates/keyboards.php
// Definición de todos los teclados/botones del bot

return [
    'start' => [
        'inline_keyboard' => [
            [
                ['text' => '🔍 Buscar Códigos', 'callback_data' => 'search_menu'],
                ['text' => '❓ Ayuda', 'callback_data' => 'help']
            ],
            [
                ['text' => '⚙️ Mi Configuración', 'callback_data' => 'config'],
                ['text' => '📊 Estadísticas', 'callback_data' => 'stats']
            ]
        ]
    ],
    
    'search_menu' => [
        'inline_keyboard' => [
            [
                ['text' => '📧 Buscar por Email', 'callback_data' => 'search_email'],
                ['text' => '🆔 Buscar por ID', 'callback_data' => 'search_id']
            ],
            [
                ['text' => '📋 Plataformas Disponibles', 'callback_data' => 'list_platforms'],
                ['text' => '🔙 Volver al Inicio', 'callback_data' => 'start_menu']
            ]
        ]
    ],
    
    'help_menu' => [
        'inline_keyboard' => [
            [
                ['text' => '🚀 Comandos Básicos', 'callback_data' => 'help_commands'],
                ['text' => '🔍 Cómo Buscar', 'callback_data' => 'help_search']
            ],
            [
                ['text' => '⚙️ Configuración', 'callback_data' => 'help_config'],
                ['text' => '🔙 Menú Principal', 'callback_data' => 'start_menu']
            ]
        ]
    ],
    
    'admin_menu' => [
        'inline_keyboard' => [
            [
                ['text' => '📊 Ver Estadísticas', 'callback_data' => 'admin_stats'],
                ['text' => '👥 Usuarios Activos', 'callback_data' => 'admin_users']
            ],
            [
                ['text' => '🔧 Configuración Sistema', 'callback_data' => 'admin_config'],
                ['text' => '📝 Logs del Sistema', 'callback_data' => 'admin_logs']
            ],
            [
                ['text' => '🔙 Volver al Inicio', 'callback_data' => 'start_menu']
            ]
        ]
    ],
    
    'back_to_start' => [
        'inline_keyboard' => [
            [
                ['text' => '🔙 Volver al Inicio', 'callback_data' => 'start_menu']
            ]
        ]
    ]
];