# Moodle JWT Auth Plugin

Authentication into OLab from Moodle using JWT We are using Moodle as our primary IAMS app because it is commonplace and easy to work with. Ideally, we would like to extend this SSO-authentication method to other LMS apps. We are happy to work with other groups that might like to extend this functionality.

## Install

1. Download the latest release or the source of this repository
2. Extract the archive into into moodle's `local` folder
3. Make sure the plugin folder is called `jwt_auth`. To be sure, `ls local/jwt_auth/config.php` should show the plugin's config file.
4. As a last step, go to moodle's admin screen and follow moodle's suggested database upgrade prompts.

The plugin will then be installed and enabled.

## Configuration

Edit `jwt_auth/config.php` to customize the plugin settings:

```php
// this private key with oLab API's
const OLAB_JWT_PRIVATE_KEY = 'your_jwt_signing_key';
```

The JWT signing secret, for generating tokens to embed external cases. This should match with the token used on OLab API.

```php
// which hosts to whitelist for bearer redirects -- for ports other than 80/443, please use host:port, e.g localhost:8080
const OLAB_AUTH_REDIRECT_ALLOW_HOSTS = ['example.com', 'www.example.com', 'localhost:5903'];
```

This feature is not currently in use by OLab. It allows you to whitelist domains for access token browser redirects, invoked via `/local/jwt_auth/auth.php?callback_url=https://www.example.com/auth_callback`

```php
// cookie params for syncing the JWT with other apps
const OLAB_AUTH_JWT_COOKIE_NAME = 'moodle_bearer';
const OLAB_AUTH_JWT_COOKIE_PARAMS = [
    'domain' => 'olab.ca',
    'path' => '/',
    'secure' => true,
    'httponly' => false,
];
```

The cookie name and [`setcookie`](https://www.php.net/manual/en/function.setcookie.php) parameters, used for syncing the JWT token in a browser cookie.

```php
// one or more hosts for iframes to attach JWT token to via query string
const TOKENIZE_IFRAMES_WITH_HOST = [
    'dev.olab.ca',
    'demo.olab.ca',
    'logan.cardinalcreek.ca',
    'everest.cardinalcreek.ca',
];
```

As we moved OLab to a new root domain, different than OLab's, relying on cookies for syncing bearer tokens was no longer possible and we resorted to passing tokens via a query string parameter. Any iframes with the whitelisted hosts above will have the token appended to their source URL and synchronized. This is also used to clean up any tokens accidentally saved into some common database tables.
