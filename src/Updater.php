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
        $key = version_compare($update['version'], $current, '>') ? 'response' : 'no_update';

        $transient->{$key}[$this->basename] = (object) [
            'slug'        => $this->slug,
            'plugin'      => $this->basename,
            'new_version' => $update['version'],
            'package'     => $update['download_url'] ?? '',
            'url'         => $update['url'] ?? '',
        ];

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

        return (object) [
            'name'          => $info['name'] ?? $this->slug,
            'slug'          => $this->slug,
            'version'       => $info['version'],
            'download_link' => $info['download_url'] ?? '',
            'sections'      => ['changelog' => $info['changelog'] ?? ''],
        ];
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
     * Fetch update info (cached).
     *
     * @return array|null
     */
    private function fetch(): ?array
    {
        if ($this->update_cache !== null) {
            return $this->update_cache ?: null;
        }

        $key = $this->license->get_key();
        if (! $key) {
            $this->update_cache = [];
            return null;
        }

        $response = wp_remote_get(
            'https://api.packedge.dev/public/v1/update/' . $this->slug . '?' . http_build_query([
                'license_key' => $key,
                'site'        => site_url(),
            ]),
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
