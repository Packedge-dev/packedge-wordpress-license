<?php
/**
 * License tests.
 *
 * @package PackEdge\Tests
 */

namespace PackEdge\Tests;

use PackEdge\License;
use PHPUnit\Framework\TestCase;

class LicenseTest extends TestCase
{
    private const PLUGIN_FILE = '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php';

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['wp_current_user_can'] = true;

        $reflection = new \ReflectionClass(License::class);
        $property = $reflection->getProperty('instances');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    public function test_init_returns_singleton(): void
    {
        $a = License::init('pk_test', self::PLUGIN_FILE);
        $b = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertSame($a, $b);
    }

    public function test_different_keys_different_instances(): void
    {
        $a = License::init('pk_one', self::PLUGIN_FILE);
        $b = License::init('pk_two', self::PLUGIN_FILE);

        $this->assertNotSame($a, $b);
    }

    public function test_get_public_key(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertEquals('pk_test', $license->get_public_key());
    }

    public function test_get_plugin_slug(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertEquals('my-plugin', $license->get_plugin_slug());
    }

    public function test_get_plugin_file(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertEquals(self::PLUGIN_FILE, $license->get_plugin_file());
    }

    public function test_is_valid_false_when_empty(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertFalse($license->is_valid());
    }

    public function test_is_valid_false_without_key(): void
    {
        $GLOBALS['wp_options']['license_pk_test'] = json_encode(['status' => 'active']);

        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertFalse($license->is_valid());
    }

    public function test_is_valid_false_without_status(): void
    {
        $GLOBALS['wp_options']['license_pk_test'] = json_encode(['key' => 'lic_123']);

        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertFalse($license->is_valid());
    }

    public function test_is_valid_false_when_not_active(): void
    {
        $GLOBALS['wp_options']['license_pk_test'] = json_encode([
            'key' => 'lic_123',
            'status' => 'expired',
        ]);

        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertFalse($license->is_valid());
    }

    public function test_is_valid_true_when_active(): void
    {
        $GLOBALS['wp_options']['license_pk_test'] = json_encode([
            'key' => 'lic_123',
            'status' => 'active',
        ]);

        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertTrue($license->is_valid());
    }

    public function test_is_valid_false_when_expired(): void
    {
        $GLOBALS['wp_options']['license_pk_test'] = json_encode([
            'key' => 'lic_123',
            'status' => 'active',
            'expires_at' => '2020-01-01T00:00:00Z',
        ]);

        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertFalse($license->is_valid());
    }

    public function test_is_valid_true_when_not_expired(): void
    {
        $GLOBALS['wp_options']['license_pk_test'] = json_encode([
            'key' => 'lic_123',
            'status' => 'active',
            'expires_at' => '2099-01-01T00:00:00Z',
        ]);

        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertTrue($license->is_valid());
    }

    public function test_get_key_null_when_empty(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertNull($license->get_key());
    }

    public function test_get_key(): void
    {
        $GLOBALS['wp_options']['license_pk_test'] = json_encode([
            'key' => 'lic_123',
            'status' => 'active',
        ]);

        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertEquals('lic_123', $license->get_key());
    }

    public function test_registers_rest_api(): void
    {
        License::init('pk_test', self::PLUGIN_FILE);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
    }

    public function test_registers_admin_scripts(): void
    {
        License::init('pk_test', self::PLUGIN_FILE);

        $this->assertArrayHasKey('admin_enqueue_scripts', $GLOBALS['wp_actions']);
    }

    public function test_updater_returns_updater_instance(): void
    {
        $license = License::init('pk_test', self::PLUGIN_FILE);

        $this->assertInstanceOf(\PackEdge\Updater::class, $license->updater());
    }
}
