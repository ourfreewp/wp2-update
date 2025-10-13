<?php

namespace Tests\Helpers;

use Tests\Helpers\WordPressStubs;

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'test-auth-key');
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback): void
    {
        WordPressStubs::$actions[$hook][] = $callback;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback): void
    {
        WordPressStubs::$filters[$hook][] = $callback;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        WordPressStubs::$actionCalls[$hook][] = $args;
        if (!empty(WordPressStubs::$actions[$hook])) {
            foreach (WordPressStubs::$actions[$hook] as $callback) {
                $callback(...$args);
            }
        }
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value)
    {
        WordPressStubs::$filters[$hook][] = $value;
        return $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        if (!is_scalar($value)) {
            return '';
        }

        return filter_var((string) $value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($value)
    {
        $value = strtolower(preg_replace('/[^a-zA-Z0-9-_]+/', '-', (string) $value));
        return trim($value, '-');
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value)
    {
        return is_string($value) ? $value : '';
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return (int) abs($value);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql')
    {
        if ('mysql' === $type) {
            return '2024-01-01 00:00:00';
        }

        return time();
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        $counter = WordPressStubs::$uuidCounter++;
        $uuid    = sprintf('00000000-0000-4000-8000-%012d', $counter);
        WordPressStubs::$generatedUuids[] = $uuid;
        return $uuid;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $name, $value, bool $autoload = false): void
    {
        WordPressStubs::$options[$name] = $value;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $name, $default = false)
    {
        return WordPressStubs::$options[$name] ?? $default;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $name): void
    {
        unset(WordPressStubs::$options[$name]);
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expiration = 0): bool
    {
        WordPressStubs::$transients[$key] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $key)
    {
        return WordPressStubs::$transients[$key] ?? false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset(WordPressStubs::$transients[$key]);
        return true;
    }
}

if (!function_exists('set_site_transient')) {
    function set_site_transient(string $key, $value, int $expiration = 0): bool
    {
        WordPressStubs::$siteTransients[$key] = $value;
        return true;
    }
}

if (!function_exists('get_site_transient')) {
    function get_site_transient(string $key)
    {
        return WordPressStubs::$siteTransients[$key] ?? false;
    }
}

if (!function_exists('delete_site_transient')) {
    function delete_site_transient(string $key): bool
    {
        unset(WordPressStubs::$siteTransients[$key]);
        return true;
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        private $data;
        private int $status;
        private array $headers;

        public function __construct($data = null, int $status = 200, array $headers = [])
        {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function get_headers(): array
        {
            return $this->headers;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        protected array $headers = [];
        protected string $body = '';
        protected array $params = [];

        public function set_header(string $name, string $value): void
        {
            $this->headers[$name] = $value;
        }

        public function set_body(string $body): void
        {
            $this->body = $body;
        }

        public function get_header(string $name): ?string
        {
            return $this->headers[$name] ?? null;
        }

        public function get_body(): string
        {
            return $this->body;
        }

        public function set_param(string $name, $value): void
        {
            $this->params[$name] = $value;
        }

        public function get_param(string $name)
        {
            return $this->params[$name] ?? null;
        }
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value, int $options = 0, int $depth = 512)
    {
        return json_encode($value, $options, $depth);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string
    {
        return hash('crc32b', $action . AUTH_KEY);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action): bool
    {
        return $nonce === wp_create_nonce($action);
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $name, array $data): bool
    {
        WordPressStubs::$localizedScripts[$handle][$name] = $data;
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], $ver = false, bool $in_footer = false): void
    {
        WordPressStubs::$enqueuedScripts[$handle] = compact('src', 'deps', 'ver', 'in_footer');
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void
    {
        WordPressStubs::$enqueuedStyles[$handle] = compact('src', 'deps', 'ver', 'media');
    }
}

if (!function_exists('wp_update_plugins')) {
    function wp_update_plugins(): void
    {
        WordPressStubs::$pluginUpdateCalls++;
    }
}

if (!function_exists('wp_update_themes')) {
    function wp_update_themes(): void
    {
        WordPressStubs::$themeUpdateCalls++;
    }
}

if (!function_exists('wp_get_themes')) {
    function wp_get_themes(): array
    {
        return WordPressStubs::$themes;
    }
}

if (!function_exists('get_plugins')) {
    function get_plugins(): array
    {
        return WordPressStubs::$plugins;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://test.local' . $path;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://test.local/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://test.local/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = ''): void
    {
        throw new \RuntimeException($message ?: 'wp_die called'); // Use global RuntimeException
    }
}

if (!function_exists('wp_tempnam')) {
    function wp_tempnam(string $filename = '', string $dir = ''): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'wp2');
        if (false === $temp) {
            throw new \RuntimeException('Failed to create temp file.'); // Use global RuntimeException
        }

        return $temp;
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $path): bool
    {
        return is_dir($path) || mkdir($path, 0777, true);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw')
    {
        return 'Test Site';
    }
}

if (!function_exists('delete_site_option')) {
    function delete_site_option(string $name): void
    {
        unset(WordPressStubs::$options[$name]);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return 1;
    }
}
