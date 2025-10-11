# Guía de Uso

Aprende a usar Yii2 Fastly Cache de manera efectiva en tus aplicaciones.

## ⚠️ Advertencia de Seguridad

**NUNCA almacenes en Fastly KV Store:**
- Información personal identificable (PII)
- Contraseñas o tokens de autenticación
- Claves API o secretos
- Datos sensibles o confidenciales
- Información regulada (GDPR, HIPAA, etc.)

**Fastly no mantiene historial de versiones** de los datos almacenados. Para más información sobre privacidad, consulta [Fastly's Compliance and Law FAQ](https://www.fastly.com/legal/compliance-law-faq).

## Operaciones Básicas

### Guardar Datos

```php
// ✅ Guardar datos públicos con duración por defecto
$publicUserData = ['id' => 123, 'username' => 'john', 'display_name' => 'John'];
Yii::$app->cache->set('user_public_123', $publicUserData);

// ✅ Guardar con duración específica (1 hora)
Yii::$app->cache->set('user_public_123', $publicUserData, 3600);

// ✅ Guardar múltiples valores seguros
$safeData = [
    'user_public_123' => $publicUserData,
    'config_app' => $configData,
    'menu_items' => $menuData,
];

foreach ($safeData as $key => $value) {
    Yii::$app->cache->set($key, $value, 3600);
}
```

### Obtener Datos

```php
// Obtener datos públicos de usuario
$publicUserData = Yii::$app->cache->get('user_public_123');

// Obtener con valor por defecto
$publicUserData = Yii::$app->cache->get('user_public_123', []);

// Obtener múltiples valores seguros
$keys = ['user_public_123', 'config_app', 'menu_items'];
$values = Yii::$app->cache->multiGet($keys);
```

### Verificar Existencia

```php
if (Yii::$app->cache->exists('user_public_123')) {
    echo "Los datos públicos del usuario están en caché";
}
```

### Eliminar Datos

```php
// Eliminar datos públicos de usuario
Yii::$app->cache->delete('user_public_123');

// Eliminar múltiples claves de datos públicos
$keys = ['user_public_123', 'user_public_456', 'user_public_789'];
Yii::$app->cache->multiDelete($keys);
```

### Agregar Solo si No Existe

```php
// Solo agrega datos públicos si la clave no existe
$publicUserData = ['id' => 123, 'username' => 'john', 'display_name' => 'John'];
$success = Yii::$app->cache->add('user_public_123', $publicUserData, 3600);

if ($success) {
    echo "Datos públicos agregados exitosamente";
} else {
    echo "La clave ya existe";
}
```

## Patrones de Uso Comunes

### 1. Cache-Aside Pattern (SEGURO)

```php
public function getUserPublicData($id)
{
    $cacheKey = "user_public_{$id}";
    
    // Intentar obtener de caché
    $userData = Yii::$app->cache->get($cacheKey);
    
    if ($userData === false) {
        // No está en caché, obtener de BD
        $user = User::findOne($id);
        
        if ($user !== null) {
            // ✅ SOLO cachear datos públicos
            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
                'created_at' => $user->created_at,
            ];
            // ❌ NO cachear: email, password_hash, auth_key, etc.
            
            Yii::$app->cache->set($cacheKey, $userData, 3600);
        }
    }
    
    return $userData;
}

// ❌ PELIGROSO - NO hacer esto
public function getUserUnsafe($id)
{
    $cacheKey = "user_{$id}";
    $user = Yii::$app->cache->get($cacheKey);
    
    if ($user === false) {
        $user = User::findOne($id); // Contiene datos sensibles
        Yii::$app->cache->set($cacheKey, $user, 3600); // ❌ NUNCA
    }
    
    return $user;
}
```

### 2. Write-Through Pattern (SEGURO)

```php
public function updateUserPublicData($id, $data)
{
    $user = User::findOne($id);
    $user->setAttributes($data);
    
    if ($user->save()) {
        // ✅ Actualizar solo datos públicos en caché
        $cacheKey = "user_public_{$id}";
        $publicData = [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_url,
            'updated_at' => $user->updated_at,
        ];
        
        Yii::$app->cache->set($cacheKey, $publicData, 3600);
        
        return true;
    }
    
    return false;
}

// ❌ PELIGROSO - NO hacer esto
public function updateUserUnsafe($id, $data)
{
    $user = User::findOne($id);
    $user->setAttributes($data);
    
    if ($user->save()) {
        $cacheKey = "user_{$id}";
        Yii::$app->cache->set($cacheKey, $user, 3600); // ❌ Cacheando datos sensibles
        return true;
    }
    
    return false;
}
```

### 3. Cache Invalidation (SEGURO)

```php
public function deleteUser($id)
{
    $user = User::findOne($id);
    
    if ($user->delete()) {
        // ✅ Invalidar solo cachés de datos públicos
        $keys = [
            "user_public_{$id}",
            "user_stats_{$id}",
            "user_activity_{$id}",
        ];
        
        Yii::$app->cache->multiDelete($keys);
        
        return true;
    }
    
    return false;
}
```

## Uso con Dependencias

### File Dependency

```php
use yii\caching\FileDependency;

$dependency = new FileDependency([
    'fileName' => Yii::getAlias('@app/config/params.php')
]);

Yii::$app->cache->set('app_config', $configData, 0, $dependency);
```

### Tag Dependency

```php
use yii\caching\TagDependency;

// ✅ Guardar datos públicos con tags
$publicUserData = [
    'id' => 123,
    'username' => 'john_doe',
    'display_name' => 'John Doe',
    'avatar_url' => '/avatars/john.jpg'
];

Yii::$app->cache->set(
    'user_public_123', 
    $publicUserData, 
    3600, 
    new TagDependency(['tags' => ['users', 'user_public_123']])
);

// Invalidar por tag
TagDependency::invalidate(Yii::$app->cache, 'users');
```

### Expression Dependency

```php
use yii\caching\ExpressionDependency;

$dependency = new ExpressionDependency([
    'expression' => 'date("H") < 12' // Válido solo en la mañana
]);

Yii::$app->cache->set('morning_data', $data, 3600, $dependency);
```

## Casos de Uso Específicos

### 1. Cache de Consultas de Base de Datos

```php
class ProductService
{
    public function getFeaturedProducts()
    {
        $cacheKey = 'featured_products';
        
        $products = Yii::$app->cache->get($cacheKey);
        
        if ($products === false) {
            $products = Product::find()
                ->select(['id', 'name', 'price', 'featured']) // Solo campos públicos
                ->where(['featured' => 1])
                ->orderBy('created_at DESC')
                ->limit(10)
                ->all();
            
            // Cache por 30 minutos
            Yii::$app->cache->set($cacheKey, $products, 1800);
        }
        
        return $products;
    }
}
```

### 2. Cache de Respuestas de API

```php
class WeatherService
{
    public function getCurrentWeather($city)
    {
        $cacheKey = "weather_{$city}";
        
        $weather = Yii::$app->cache->get($cacheKey);
        
        if ($weather === false) {
            // Llamada a API externa
            $weather = $this->fetchWeatherFromAPI($city);
            
            // Cache por 15 minutos
            Yii::$app->cache->set($cacheKey, $weather, 900);
        }
        
        return $weather;
    }
}
```

### 3. Cache de Configuración

```php
class ConfigService
{
    public function getAppSettings()
    {
        $cacheKey = 'app_settings';
        
        $settings = Yii::$app->cache->get($cacheKey);
        
        if ($settings === false) {
            $settings = Setting::find()
                ->indexBy('key')
                ->column('value');
            
            // Cache por 1 hora
            Yii::$app->cache->set($cacheKey, $settings, 3600);
        }
        
        return $settings;
    }
}
```

### 4. Cache de Datos Públicos de Usuario

```php
class UserPublicDataService
{
    public function getUserPublicData($userId)
    {
        $cacheKey = "user_public_{$userId}";
        
        $data = Yii::$app->cache->get($cacheKey);
        
        if ($data === false) {
            // Solo datos públicos, NO información personal
            $data = [
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
                'public_profile' => $user->public_profile,
                'last_activity' => $user->last_activity,
            ];
            
            Yii::$app->cache->set($cacheKey, $data, 1800);
        }
        
        return $data;
    }
}
```

## Consideraciones de Seguridad

⚠️ **Para información detallada sobre seguridad, consulta la [Guía de Seguridad](security.md)**

### Datos Seguros vs Peligrosos

```php
// ✅ SEGURO - Datos públicos
$safeData = [
    'product_name' => 'iPhone 15',
    'price' => 999.99,
    'category' => 'Electronics',
    'public_reviews_count' => 150
];

// ❌ PELIGROSO - Información personal/sensible
$dangerousData = [
    'user_email' => 'user@example.com',
    'password_hash' => 'abc123...',
    'credit_card' => '4111-1111-1111-1111',
    'ssn' => '123-45-6789'
];
```

## Mejores Prácticas

### 1. Naming Conventions

```php
// Usar prefijos descriptivos que indiquen datos públicos
$userKey = "user_public_{$id}";
$productKey = "product_{$id}";
$configKey = "config_app";

// Incluir versión para invalidación
$apiKey = "api_v2_weather_{$city}";
```

### 2. TTL Strategy

```php
class TTLStrategy
{
    // TTL basado en tipo de datos
    const TTL_PUBLIC_DATA = 3600;     // 1 hora - datos públicos
    const TTL_CONFIG = 86400;         // 24 horas - configuración
    const TTL_API_RESPONSE = 900;     // 15 minutos - APIs externas
    const TTL_STATISTICS = 1800;      // 30 minutos - estadísticas
    const TTL_TEMPORARY = 300;        // 5 minutos - datos temporales
}
```

### 3. Monitoring

```php
class CacheMonitor
{
    public static function logCacheOperation($operation, $key, $hit = null)
    {
        $data = [
            'operation' => $operation,
            'key' => $key,
            'timestamp' => time(),
        ];
        
        if ($hit !== null) {
            $data['hit'] = $hit;
        }
        
        Yii::info($data, 'cache-monitor');
    }
}
```