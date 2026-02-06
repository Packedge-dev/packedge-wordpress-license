<?php
/**
 * Updater tests.
 *
 * @package PackEdge\Tests
 */

namespace PackEdge\Tests;

use PackEdge\License;
use PackEdge\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
    private const PLUGIN_FILE = '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php';

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['is_admin'] = true;

        $this->resetSingleton(License::class);
        $this->resetSingleton(Updater::class);
    }

    private function resetSingleton(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $property = $reflection->getProperty('instances');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    public function test_init_returns_singleton(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);

        $a = Updater::init($license);
        $b = Updater::init($license);

        $this->assertSame($a, $b);
    }

    public function test_registers_update_filter(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);
        Updater::init($license);

        $this->assertArrayHasKey('pre_set_site_transient_update_plugins', $GLOBALS['wp_filters']);
    }

    public function test_registers_plugins_api_filter(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);
        Updater::init($license);

        $this->assertArrayHasKey('plugins_api', $GLOBALS['wp_filters']);
    }

    public function test_registers_source_selection_filter(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);
        Updater::init($license);

        $this->assertArrayHasKey('upgrader_source_selection', $GLOBALS['wp_filters']);
    }

    public function test_check_update_returns_transient_when_not_checked(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);
        $updater = Updater::init($license);

        $transient = (object) [];
        $result = $updater->check_update($transient);

        $this->assertSame($transient, $result);
    }

    public function test_plugin_info_returns_result_for_wrong_action(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);
        $updater = Updater::init($license);

        $result = $updater->plugin_info(false, 'other_action', (object) []);

        $this->assertFalse($result);
    }

    public function test_plugin_info_returns_result_for_wrong_slug(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);
        $updater = Updater::init($license);

        $result = $updater->plugin_info(false, 'plugin_information', (object) ['slug' => 'other']);

        $this->assertFalse($result);
    }

    public function test_fix_folder_returns_source_for_wrong_plugin(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);
        $updater = Updater::init($license);

        $result = $updater->fix_folder('/tmp/source', '/tmp', null, ['plugin' => 'other/other.php']);

        $this->assertEquals('/tmp/source', $result);
    }
}
