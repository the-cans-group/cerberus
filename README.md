# Cerberus 🛡️
**A lightweight, extensible multi-device session authentication system for Laravel.**

---

## 🚀 Installation

```bash
composer require the-cans-group/cerberus
```

### 1. Register the Service Provider (Laravel 5.x)

```php
// config/app.php
'providers' => [
    Cerberus\CerberusProvider::class,
],
```

> For Laravel 5.5+ this is auto-discovered.

---

## ⚙️ Configuration

### Publish the config file:

```bash
php artisan vendor:publish --tag=cerberus-config
```

### Publish the migration:

```bash
php artisan migrate
```

---

## 🔐 Guard Setup

Add to `config/auth.php`:

```php
'guards' => [
    'cerberus' => [
        'driver' => 'cerberus',
        'provider' => null, // Automatically resolved
    ],
],
```

---

## 🧬 Model Integration

Use the `CerberusAuthenticatable` trait in your authenticatable model:

```php
use Cerberus\CerberusAuthenticatable;

class User extends Authenticatable
{
    use CerberusAuthenticatable;
}
```

---

## 📲 Usage

### Create Access Token

```php
$token = $user->createAccessToken();
```

### Check if Token Belongs to the User

```php
$user->tokenBelongsToThisUser($token); // true / false
```

### List Active Sessions

```php
$user->sessions();
```

### Revoke Tokens

```php
$user->revokeToken($token);         // Revoke specific token
$user->revokeOtherTokens($token);   // Revoke all except this one
$user->revokeAllTokens();           // Revoke all
```

---

## 🧪 Middleware Usage

```php
Route::middleware('auth:cerberus')->group(function () {
    Route::get('/me', fn () => auth()->user());
});
```

---

## ⚙️ Commands

### Clean up expired sessions:

```bash
php artisan cerberus:prune
```

---

## 🧠 Database Structure

- `cerberus_user_devices`: Stores device data
- `cerberus_user_device_sessions`: Stores session data
- Supports polymorphic `authenticatable_type/id` for multiple model support

---

## 🛠️ Configuration Options (`config/cerberus.php`)

Example:

```php
'token' => [
    'rounds' => 128,
    'prefix' => 'cerberus',
    'encoding' => 'base64url',
    'hash_driver' => 'argon2id',
    'hash_enabled' => true,
],
```

---

## 📄 License

MIT © The Can's Group
