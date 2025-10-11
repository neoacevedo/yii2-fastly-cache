# Solución de Problemas

Guía para resolver problemas comunes con Yii2 Fastly Cache.

## Problemas de Configuración

### Error: "apiToken y storeId son requeridos"

**Síntomas:**
```
InvalidConfigException: apiToken y storeId son requeridos
```

**Causas Posibles:**
- Configuración faltante o vacía
- Variables de entorno no cargadas
- Configuración en archivo incorrecto

**Soluciones:**

1. **Verificar configuración:**
```php
// En config/web.php o config/main.php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => 'tu_token_aqui', // No debe estar vacío
        'storeId' => 'tu_store_id_aqui', // No debe estar vacío
    ],
],
```

2. **Verificar variables de entorno:**
```bash
# Verificar que las variables estén definidas
echo $FASTLY_API_TOKEN
echo $FASTLY_STORE_ID
```

3. **Debug de configuración:**
```php
// Agregar en el bootstrap o inicio de la aplicación
var_dump($_ENV['FASTLY_API_TOKEN']);
var_dump($_ENV['FASTLY_STORE_ID']);
```

### Error: Token de API Inválido

**Síntomas:**
- Respuestas HTTP 401 o 403
- Mensajes de "Unauthorized" en logs

**Soluciones:**

1. **Verificar token en Fastly Dashboard:**
   - Ve a [Personal API Tokens](https://manage.fastly.com/account/personal/tokens)
   - Verifica que el token existe y está activo
   - Regenera el token si es necesario

2. **Verificar permisos del token:**
   - `global:read`
   - `global:write`
   - `kv_store:read`
   - `kv_store:write`

3. **Test manual del token:**
```bash
curl -H "Fastly-Key: TU_TOKEN" \
     https://api.fastly.com/resources/stores/kv/TU_STORE_ID/keys/test
```

### Error: Store ID Incorrecto

**Síntomas:**
- Respuestas HTTP 404
- Mensajes de "Store not found"

**Soluciones:**

1. **Verificar Store ID en dashboard:**
   - Ve a KV Stores en Fastly Dashboard
   - Copia el ID exacto del store

2. **Listar stores disponibles:**
```bash
curl -H "Fastly-Key: TU_TOKEN" \
     https://api.fastly.com/resources/stores/kv
```

## Problemas de Conectividad

### Error: cURL Connection Failed

**Síntomas:**
```
cURL error: Could not resolve host
cURL error: Connection timeout
```

**Soluciones:**

1. **Verificar conectividad:**
```bash
# Test básico de conectividad
ping api.fastly.com

# Test HTTPS
curl -I https://api.fastly.com
```

2. **Verificar configuración de proxy:**
```php
// Si usas proxy, configúralo en cURL
private function makeRequest($method, $url, $data = null)
{
    $ch = curl_init();
    
    // Configuración de proxy si es necesario
    curl_setopt($ch, CURLOPT_PROXY, 'proxy.company.com:8080');
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, 'user:password');
    
    // ... resto de la configuración
}
```

3. **Verificar certificados SSL:**
```php
// Temporalmente para debug (NO usar en producción)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
```

### Error: Timeout de Conexión

**Síntomas:**
- Operaciones que tardan mucho tiempo
- Timeouts intermitentes

**Soluciones:**

1. **Aumentar timeout:**
```php
// En la configuración del componente
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    'timeout' => 30, // Si implementas esta opción
],
```

2. **Implementar retry logic:**
```php
private function makeRequestWithRetry($method, $url, $data = null, $retries = 3)
{
    for ($i = 0; $i < $retries; $i++) {
        $result = $this->makeRequest($method, $url, $data);
        
        if ($result !== false) {
            return $result;
        }
        
        // Esperar antes del siguiente intento
        sleep(pow(2, $i)); // Backoff exponencial
    }
    
    return false;
}
```

## Problemas de Rendimiento

### Cache Miss Frecuentes

**Síntomas:**
- Alto número de consultas a base de datos
- Rendimiento lento de la aplicación

**Diagnóstico:**
```php
// Agregar logging para monitorear hits/misses
Yii::$app->cache->on(Cache::EVENT_AFTER_GET, function($event) {
    $hit = $event->result !== false ? 'HIT' : 'MISS';
    Yii::info("Cache {$hit}: {$event->key}", 'cache-stats');
});
```

**Soluciones:**

1. **Verificar TTL:**
```php
// Asegúrate de que el TTL sea apropiado
Yii::$app->cache->set('key', $value, 3600); // 1 hora
```

2. **Implementar cache warming:**
```php
// Comando de consola para calentar caché
class CacheWarmupCommand extends \yii\console\Controller
{
    public function actionIndex()
    {
        $this->warmupCriticalData();
    }
    
    private function warmupCriticalData()
    {
        // Cargar datos críticos en caché
        $users = User::find()->limit(100)->all();
        foreach ($users as $user) {
            Yii::$app->cache->set("user_{$user->id}", $user, 3600);
        }
    }
}
```

### Latencia Alta

**Síntomas:**
- Operaciones de caché lentas
- Timeouts ocasionales

**Soluciones:**

1. **Implementar cache local como fallback:**
```php
class HybridCache extends Component
{
    public $fastlyCache;
    public $localCache;
    
    public function get($key)
    {
        // Intentar caché local primero
        $value = $this->localCache->get($key);
        if ($value !== false) {
            return $value;
        }
        
        // Luego Fastly
        $value = $this->fastlyCache->get($key);
        if ($value !== false) {
            // Guardar en caché local
            $this->localCache->set($key, $value, 300); // 5 min
        }
        
        return $value;
    }
}
```

2. **Usar operaciones asíncronas:**
```php
// Implementar operaciones no bloqueantes
class AsyncCacheWriter
{
    private $queue = [];
    
    public function queueSet($key, $value, $duration)
    {
        $this->queue[] = [$key, $value, $duration];
    }
    
    public function flush()
    {
        foreach ($this->queue as $item) {
            [$key, $value, $duration] = $item;
            // Escribir en background
            $this->writeAsync($key, $value, $duration);
        }
        $this->queue = [];
    }
}
```

## Problemas de Serialización

### Error: Datos Corruptos

**Síntomas:**
- Valores incorrectos al recuperar de caché
- Errores de deserialización

**Soluciones:**

1. **Verificar serialización:**
```php
// Test de serialización
$data = ['test' => 'value'];
$serialized = serialize($data);
$unserialized = unserialize($serialized);

var_dump($data === $unserialized); // Debe ser true
```

2. **Usar serialización JSON para debug:**
```php
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    'serializer' => [
        'serialize' => 'json_encode',
        'unserialize' => function($data) {
            return json_decode($data, true);
        },
    ],
],
```

### Error: Objetos No Serializables

**Síntomas:**
```
Error: __PHP_Incomplete_Class
Error: Serialization of 'Closure' is not allowed
```

**Soluciones:**

1. **Implementar __sleep() y __wakeup():**
```php
class User extends ActiveRecord
{
    public function __sleep()
    {
        // Solo serializar propiedades necesarias
        return ['id', 'name', 'email'];
    }
    
    public function __wakeup()
    {
        // Reinicializar después de deserializar
        $this->init();
    }
}
```

2. **Convertir a array antes de cachear:**
```php
// En lugar de cachear el objeto directamente
$user = User::findOne($id);
Yii::$app->cache->set("user_{$id}", $user->toArray(), 3600);
```

## Problemas de Logging y Debug

### Habilitar Debug Detallado

```php
// En config/web.php para desarrollo
'log' => [
    'targets' => [
        [
            'class' => 'yii\log\FileTarget',
            'levels' => ['error', 'warning', 'info'],
            'categories' => ['fastly-cache'],
            'logFile' => '@runtime/logs/fastly-cache.log',
        ],
    ],
],
```

### Monitoreo de Operaciones

```php
class CacheMonitor
{
    public static function logOperation($operation, $key, $success, $duration = null)
    {
        $data = [
            'operation' => $operation,
            'key' => $key,
            'success' => $success,
            'timestamp' => microtime(true),
        ];
        
        if ($duration !== null) {
            $data['duration'] = $duration;
        }
        
        Yii::info($data, 'cache-monitor');
    }
}

// Uso en FastlyKvCache
protected function setValue($key, $value, $duration)
{
    $start = microtime(true);
    $result = $this->makeRequest('PUT', $url, $value) !== false;
    $elapsed = microtime(true) - $start;
    
    CacheMonitor::logOperation('set', $key, $result, $elapsed);
    
    return $result;
}
```

## Herramientas de Diagnóstico

### Script de Diagnóstico Completo

```php
#!/usr/bin/env php
<?php
// scripts/diagnose-cache.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

$app = new yii\console\Application(require __DIR__ . '/../config/console.php');

echo "=== Diagnóstico de Fastly Cache ===\n\n";

// 1. Verificar configuración
echo "1. Verificando configuración...\n";
$cache = Yii::$app->cache;

if (!$cache instanceof \neoacevedo\yii2\fastly\FastlyKvCache) {
    echo "❌ Cache no es instancia de FastlyKvCache\n";
    exit(1);
}

echo "✅ Configuración básica OK\n";

// 2. Test de conectividad
echo "\n2. Probando conectividad...\n";
$testKey = 'diagnostic_' . time();
$testValue = 'test_value_' . uniqid();

try {
    $setResult = $cache->set($testKey, $testValue, 60);
    if ($setResult) {
        echo "✅ Escritura exitosa\n";
    } else {
        echo "❌ Error en escritura\n";
    }
    
    $getValue = $cache->get($testKey);
    if ($getValue === $testValue) {
        echo "✅ Lectura exitosa\n";
    } else {
        echo "❌ Error en lectura\n";
    }
    
    $deleteResult = $cache->delete($testKey);
    if ($deleteResult) {
        echo "✅ Eliminación exitosa\n";
    } else {
        echo "❌ Error en eliminación\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Diagnóstico completado ===\n";
```

### Monitoreo en Tiempo Real

```bash
# Monitorear logs de caché
tail -f runtime/logs/fastly-cache.log | grep -E "(ERROR|WARNING)"

# Monitorear métricas de rendimiento
tail -f runtime/logs/cache-monitor.log | jq '.duration' | awk '{sum+=$1; count++} END {print "Promedio:", sum/count "ms"}'
```

## Contacto y Soporte

Si los problemas persisten:

1. **Revisa los logs** de Fastly Dashboard
2. **Verifica el estado** de Fastly en [status.fastly.com](https://status.fastly.com)
3. **Reporta el issue** en [GitHub Issues](https://github.com/neoacevedo/yii2-fastly-cache/issues)
4. **Contacta al autor** en contacto@neoacevedo.nom.co

### Información a Incluir en Reportes

- Versión de PHP
- Versión de Yii2
- Versión del componente
- Configuración (sin tokens)
- Logs de error completos
- Pasos para reproducir el problema