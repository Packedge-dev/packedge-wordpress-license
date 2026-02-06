# PackEdge License

License + auto-update for WordPress plugins.

## Installation

### Via Composer (Recommended)

```bash
composer require packedge/license
```

```php
require_once __DIR__ . '/vendor/autoload.php';

use PackEdge\License;

$license = License::init('pk_xxx', __FILE__);
```

### Manual Installation

Download and copy `src/License.php` and `src/Updater.php` to your plugin:

```php
require_once __DIR__ . '/includes/License.php';
require_once __DIR__ . '/includes/Updater.php';

use PackEdge\License;

$license = License::init('pk_xxx', __FILE__);
```

## Quick Start

```php
<?php
/**
 * Plugin Name: My Premium Plugin
 * Version: 1.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';

use PackEdge\License;

$license = License::init('pk_your_public_key', __FILE__);
$license->updater();

if ($license->is_valid()) {
    // Premium features
}
```

## License Class

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `init($public_key, $plugin_file)` | License | Initialize (singleton) |
| `is_valid()` | bool | Check if license is valid |
| `get_key()` | ?string | Get license key |
| `updater()` | Updater | Enable auto-updates |

### License Statuses

- `active` - License is valid
- `expired` - License has expired
- `suspended` - License is suspended
- `revoked` - License is revoked

## Updater Class

```php
$license->updater();
```

Hooks into WordPress update system and checks PackEdge API for new versions.

## How It Works

### License Storage

License stored in WordPress options as `license_{public_key}`.

### REST API

Endpoint at `/wp-json/packedge/v1/license/{public_key}` accepts license data from JS SDK.

### Localized Data

Available in admin as `PackEdgeLicense_pk_xxx`:

```javascript
{
    endpoint: '/wp-json/packedge/v1/license/pk_xxx',
    nonce: 'xxx',
    public_key: 'pk_xxx',
    plugin_slug: 'my-plugin',
    site_url: 'https://example.com',
    license: { key: 'lic_xxx', status: 'active', ... }
}
```

## Full Example

```php
<?php
/**
 * Plugin Name: My Premium Plugin
 * Version: 1.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';

use PackEdge\License;

class MyPlugin
{
    private License $license;

    public function __construct()
    {
        $this->license = License::init('pk_xxx', __FILE__);
        $this->license->updater();

        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void
    {
        add_menu_page('My Plugin', 'My Plugin', 'manage_options', 'my-plugin', [$this, 'render']);
    }

    public function render(): void
    {
        if ($this->license->is_valid()) {
            echo '<p>License: ' . esc_html($this->license->get_key()) . '</p>';
        } else {
            echo '<p>Please activate license</p>';
        }
    }
}

new MyPlugin();
```

## Documentation

[docs.packedge.dev](https://docs.packedge.dev)

## License

MIT
