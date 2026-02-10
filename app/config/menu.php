<?php

return [
    ['url' => '/home', 'icon' => 'home', 'text' => "Главная", 'controller' => 'home', 'action' => 'index'],
    ['url' => '/moderator_main', 'icon' => 'home', 'text' => "Главная", 'controller' => 'moderator_main', 'action' => 'index'],
    ['url' => '/admin_main', 'icon' => 'home', 'text' => "Главная", 'controller' => 'admin_main', 'action' => 'index'],
    ['url' => '/order', 'icon' => 'folder', 'text' => "Заявки", 'controller' => 'order', 'action' => 'index'],
    ['url' => '/correction_request', 'icon' => 'folder', 'text' => "Заявки на корректировку", 'controller' => 'correction_request', 'action' => 'index', 'name' => 'correction_request'],
    ['url' => '/admin_bank/bank', 'icon' => 'dollar-sign', 'text' => "bank-transfers", 'controller' => 'admin_bank', 'action' => 'bank'],
    ['url' => '/zd_admin_bank/bank', 'icon' => 'dollar-sign', 'text' => "zd-bank-transfers", 'controller' => 'zd_admin_bank', 'action' => 'bank'],
    ['url' => '/operator_bank/bank', 'icon' => 'dollar-sign', 'text' => "bank-transfers", 'controller' => 'operator_bank', 'action' => 'bank'],
    ['url' => '/zd_operator_bank/bank', 'icon' => 'dollar-sign', 'text' => "zd-bank-transfers", 'controller' => 'zd_operator_bank', 'action' => 'bank'],
    ['url' => '/accountant_order/index', 'icon' => 'folder', 'text' => "Подписание заявок", 'controller' => 'accountant_order', 'action' => 'index'],
    ['url' => '/operator_order', 'icon' => 'folder', 'text' => "Заявки", 'controller' => 'operator_order', 'action' => 'index'],
    ['url' => '/moderator_order', 'icon' => 'folder', 'text' => "applications", 'controller' => 'moderator_order', 'action' => 'index'],
    ['url' => '/create_order', 'icon' => 'folder', 'text' => "Создание заявки", 'controller' => 'create_order', 'action' => 'index'],
    ['url' => '/moderator_fund', 'icon' => 'credit-card', 'text' => "Финансирование", 'controller' => 'moderator_fund', 'action' => 'index'],
    ['url' => '/fund', 'icon' => 'credit-card', 'text' => "Финансирование", 'controller' => 'moderator_fund', 'action' => 'index'],
    ['url' => '/offset_fund', 'icon' => 'folder', 'text' => "Финансирование методом взаимозачёта", 'controller' => 'offset_fund', 'action' => 'index'],
    ['url' => '/moderator_order/check_order', 'icon' => 'search', 'text' => "Сервисы", 'controller' => 'moderator_order', 'action' => 'check_order'],

    [
        'text' => "Интеграции",
        'icon' => 'sliders',
        'submenu' => [
            ['url' => '/epts', 'icon' => 'search', 'text' => "Интеграция с ЭПТС", 'controller' => 'epts', 'action' => 'index'],
            ['url' => '/kap_request/new', 'icon' => 'credit-card', 'text' => "kap_integration", 'controller' => 'kap_request', 'action' => 'new'],
            ['url' => '/msx', 'icon' => 'search', 'text' => "Интеграция с МСХ (ГРСТ)", 'controller' => 'msx', 'action' => 'index'],
            ['url' => '/kap_request', 'icon' => 'credit-card', 'text' => "kap_integration" . " (old)", 'controller' => 'kap_request', 'action' => 'index'],
        ]
    ],

    [
        'text' => "Сверка с КАП",
        'icon' => 'sliders',
        'submenu' => [
            ['url' => '/app_core/svup', 'icon' => 'search', 'text' => "Сверка по СВУП", 'controller' => 'app_core', 'action' => '*'],
            ['url' => '/app_core/annulment', 'icon' => 'search', 'text' => "Сверка по аннулированным сертификатам", 'controller' => 'app_core', 'action' => '*'],
        ]
    ],

    ['url' => '/test', 'icon' => 'search', 'text' => "Проверка excel", 'controller' => 'test', 'action' => 'index'],

    [
        'name' => 'moderator_correction_request',
        'text' => "Корректировки",
        'icon' => 'sliders',
        'submenu' => [
            ['url' => '/correction_request', 'text' => "Корректировки внешние", 'controller' => 'correction_request', 'action' => 'index'],
            ['url' => '/correction', 'text' => "Корректировки внутренние", 'controller' => 'correction', 'action' => 'index'],
            ['url' => '/correction_logs', 'text' => "История корректировки", 'controller' => 'correction_logs', 'action' => 'index']
        ]
    ],

    [
        'text' => "report-and-data",
        'icon' => 'sliders',
        'submenu' => [
            ['url' => '/report_importer', 'text' => "reports", 'controller' => 'report_importer', 'action' => 'index'],
            ['url' => '/report_realization', 'text' => "Отчеты по реализации", 'controller' => 'report_realization', 'action' => 'index'],
            ['url' => '/report_admin', 'text' => "Админские отчеты", 'controller' => 'report_admin', 'action' => 'index'],
            ['url' => '/sqlexport', 'text' => "SQL запрос", 'controller' => 'sqlexport', 'action' => 'index'],
        ]
    ],
    [
        'text' => "Логи",
        'icon' => 'settings',
        'name' => 'logs',
        'submenu' => [
            ['url' => '/correction_logs/file_logs', 'text' => "История файлов", 'controller' => 'correction_logs', 'action' => 'file_logs'],
            ['url' => '/correction_logs/ref_fund_logs', 'text' => "История лимитов(FUND)", 'controller' => 'correction_logs', 'action' => 'ref_fund_logs']
        ]
    ],

    [
        'text' => "system-menu",
        'icon' => 'settings',
        'submenu' => [
            ['url' => '/ref_bank', 'text' => "references", 'controller' => 'ref_bank', 'action' => 'index'],
            ['url' => '/users', 'text' => "users", 'controller' => 'users', 'action' => 'index'],
            ['url' => '/role', 'text' => "Роли", 'controller' => 'role', 'action' => 'index']
        ]
    ],

    [
        'text' => "user-menu",
        'icon' => 'users',
        'submenu' => []
    ]
];
