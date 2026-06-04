<?php

/**
 * PackEdge License
 *
 * @package PackEdge
 */

namespace PackEdge;

/**
 * License class.
 */
class License
{
    /** @var string Public key. */
    private string $public_key;

    /** @var string Plugin slug. */
    private string $plugin_slug;

    /** @var string Plugin file path. */
    private string $plugin_file;

    /** @var array|null Cached license data. */
    private ?array $license = null;

    /** @var bool License loaded flag. */
    private bool $loaded = false;

    /** @var array<string, self> Instances. */
    private static array $instances = [];

    /**
     * Initialize.
     *
     * @param string $public_key  PackEdge public key.
     * @param string $plugin_file Plugin file (__FILE__).
     * @return self
     */
    public static function init(string $public_key, string $plugin_file): self
    {
        if (! isset(self::$instances[$public_key])) {
            self::$instances[$public_key] = new self($public_key, $plugin_file);
        }
        return self::$instances[$public_key];
    }

    /**
     * Constructor.
     */
    private function __construct(string $public_key, string $plugin_file)
    {
        $this->public_key  = $public_key;
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = basename(dirname($plugin_file));

        // REST route must register on REST requests too (is_admin() is false there).
        add_action('rest_api_init', [$this, 'register_route']);

        // Localized admin data is only needed in wp-admin.
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'localize']);
        }
    }

    /**
     * Check if license is valid.
     *
     * @return bool
     */
    public function is_valid(): bool
    {
        $license = $this->get_license();

        if (empty($license['key']) || ($license['status'] ?? '') !== 'active') {
            return false;
        }

        if (! empty($license['expires_at'])) {
            return strtotime($license['expires_at']) > time();
        }

        return true;
    }

    /**
     * Get license key.
     *
     * @return string|null
     */
    public function get_key(): ?string
    {
        return $this->get_license()['key'] ?? null;
    }

    /**
     * Get updater.
     *
     * @return Updater
     */
    public function updater(): Updater
    {
        return Updater::init($this);
    }

    /**
     * Get public key.
     *
     * @return string
     */
    public function get_public_key(): string
    {
        return $this->public_key;
    }

    /**
     * Get plugin slug.
     *
     * @return string
     */
    public function get_plugin_slug(): string
    {
        return $this->plugin_slug;
    }

    /**
     * Get plugin file.
     *
     * @return string
     */
    public function get_plugin_file(): string
    {
        return $this->plugin_file;
    }

    /**
     * Get license data (lazy load).
     *
     * @return array
     */
    private function get_license(): array
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $stored = get_option('license_' . $this->public_key);
            if (is_string($stored)) {
                $this->license = json_decode($stored, true);
            } elseif (is_array($stored)) {
                $this->license = $stored;
            }
        }
        return $this->license ?? [];
    }

    /**
     * Register REST route.
     *
     * @return void
     */
    public function register_route(): void
    {
        register_rest_route('packedge/v1', '/license/' . $this->public_key, [
            'methods'  => 'POST',
            'callback' => function (\WP_REST_Request $r): \WP_REST_Response {
                $data = $r->get_json_params();
                if (empty($data) || ! is_array($data)) {
                    return new \WP_REST_Response(['success' => false], 400);
                }
                $this->license = $data;
                $this->loaded = true;
                $saved = update_option('license_' . $this->public_key, wp_json_encode($data));

                // License state changed (activated, deactivated, disabled or
                // invalidated) — drop the cached update list so the next Plugins/
                // Updates page load re-checks entitlement instead of showing a
                // stale (now undownloadable) update.
                delete_site_transient('update_plugins');

                return new \WP_REST_Response(['success' => $saved]);
            },
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * Localize script data.
     *
     * @return void
     */
    public function localize(): void
    {
        $key = str_replace(['-', '.'], '_', $this->public_key);
        wp_localize_script('jquery', 'PackEdgeLicense_' . $key, [
            'endpoint'    => rest_url('packedge/v1/license/' . $this->public_key),
            'nonce'       => wp_create_nonce('wp_rest'),
            'public_key'  => $this->public_key,
            'plugin_slug' => $this->plugin_slug,
            'site_url'    => site_url(),
            'license'     => $this->get_license(),
        ]);
    }
}
