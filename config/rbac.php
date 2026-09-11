<?php

declare(strict_types=1);

return [
    'consumer' => [
        'enabled' => env('RBAC_KAFKA_ENABLED', true),
        // Reserved fixed topic for RBAC snapshots; always consumed by rbac:consume-snapshots.
        'topic' => env('RBAC_KAFKA_TOPIC', env('KAFKA_TOPIC_RBAC_SNAPSHOTS', 'iam.rbac.snapshots.v1')),
        'group_id' => env('RBAC_KAFKA_GROUP_ID', 'rbac-materializer'),
        // Optional: app-specific topics to consume in the same worker.
        'handlers' => [
            // 'auth.events.v1' => \App\Kafka\Handlers\AuthEventsTopicHandler::class,
        ],
        // skip|fail: behavior when a message arrives for a topic without registered handler.
        'on_unhandled_topic' => env('RBAC_KAFKA_ON_UNHANDLED_TOPIC', 'skip'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kafka: Schema Registry + JSON/AVRO por cabecera content_type
    |--------------------------------------------------------------------------
    |
    | Si content_type = application/avro el consumer decodifica el value en Confluent Avro
    | Wire Format (magic 0x00 + schema_id + payload): el esquema se resuelve vía Schema
    | Registry por el id embebido (varios esquemas por topic). En caso contrario se usa JSON.
    | Requiere rbac.kafka.schema_registry.url. El mapa body_schema_by_topic solo aplica al
    | publish AVRO (KafkaEventPublisher) para elegir subject/version por topic.
    |
    */
    'kafka' => [
        'schema_registry' => [
            'url' => env('RBAC_KAFKA_SCHEMA_REGISTRY_URL'),
        ],

        'serialization' => [
            // Si true, en publish JSON se añade content_type: application/json.
            'emit_json_content_type_header' => (bool) env('RBAC_KAFKA_EMIT_JSON_CONTENT_TYPE', false),

            'avro' => [
                /*
                 * topic => ['schema_name' => 'subject-in-registry', 'version' => -1]
                 * Solo necesario para publicar con SerializationFormat::Avro (encode por subject).
                 * Omitir version o usar -1 para última versión (KafkaAvroSchemaRegistry::LATEST_VERSION).
                 *
                 * @var array<string, array{schema_name: string, version?: int}>
                 */
                'body_schema_by_topic' => [
                    // 'iam.rbac.snapshots.v1' => ['schema_name' => 'iam.rbac.snapshots-value', 'version' => -1],
                ],
            ],
        ],
    ],

    'store' => [
        'driver' => 'database',
        'table' => env('RBAC_STORE_TABLE', 'rbac_user_permission_snapshots'),
    ],

    'fallback' => [
        'enabled' => env('RBAC_IAM_FALLBACK_ENABLED', true),
        'base_url' => env('RBAC_IAM_BASE_URL', env('APP_URL', 'http://localhost:8000')),
        'internal_token' => env('RBAC_IAM_INTERNAL_TOKEN', env('RBAC_INTERNAL_TOKEN')),
        'timeout_ms' => (int) env('RBAC_IAM_TIMEOUT_MS', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | IAM user profile enrichment (MMT-AUTH-SERVICE)
    |--------------------------------------------------------------------------
    |
    | Used by rbac.auth.user.info middleware: merges gateway JWT claims with the
    | full user record from GET /api/iam/v1/rbac/admin/users/{uuid} (internal MS auth).
    |
    */
    'iam_user' => [
        'enabled' => env('RBAC_IAM_USER_ENRICH_ENABLED', true),
        'base_url' => env('RBAC_IAM_BASE_URL', env('RBAC_IAM_USER_BASE_URL', env('APP_URL', 'http://localhost:8000'))),
        'path' => env('RBAC_IAM_USER_PATH', '/api/iam/v1/rbac/admin/users'),
        'timeout_ms' => (int) env('RBAC_IAM_USER_TIMEOUT_MS', env('RBAC_IAM_TIMEOUT_MS', 1500)),
        'fail_open' => (bool) env('RBAC_IAM_USER_FAIL_OPEN', true),
        'log_failures' => (bool) env('RBAC_IAM_USER_LOG_FAILURES', false),
    ],

    'auth' => [
        'guard' => env('RBAC_AUTH_GUARD', 'web'),
        'strict_deny' => env('RBAC_STRICT_DENY', true),
        'fail_mode' => env('RBAC_FAIL_MODE', 'deny'),
    ],

    'surface' => [
        'default' => env('RBAC_DEFAULT_SURFACE'),
    ],

    'gateway' => [
        'internal_header' => env('RBAC_GATEWAY_INTERNAL_HEADER', 'X-Internal-Gateway'),
        'internal_secret' => env('RBAC_GATEWAY_INTERNAL_SECRET', 'apisix'),
        'userinfo_header' => env('RBAC_GATEWAY_USERINFO_HEADER', 'X-Userinfo'),
        'log_missing_headers' => env('RBAC_GATEWAY_LOG_MISSING_HEADERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal microservice-to-microservice authentication
    |--------------------------------------------------------------------------
    |
    | Shared secret (X-Internal-Token) plus caller identity (X-Internal-Source).
    | Aligns with MMT-AUTH-SERVICE internal routes; use rbac.trusted.internal first.
    |
    */
    'internal' => [
        'token' => env('RBAC_INTERNAL_TOKEN'),
        'token_header' => env('RBAC_INTERNAL_TOKEN_HEADER', 'X-Internal-Token'),
        'source_header' => env('RBAC_INTERNAL_SOURCE_HEADER', 'X-Internal-Source'),
        'caller_source' => env('RBAC_INTERNAL_CALLER_SOURCE', env('APP_NAME', 'unknown')),
        'log_trusted_requests' => (bool) env('RBAC_INTERNAL_LOG_TRUSTED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy top-level key (MMT-AUTH-SERVICE compatibility)
    |--------------------------------------------------------------------------
    */
    'internal_token' => env('RBAC_INTERNAL_TOKEN'),
];
