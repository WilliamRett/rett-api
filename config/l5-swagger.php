<?php

return [
    'default' => 'default',

    'documentations' => [
        'default' => [
            // meta da doc
            'api' => [
                'title' => 'Rett API',
                'paths' => [
                    'docs' => 'storage/api-docs',
                ],
            ],

            // ROTAS ESPECÍFICAS DESTA DOC
            'routes' => [
                // 👇 ESTA É A PÁGINA HTML DO SWAGGER
                'docs' => 'api/docs',

                // 👇 ESTE É O JSON QUE O SWAGGER UI VAI BUSCAR
                'api'  => 'api/docs.json',

                'oauth2_callback' => 'api/oauth2-callback',

                // se quiser proteger com auth web/api, põe aqui
                'middleware' => [
                    'api'             => [],
                    'asset'           => [],
                    'docs'            => [],
                    'oauth2_callback' => [],
                ],
            ],

            // caminhos
            'paths' => [
                // 🔴 deixa false pra não tentar bater em http://127.0.0.1:8000 de dentro do container
                'use_absolute_path'      => env('L5_SWAGGER_USE_ABSOLUTE_PATH', false),

                // assets do swagger
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

                // nome do arquivo gerado
                'docs_json'              => 'api-docs.json',
                'docs_yaml'              => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),

                // onde o l5-swagger vai escanear
                'annotations' => [
                    base_path('app/Http/Controllers'),
                    base_path('app/Http/Requests'),
                    base_path('app/Docs'),
                ],
            ],
        ],
    ],

    // VALORES PADRÃO
    'defaults' => [
        'routes' => [
            'docs'            => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware'      => [
                'api'             => [],
                'asset'           => [],
                'docs'            => [],
                'oauth2_callback' => [],
            ],
            'group_options' => [],
        ],

        'paths' => [
            'docs'   => storage_path('api-docs'),
            'views'  => base_path('resources/views/vendor/l5-swagger'),
            'base'   => env('L5_SWAGGER_BASE_PATH', null),
            'excludes' => [],
        ],

        'scanOptions' => [
            'default_processors_configuration' => [],
            'analyser'  => null,
            'analysis'  => null,
            'processors'=> [],
            'pattern'   => null,

            'exclude'   => [
                base_path('vendor'),
                base_path('app/Support'),
                base_path('app/Services'),
                base_path('app/Repositories'),
                base_path('app/Console'),
                base_path('app/Providers'),
                base_path('bootstrap'),
                base_path('config'),
                base_path('database'),
                base_path('routes'),
                base_path('tests'),
            ],

            'open_api_spec_version' => env('L5_SWAGGER_OPEN_API_SPEC_VERSION', \L5Swagger\Generator::OPEN_API_DEFAULT_SPEC_VERSION),
        ],

        'securityDefinitions' => [
            'securitySchemes' => [
                // ex: 'bearerAuth' => [...]
            ],
            'security' => [
                // ex: ['bearerAuth' => []],
            ],
        ],

        // pra docker é melhor regenerar sempre
        'generate_always'    => env('L5_SWAGGER_GENERATE_ALWAYS', true),
        'generate_yaml_copy' => false,
        'proxy'              => false,
        'additional_config_url' => null,
        'operations_sort'       => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'validator_url'         => null,

        'ui' => [
            'display' => [
                'dark_mode'     => env('L5_SWAGGER_UI_DARK_MODE', false),
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                'filter'        => env('L5_SWAGGER_UI_FILTERS', true),
            ],
            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', true),
                'oauth2' => [
                    'use_pkce_with_authorization_code_grant' => false,
                ],
            ],
        ],

        // deixa vazio pra ele não forçar outra origem
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', ''),
        ],
    ],
];
