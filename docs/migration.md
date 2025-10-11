# Guía de Migración

Guía para migrar desde otros sistemas de caché a Yii2 Fastly Cache.

## Migración desde FileCache

### Configuración Actual

```php
// Configuración anterior con FileCache
'components' => [
    'cache' => [
        'class' => 'yii\caching\FileCache',
        'cachePath' => '@runtime/cache',
        'keyPrefix' => 'myapp_',
        'defaultDuration' => 3600,
    ],
],
```

### Nueva Configuración

```php
// Nueva configuración con FastlyKvCache
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID'],
        'keyPrefix' => 'myapp_',
        'defaultDuration' => 3600,
    ],
],
```

### Script de Migración

```php
<?php
// scripts/migrate-from-filecache.php

use yii\caching\FileCache;
use neoacevedo\yii2\fastly\FastlyKvCache;

class FileCacheMigrator
{
    private $fileCache;
    private $fastlyCache;
    
    public function __construct()
    {
        $this->fileCache = new FileCache([
            'cachePath' => Yii::getAlias('@runtime/cache'),
        ]);
        
        $this->fastlyCache = new FastlyKvCache([
            'apiToken' => $_ENV['FASTLY_API_TOKEN'],
            'storeId' => $_ENV['FASTLY_STORE_ID'],
        ]);
    }
    
    public function migrate()
    {
        echo "Iniciando migración desde FileCache...\n";
        
        $cacheDir = Yii::getAlias('@runtime/cache');
        $files = glob($cacheDir . '/*.bin');
        
        $migrated = 0;
        $errors = 0;
        
        foreach ($files as $file) {
            try {
                $key = $this->extractKeyFromFilename($file);
                $data = $this->fileCache->get($key);
                
                if ($data !== false) {
                    $success = $this->fastlyCache->set($key, $data);
                    if ($success) {
                        $migrated++;
                        echo "✅ Migrado: {$key}\n";
                    } else {
                        $errors++;
                        echo "❌ Error migrando: {$key}\n";
                    }
                }
            } catch (Exception $e) {
                $errors++;
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\nMigración completada:\n";
        echo "- Migrados: {$migrated}\n";
        echo "- Errores: {$errors}\n";
    }
    
    private function extractKeyFromFilename($filename)
    {
        $basename = basename($filename, '.bin');
        return $basename;
    }
}

// Ejecutar migración
$migrator = new FileCacheMigrator();
$migrator->migrate();
```

## Migración desde Redis

### Configuración Actual

```php
// Configuración anterior con Redis
'components' => [
    'cache' => [
        'class' => 'yii\redis\Cache',
        'redis' => [
            'hostname' => 'localhost',
            'port' => 6379,
            'database' => 0,
        ],
        'keyPrefix' => 'myapp_',
    ],
],
```

### Script de Migración desde Redis

```php
<?php
// scripts/migrate-from-redis.php

use yii\redis\Cache as RedisCache;
use neoacevedo\yii2\fastly\FastlyKvCache;

class RedisMigrator
{
    private $redisCache;
    private $fastlyCache;
    
    public function __construct()
    {
        $this->redisCache = new RedisCache([
            'redis' => [
                'hostname' => 'localhost',
                'port' => 6379,
                'database' => 0,
            ],
        ]);
        
        $this->fastlyCache = new FastlyKvCache([
            'apiToken' => $_ENV['FASTLY_API_TOKEN'],
            'storeId' => $_ENV['FASTLY_STORE_ID'],
        ]);
    }
    
    public function migrate($pattern = '*')
    {
        echo "Iniciando migración desde Redis...\n";
        
        $redis = $this->redisCache->redis;
        $keys = $redis->keys($pattern);
        
        $migrated = 0;
        $errors = 0;
        
        foreach ($keys as $key) {
            try {
                $value = $this->redisCache->get($key);
                $ttl = $redis->ttl($key);
                
                if ($value !== false) {
                    $duration = $ttl > 0 ? $ttl : 0;
                    $success = $this->fastlyCache->set($key, $value, $duration);
                    
                    if ($success) {
                        $migrated++;
                        echo "✅ Migrado: {$key} (TTL: {$duration}s)\n";
                    } else {
                        $errors++;
                        echo "❌ Error migrando: {$key}\n";
                    }
                }
            } catch (Exception $e) {
                $errors++;
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\nMigración completada:\n";
        echo "- Migrados: {$migrated}\n";
        echo "- Errores: {$errors}\n";
    }
}

// Ejecutar migración
$migrator = new RedisMigrator();
$migrator->migrate('myapp_*'); // Solo claves con prefijo específico
```

## Migración desde Memcached

### Configuración Actual

```php
// Configuración anterior con Memcached
'components' => [
    'cache' => [
        'class' => 'yii\caching\MemCache',
        'servers' => [
            [
                'host' => 'localhost',
                'port' => 11211,
                'weight' => 100,
            ],
        ],
    ],
],
```

### Consideraciones Especiales

Memcached no permite enumerar todas las claves, por lo que necesitas:

1. **Mantener un registro de claves** en tu aplicación
2. **Migrar gradualmente** durante el uso normal
3. **Usar un período de transición** con doble escritura

### Estrategia de Migración Gradual

```php
class GradualMigrator
{
    private $memcache;
    private $fastlyCache;
    private $keyRegistry;
    
    public function __construct()
    {
        $this->memcache = Yii::$app->cache; // Memcached actual
        $this->fastlyCache = new FastlyKvCache([
            'apiToken' => $_ENV['FASTLY_API_TOKEN'],
            'storeId' => $_ENV['FASTLY_STORE_ID'],
        ]);
        $this->keyRegistry = new KeyRegistry();
    }
    
    public function get($key)
    {
        // Intentar Fastly primero
        $value = $this->fastlyCache->get($key);
        if ($value !== false) {
            return $value;
        }
        
        // Fallback a Memcached
        $value = $this->memcache->get($key);
        if ($value !== false) {
            // Migrar automáticamente a Fastly
            $this->fastlyCache->set($key, $value);
            echo "Auto-migrado: {$key}\n";
        }
        
        return $value;
    }
    
    public function set($key, $value, $duration = 0)
    {
        // Escribir en ambos durante la transición
        $fastlyResult = $this->fastlyCache->set($key, $value, $duration);
        $memcacheResult = $this->memcache->set($key, $value, $duration);
        
        $this->keyRegistry->addKey($key);
        
        return $fastlyResult && $memcacheResult;
    }
}
```

## Migración de Configuraciones Complejas

### Múltiples Componentes de Caché

```php
// Configuración anterior
'components' => [
    'cache' => [
        'class' => 'yii\caching\FileCache',
    ],
    'sessionCache' => [
        'class' => 'yii\redis\Cache',
    ],
    'dataCache' => [
        'class' => 'yii\caching\MemCache',
    ],
],

// Nueva configuración
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID_MAIN'],
    ],
    'sessionCache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID_SESSIONS'],
        'keyPrefix' => 'sess_',
    ],
    'dataCache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID_DATA'],
        'keyPrefix' => 'data_',
    ],
],
```

### Migración de Dependencias

```php
// Migrar dependencias de caché
class DependencyMigrator
{
    public function migrateDependencies()
    {
        // FileDependency - compatible
        $fileDep = new FileDependency(['fileName' => 'config.php']);
        Yii::$app->cache->set('config', $data, 0, $fileDep);
        
        // TagDependency - compatible
        $tagDep = new TagDependency(['tags' => 'users']);
        Yii::$app->cache->set('user_list', $users, 0, $tagDep);
        
        // DbDependency - compatible
        $dbDep = new DbDependency([
            'sql' => 'SELECT MAX(updated_at) FROM users'
        ]);
        Yii::$app->cache->set('user_stats', $stats, 0, $dbDep);
    }
}
```

## Estrategias de Migración

### 1. Migración Big Bang

Cambiar toda la configuración de una vez:

```php
// scripts/big-bang-migration.php

class BigBangMigration
{
    public function execute()
    {
        echo "=== MIGRACIÓN BIG BANG ===\n";
        
        // 1. Backup de configuración actual
        $this->backupCurrentConfig();
        
        // 2. Migrar datos existentes
        $this->migrateExistingData();
        
        // 3. Actualizar configuración
        $this->updateConfiguration();
        
        // 4. Verificar funcionamiento
        $this->verifyMigration();
        
        echo "Migración completada\n";
    }
    
    private function backupCurrentConfig()
    {
        $config = require Yii::getAlias('@app/config/web.php');
        file_put_contents(
            Yii::getAlias('@app/config/web.backup.php'),
            "<?php\nreturn " . var_export($config, true) . ";\n"
        );
    }
}
```

### 2. Migración Gradual (Blue-Green)

```php
// scripts/gradual-migration.php

class GradualMigration
{
    private $phase = 1;
    
    public function executePhase1()
    {
        echo "=== FASE 1: Doble Escritura ===\n";
        
        // Configurar doble escritura
        $this->setupDualWrite();
        
        // Migrar datos críticos
        $this->migrateCriticalData();
    }
    
    public function executePhase2()
    {
        echo "=== FASE 2: Lectura Híbrida ===\n";
        
        // Leer de Fastly, fallback a sistema anterior
        $this->setupHybridRead();
        
        // Migrar datos restantes
        $this->migrateRemainingData();
    }
    
    public function executePhase3()
    {
        echo "=== FASE 3: Solo Fastly ===\n";
        
        // Cambiar completamente a Fastly
        $this->switchToFastlyOnly();
        
        // Limpiar sistema anterior
        $this->cleanupOldSystem();
    }
}
```

### 3. Migración por Funcionalidad

```php
class FeatureMigration
{
    public function migrateUserSessions()
    {
        echo "Migrando sesiones de usuario...\n";
        // Migrar solo el caché de sesiones
    }
    
    public function migrateApiResponses()
    {
        echo "Migrando respuestas de API...\n";
        // Migrar solo el caché de API
    }
    
    public function migrateStaticData()
    {
        echo "Migrando datos estáticos...\n";
        // Migrar configuraciones y datos estáticos
    }
}
```

## Validación Post-Migración

### Script de Validación

```php
<?php
// scripts/validate-migration.php

class MigrationValidator
{
    private $fastlyCache;
    
    public function __construct()
    {
        $this->fastlyCache = Yii::$app->cache;
    }
    
    public function validate()
    {
        echo "=== VALIDACIÓN POST-MIGRACIÓN ===\n";
        
        $tests = [
            'testBasicOperations',
            'testDataIntegrity',
            'testPerformance',
            'testDependencies',
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                $this->$test();
                echo "✅ {$test} - PASÓ\n";
                $passed++;
            } catch (Exception $e) {
                echo "❌ {$test} - FALLÓ: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\nResultados:\n";
        echo "- Pruebas pasadas: {$passed}\n";
        echo "- Pruebas fallidas: {$failed}\n";
        
        return $failed === 0;
    }
    
    private function testBasicOperations()
    {
        $key = 'test_' . uniqid();
        $value = 'test_value_' . time();
        
        // Test SET
        if (!$this->fastlyCache->set($key, $value, 60)) {
            throw new Exception('SET operation failed');
        }
        
        // Test GET
        $retrieved = $this->fastlyCache->get($key);
        if ($retrieved !== $value) {
            throw new Exception('GET operation failed');
        }
        
        // Test EXISTS
        if (!$this->fastlyCache->exists($key)) {
            throw new Exception('EXISTS operation failed');
        }
        
        // Test DELETE
        if (!$this->fastlyCache->delete($key)) {
            throw new Exception('DELETE operation failed');
        }
    }
    
    private function testDataIntegrity()
    {
        // Verificar que los datos migrados son correctos
        $sampleKeys = ['user_1', 'config_app', 'menu_items'];
        
        foreach ($sampleKeys as $key) {
            $value = $this->fastlyCache->get($key);
            if ($value === false) {
                throw new Exception("Key {$key} not found after migration");
            }
            
            // Verificar estructura de datos
            if (is_array($value) && empty($value)) {
                throw new Exception("Key {$key} has empty data");
            }
        }
    }
    
    private function testPerformance()
    {
        $iterations = 100;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $key = "perf_test_{$i}";
            $this->fastlyCache->set($key, "value_{$i}", 60);
            $this->fastlyCache->get($key);
        }
        
        $duration = microtime(true) - $start;
        $avgTime = ($duration / $iterations) * 1000; // ms
        
        if ($avgTime > 500) { // 500ms por operación es demasiado
            throw new Exception("Performance too slow: {$avgTime}ms per operation");
        }
    }
    
    private function testDependencies()
    {
        // Test FileDependency
        $tempFile = tempnam(sys_get_temp_dir(), 'cache_test');
        file_put_contents($tempFile, 'test content');
        
        $dependency = new FileDependency(['fileName' => $tempFile]);
        $this->fastlyCache->set('dep_test', 'value', 60, $dependency);
        
        // Modificar archivo
        sleep(1);
        file_put_contents($tempFile, 'modified content');
        
        // El valor debería ser inválido ahora
        $value = $this->fastlyCache->get('dep_test');
        if ($value !== false) {
            throw new Exception('FileDependency not working correctly');
        }
        
        unlink($tempFile);
    }
}

// Ejecutar validación
$validator = new MigrationValidator();
$success = $validator->validate();

exit($success ? 0 : 1);
```

## Rollback Plan

### Script de Rollback

```php
<?php
// scripts/rollback-migration.php

class MigrationRollback
{
    public function rollback()
    {
        echo "=== INICIANDO ROLLBACK ===\n";
        
        try {
            // 1. Restaurar configuración anterior
            $this->restoreConfiguration();
            
            // 2. Migrar datos críticos de vuelta
            $this->migrateDataBack();
            
            // 3. Verificar funcionamiento
            $this->verifyRollback();
            
            echo "✅ Rollback completado exitosamente\n";
            
        } catch (Exception $e) {
            echo "❌ Error durante rollback: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    private function restoreConfiguration()
    {
        $backupFile = Yii::getAlias('@app/config/web.backup.php');
        $configFile = Yii::getAlias('@app/config/web.php');
        
        if (!file_exists($backupFile)) {
            throw new Exception('Backup configuration not found');
        }
        
        copy($backupFile, $configFile);
        echo "✅ Configuración restaurada\n";
    }
}
```

## Checklist de Migración

### Pre-Migración

- [ ] Backup completo de la configuración actual
- [ ] Backup de datos de caché críticos
- [ ] Configuración de credenciales de Fastly
- [ ] Pruebas en entorno de desarrollo
- [ ] Plan de rollback definido

### Durante la Migración

- [ ] Monitoreo de errores en tiempo real
- [ ] Verificación de conectividad con Fastly
- [ ] Migración de datos por lotes
- [ ] Validación de integridad de datos

### Post-Migración

- [ ] Validación completa de funcionalidad
- [ ] Monitoreo de rendimiento
- [ ] Limpieza de sistema anterior
- [ ] Documentación de cambios
- [ ] Capacitación del equipo

## Consideraciones Especiales

### 1. Datos Sensibles

```php
// Filtrar datos sensibles durante la migración
class SecureMigrator
{
    private $sensitivePatterns = [
        '/password/',
        '/token/',
        '/secret/',
        '/key/',
    ];
    
    public function shouldMigrate($key, $value)
    {
        foreach ($this->sensitivePatterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return false; // No migrar datos sensibles
            }
        }
        
        return true;
    }
}
```

### 2. Datos Grandes

```php
// Manejar objetos grandes durante la migración
class LargeDataMigrator
{
    private $maxSize = 1024 * 1024; // 1MB
    
    public function migrate($key, $value)
    {
        $serialized = serialize($value);
        
        if (strlen($serialized) > $this->maxSize) {
            echo "⚠️  Objeto grande detectado: {$key} - Fragmentando...\n";
            return $this->migrateFragmented($key, $value);
        }
        
        return $this->migrateNormal($key, $value);
    }
}
```