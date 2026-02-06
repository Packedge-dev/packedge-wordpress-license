<?php
/**
 * PHPUnit bootstrap.
 *
 * @package PackEdge\Tests
 */

$GLOBALS['wp_options'] = [];
$GLOBALS['wp_actions'] = [];
$GLOBALS['wp_filters'] = [];
$GLOBALS['is_admin'] = true;

function is_admin(): bool
{
    return $GLOBALS['is_admin'] ?? true;
}

function get_option(string $option, $default = false)
{
    return $GLOBALS['wp_options'][$option] ?? $default;
}

function update_option(string $option, $value): bool
{
    $GLOBALS['wp_options'][$option] = $value;
    return true;
}

function site_url(string $path = ''): string
{
    return 'https://example.com' . $path;
}

function rest_url(string $path = ''): string
{
    return 'https://example.com/wp-json/' . ltrim($path, '/');
}

function wp_create_nonce(string $action): string
{
    return 'nonce_' . $action;
}

function wp_json_encode($data)
{
    return json_encode($data);
}

function current_user_can(string $capability): bool
{
    return $GLOBALS['wp_current_user_can'] ?? true;
}

function add_action(string $tag, callable $callback, int $priority = 10, int $args = 1): void
{
    $GLOBALS['wp_actions'][$tag][] = $callback;
}

function add_filter(string $tag, callable $callback, int $priority = 10, int $args = 1): void
{
    $GLOBALS['wp_filters'][$tag][] = $callback;
}

function register_rest_route(string $namespace, string $route, array $args): void
{
    $GLOBALS['wp_rest_routes'][$namespace][$route] = $args;
}

function wp_localize_script(string $handle, string $name, array $data): void
{
    $GLOBALS['wp_localized'][$handle][$name] = $data;
}

function plugin_basename(string $file): string
{
    preg_match('/wp-content\/plugins\/(.+)$/', $file, $matches);
    return $matches[1] ?? basename(dirname($file)) . '/' . basename($file);
}

function trailingslashit(string $string): string
{
    return rtrim($string, '/\\') . '/';
}

function wp_remote_get(string $url, array $args = [])
{
    return $GLOBALS['wp_remote_response'] ?? ['body' => '{}'];
}

function wp_remote_retrieve_body(array $response): string
{
    return $response['body'] ?? '';
}

function is_wp_error($thing): bool
{
    return false;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
