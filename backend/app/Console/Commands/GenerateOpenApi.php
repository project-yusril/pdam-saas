<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * pdam:openapi — spek OpenAPI 3.0 yang JANGAN bohong: dihasilkan dari route
 * registry riil (route:list), bukan anotasi parsial yang tertinggal.
 *
 * Setiap operation memakai skema respons generik ApiResponse ({success,data,
 * meta/error}); skema per-endpoint yang detail menunggu anotasi/JSON schema
 * yang diverifikasi (todo H-10). Keluaran ditulis ke storage/api-docs/
 * api-docs.generated.json sehingga Swagger UI/l5-swagger tetap bisa menunjuknya.
 *
 *   php artisan pdam:openapi            # tulis api-docs.generated.json
 *   php artisan pdam:openapi --json     # cetak ke stdout
 */
class GenerateOpenApi extends Command
{
    protected $signature = 'pdam:openapi
                            {--json : cetak spek ke stdout}
                            {--out= : path tujuan (default storage/api-docs + docs/openapi.json)}';

    protected $description = 'Generate OpenAPI 3.0 dari route registry (368 endpoint API)';

    private const AUTH_PATHS = [
        '/platform/login', '/login', '/provinces', '/up', '/sanctum/csrf-cookie',
    ];

    public function handle(): int
    {
        $spec = $this->build();
        if ($this->option('json')) {
            $this->line(json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($this->targets() as $out) {
            $dir = dirname($out);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($out, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->line('Ditulis: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $out));
        }
        $this->line(sprintf('  paths  : %d', count($spec['paths'])));
        $this->line(sprintf('  methods: %d', array_sum(array_map('count', $spec['paths']))));

        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function targets(): array
    {
        if ($opt = $this->option('out')) {
            return [(string) $opt];
        }

        return [
            storage_path('api-docs/api-docs.generated.json'),
            dirname(base_path()).'/docs/openapi.json',
        ];
    }

    private function build(): array
    {
        $routes = Route::getRoutes();
        $paths = [];
        $operationIds = [];
        $n = 0;

        foreach ($routes as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }
            // Path OpenAPI: uri 'api/v1/login' → '/login' (server path = /api/v1)
            $afterApi = (string) substr($uri, strlen('api/'));
            $afterApi = preg_replace('#^v\d+/?#', '', $afterApi);
            $openapiPath = '/'.preg_replace('#\{([^}?]+)\?\}#', '{$1}', $afterApi);
            $openapiPath = preg_replace_callback('#\{([^}]+)\}#', fn ($m) => '{'.ltrim($m[1], ':').'}', $openapiPath);

            $methods = array_values(array_filter($route->methods(), fn ($m) => in_array($m, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], true)));
            if ($methods === [] || ($methods === ['HEAD'] || $methods === ['OPTIONS', 'HEAD'])) {
                continue;
            }

            foreach ($methods as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $n++;
                $action = $this->actionName($route);
                $segments = explode('/', $openapiPath);
                $tag = isset($segments[2]) && $segments[2] !== '' ? ucfirst($segments[2]) : 'Root';
                $tag = preg_replace('/[^A-Za-z0-9+]+/', ' ', $tag);
                $tag = trim(ucwords($tag)) ?: 'Umum';

                $base = preg_replace('/[^A-Za-z0-9]+/', ' ', $action);
                $base = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $base));
                $operationId = strtolower(str_replace(' ', '_', $base)).'_'.substr(md5($method.' '.$openapiPath), 0, 6);
                while (isset($operationIds[$operationId])) {
                    $operationId .= '_';
                }
                $operationIds[$operationId] = true;

                $op = [
                    'tags' => [$tag],
                    'summary' => ucfirst(str_replace('_', ' ', $action)),
                    'operationId' => $operationId,
                    'security' => in_array($openapiPath, self::AUTH_PATHS, true) || in_array('/'.$openapiPath, self::AUTH_PATHS, true) ? [] : [['bearerAuth' => []]],
                    'responses' => $this->responsesFor($method),
                ];

                preg_match_all('#\{([^}]+)\}#', $openapiPath, $mm);
                if (! empty($mm[1])) {
                    $op['parameters'] = array_map(
                        fn ($name) => ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        $mm[1],
                    );
                }

                $paths[$openapiPath][strtolower($method)] = $op;
            }
        }

        ksort($paths);

        $tags = [];
        foreach ($paths as $operations) {
            foreach ($operations as $op) {
                foreach ($op['tags'] as $t) {
                    $tags[$t] = true;
                }
            }
        }
        $tags = array_map(fn ($t) => ['name' => $t], array_keys($tags));
        sort($tags);

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'PDAM SaaS API',
                'version' => '1.0.0',
                'description' => 'Spek **di-generate** dari route registry Laravel (`php artisan pdam:openapi`). '
                    .'Skema operation generik `ApiResponse`; kontrak JSON per-endpoint menyusul (task H-10). '
                    .'Response standar: `{success:bool, data, meta}`; error: `{success:false, error:{code,message,details}}`. '
                    .'Web memakai session cookie (+CSRF); mobile memakai bearer Sanctum dengan `device_name` saat login.',
            ],
            'servers' => [['url' => '/api/v1', 'description' => 'API v1']],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'Sanctum'],
                ],
                'schemas' => [
                    'ApiResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean'],
                            'data' => ['nullable' => true],
                            'meta' => ['nullable' => true, 'type' => 'object', 'additionalProperties' => true],
                            'message' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'PaginatedResponse' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/ApiResponse'],
                            ['type' => 'object', 'properties' => [
                                'data' => ['type' => 'array', 'items' => new \stdClass],
                                'meta' => ['type' => 'object', 'properties' => [
                                    'current_page' => ['type' => 'integer'],
                                    'from' => ['type' => 'integer'],
                                    'to' => ['type' => 'integer'],
                                    'per_page' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                ]],
                            ]],
                        ],
                    ],
                    'ApiError' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => ['type' => 'object', 'properties' => [
                                'code' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'details' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ]],
                        ],
                        'required' => ['error'],
                    ],
                    'ValidationError' => [
                        'allOf' => [['$ref' => '#/components/schemas/ApiError']],
                        'description' => 'Error 422; format sama dgn ApiError',
                    ],
                ],
            ],
            'tags' => $tags,
        ];
    }

    private function actionName($route): string
    {
        $action = $route->getAction('uses');
        if (is_string($action)) {
            return basename(str_replace('\\', '/', $action));
        }
        if (is_array($action)) {
            return class_basename($action[0]).'@'.($action[1] ?? 'handle');
        }
        if ($action instanceof \Closure) {
            return 'closure@'.substr($route->uri(), 0, 40);
        }

        return 'invokable';
    }

    private function responsesFor(string $method): array
    {
        $ref = [
            'schema' => ['$ref' => '#/components/schemas/ApiResponse'],
        ];

        return [
            '200' => ['description' => 'Sukses', 'content' => ['application/json' => $ref]],
            '401' => ['description' => 'Unauthenticated', 'content' => ['application/json' => ['$ref' => '#/components/schemas/ApiError']]],
            '403' => ['description' => 'Forbidden / modul tidak aktif', 'content' => ['application/json' => ['$ref' => '#/components/schemas/ApiError']]],
            '422' => ['description' => 'Validasi gagal', 'content' => ['application/json' => ['$ref' => '#/components/schemas/ValidationError']]],
        ];
    }
}
