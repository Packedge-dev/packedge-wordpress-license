<?php

/**
 * PackEdge Updater
 *
 * @package PackEdge
 */

namespace PackEdge;

/**
 * Updater class.
 */
class Updater
{
    /** @var License */
    private License $license;

    /** @var string */
    private string $basename;

    /** @var string */
    private string $slug;

    /** @var array|null Cached update info. */
    private ?array $update_cache = null;

    /** @var array<string, self> */
    private static array $instances = [];

    /**
     * Initialize.
     *
     * @param License $license License instance.
     * @return self
     */
    public static function init(License $license): self
    {
        $key = $license->get_public_key();

        if (! isset(self::$instances[$key])) {
            self::$instances[$key] = new self($license);
        }

        return self::$instances[$key];
    }

    /**
     * Constructor.
     */
    private function __construct(License $license)
    {
        $this->license  = $license;
        $this->slug     = $license->get_plugin_slug();
        $this->basename = plugin_basename($license->get_plugin_file());

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_folder'], 10, 4);
    }

    /**
     * Check for updates.
     *
     * @param object $transient Transient.
     * @return object
     */
    public function check_update(object $transient): object
    {
        if (empty($transient->checked[$this->basename])) {
            return $transient;
        }

        $update = $this->fetch();
        if (! $update) {
            return $transient;
        }

        $current = $transient->checked[$this->basename];

        // Always surface a newer version as an available update so even
        // unlicensed/invalid/deactivated sites see it (a nudge to activate). The
        // downloadable package is only included by the API for a valid, licensed,
        // active site; without it WP shows the update but the download is refused.
        $is_newer = version_compare($update['version'], $current, '>');
        $key      = $is_newer ? 'response' : 'no_update';

        $entry = [
            'slug'        => $this->slug,
            'plugin'      => $this->basename,
            'new_version' => $is_newer ? $update['version'] : $current,
            'package'     => $update['package'] ?? '',
            'url'         => $update['url'] ?? '',
        ];

        // Icons/banners power the plugin logo on the Updates screen.
        if (! empty($update['icons'])) {
            $entry['icons'] = $update['icons'];
        }
        if (! empty($update['banners'])) {
            $entry['banners'] = $update['banners'];
        }

        $transient->{$key}[$this->basename] = (object) $entry;

        return $transient;
    }

    /**
     * Plugin info.
     *
     * @param mixed  $result Result.
     * @param string $action Action.
     * @param object $args   Args.
     * @return mixed
     */
    public function plugin_info($result, string $action, object $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->slug) {
            return $result;
        }

        $info = $this->fetch();
        if (! $info) {
            return $result;
        }

        $result = [
            'name'          => $info['name'] ?? $this->slug,
            'slug'          => $this->slug,
            'version'       => $info['version'],
            'download_link' => $info['package'] ?? '',
            'sections'      => $info['sections'] ?? ['changelog' => $info['changelog'] ?? ''],
            'requires'      => $info['requires'] ?? '',
            'tested'        => $info['tested'] ?? '',
            'requires_php'  => $info['requires_php'] ?? '',
        ];

        // Banner header + icon shown in the "View details" modal.
        if (! empty($info['banners'])) {
            $result['banners'] = $info['banners'];
        }
        if (! empty($info['icons'])) {
            $result['icons'] = $info['icons'];
        }

        return (object) $result;
    }

    /**
     * Fix folder name after extraction.
     *
     * @param string $source Source.
     * @param string $remote Remote.
     * @param mixed  $upgrader Upgrader.
     * @param array  $extras Extras.
     * @return string
     */
    public function fix_folder(string $source, string $remote, $upgrader, array $extras): string
    {
        if (($extras['plugin'] ?? '') !== $this->basename) {
            return $source;
        }

        global $wp_filesystem;
        $dest = trailingslashit($remote) . $this->slug;

        if ($source !== $dest && $wp_filesystem->move($source, $dest)) {
            return $dest;
        }

        return $source;
    }

    /**
     * Resolve the PackEdge API base URL. Defaults to production; override for
     * staging/local via the PACKEDGE_API_BASE constant or the
     * `packedge_api_base` filter.
     *
     * @return string
     */
    private function api_base(): string
    {
        $base = \defined('PACKEDGE_API_BASE') ? (string) \constant('PACKEDGE_API_BASE') : 'https://api.packedge.dev';

        return \rtrim(\apply_filters('packedge_api_base', $base), '/');
    }

    /**
     * Fetch update info (cached).
     *
     * @return array|null
     */
    private function fetch(): ?array
    {
        if ($this->update_cache !== null) {
            return $this->update_cache ?: null;
        }

        // No license key is fine: the update-check still returns the new version
        // (so WP shows "update available"); the API just omits the package, so
        // the download is gated to valid, licensed, active sites.
        $params = [
            'slug' => $this->slug,
            'site' => site_url(),
        ];
        $key = $this->license->get_key();
        if ($key) {
            $params['license_key'] = $key;
        }

        $response = wp_remote_get(
            $this->api_base() . '/public/v1/wp/update-check?' . http_build_query($params),
            ['timeout' => 10, 'headers' => ['X-Public-Key' => $this->license->get_public_key()]]
        );

        if (is_wp_error($response)) {
            $this->update_cache = [];
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $this->update_cache = is_array($data) && ! empty($data['version']) ? $data : [];

        return $this->update_cache ?: null;
    }
}
