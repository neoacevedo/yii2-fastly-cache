# Limitaciones de Fastly KV Store

Documentación completa de las limitaciones y restricciones de Fastly KV Store que debes conocer.

## Límites de Almacenamiento

### Límites por Clave

| Límite | Valor | Descripción |
|--------|-------|-------------|
| **Tamaño máximo de clave** | 1,024 bytes | Longitud máxima del nombre de la clave |
| **Tamaño máximo de valor** | 25 MB | Tamaño máximo por valor almacenado |
| **Caracteres válidos en clave** | A-Z, a-z, 0-9, -, _, ., ~ | Solo caracteres alfanuméricos y algunos especiales |

### Límites por Store

| Límite | Valor | Descripción |
|--------|-------|-------------|
| **Número máximo de claves** | 1,000,000 | Un millón de claves por store |
| **Tamaño total del store** | 1 GB | Límite total de almacenamiento |
| **Número de stores por cuenta** | Varía por plan | Consultar con Fastly |

### Límites de Operaciones

| Operación | Límite | Descripción |
|-----------|--------|-------------|
| **Escrituras por segundo** | 1,000 ops/sec | Rate limit para operaciones PUT |
| **Lecturas por segundo** | 50,000 ops/sec | Rate limit para operaciones GET |
| **Eliminaciones por segundo** | 1,000 ops/sec | Rate limit para operaciones DELETE |

## Validación de Límites en el Código

### Validador de Claves

```php
class FastlyKvValidator
{
    const MAX_KEY_LENGTH = 1024;
    const MAX_VALUE_SIZE = 25 * 1024 * 1024; // 25MB
    const VALID_KEY_PATTERN = '/^[A-Za-z0-9\-_\.~]+$/';
    
    public static function validateKey($key)
    {
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException(
                "Key too long: " . strlen($key) . " bytes (max: " . self::MAX_KEY_LENGTH . ")"
            );
        }
        
        if (!preg_match(self::VALID_KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(
                "Invalid key format: {$key}. Only A-Z, a-z, 0-9, -, _, ., ~ allowed"
            );
        }
        
        return true;
    }
    
    public static function validateValue($value)
    {
        $serialized = serialize($value);
        $size = strlen($serialized);
        
        if ($size > self::MAX_VALUE_SIZE) {
            throw new InvalidArgumentException(
                "Value too large: " . number_format($size) . " bytes (max: " . number_format(self::MAX_VALUE_SIZE) . ")"
            );
        }
        
        return $size;
    }
    
    public static function sanitizeKey($key)
    {
        // Reemplazar caracteres inválidos
        $sanitized = preg_replace('/[^A-Za-z0-9\-_\.~]/', '_', $key);
        
        // Truncar si es muy largo
        if (strlen($sanitized) > self::MAX_KEY_LENGTH) {
            $sanitized = substr($sanitized, 0, self::MAX_KEY_LENGTH - 8) . '_' . substr(md5($key), 0, 7);
        }
        
        return $sanitized;
    }
}
```

### FastlyKvCache Mejorado con Validaciones

```php
class FastlyKvCache extends Cache
{
    // ... propiedades existentes ...
    
    protected function setValue($key, $value, $duration)
    {
        // Validar clave
        try {
            FastlyKvValidator::validateKey($key);
        } catch (InvalidArgumentException $e) {
            Yii::warning("Invalid key: {$e->getMessage()}", 'fastly-cache');
            return false;
        }
        
        // Validar valor
        try {
            $size = FastlyKvValidator::validateValue($value);
            Yii::info("Storing {$size} bytes for key: {$key}", 'fastly-cache');
        } catch (InvalidArgumentException $e) {
            Yii::error("Value too large: {$e->getMessage()}", 'fastly-cache');
            return false;
        }
        
        $url = "{$this->baseUrl}/resources/stores/kv/{$this->storeId}/keys/{$key}";
        return $this->makeRequest('PUT', $url, $value) !== false;
    }
    
    public function set($key, $value, $duration = 0, $dependency = null)
    {
        // Sanitizar clave automáticamente
        $originalKey = $key;
        $key = $this->keyPrefix . FastlyKvValidator::sanitizeKey($key);
        
        if ($key !== $this->keyPrefix . $originalKey) {
            Yii::info("Key sanitized: '{$originalKey}' -> '{$key}'", 'fastly-cache');
        }
        
        return parent::set($key, $value, $duration, $dependency);
    }
}
```

## Estrategias para Manejar Límites

### 1. Fragmentación de Datos Grandes

```php
class FragmentedStorage
{
    private $cache;
    private $fragmentSize;
    
    public function __construct($cache, $fragmentSize = 20 * 1024 * 1024) // 20MB por fragmento
    {
        $this->cache = $cache;
        $this->fragmentSize = $fragmentSize;
    }
    
    public function setLarge($key, $data, $duration = 0)
    {
        $serialized = serialize($data);
        $totalSize = strlen($serialized);
        
        if ($totalSize <= $this->fragmentSize) {
            return $this->cache->set($key, $data, $duration);
        }
        
        // Fragmentar datos
        $fragments = str_split($serialized, $this->fragmentSize);
        $fragmentCount = count($fragments);
        
        // Guardar metadatos
        $metadata = [
            'fragments' => $fragmentCount,
            'total_size' => $totalSize,
            'created_at' => time(),
            'checksum' => md5($serialized)
        ];
        
        if (!$this->cache->set($key . ':meta', $metadata, $duration)) {
            return false;
        }
        
        // Guardar fragmentos
        for ($i = 0; $i < $fragmentCount; $i++) {
            $fragmentKey = $key . ':frag:' . str_pad($i, 4, '0', STR_PAD_LEFT);
            if (!$this->cache->set($fragmentKey, $fragments[$i], $duration)) {
                // Limpiar fragmentos parciales
                $this->cleanupFragments($key, $i);
                return false;
            }
        }
        
        Yii::info("Stored large object: {$key} ({$totalSize} bytes in {$fragmentCount} fragments)", 'fastly-cache');
        return true;
    }
    
    public function getLarge($key)
    {
        // Intentar obtener como objeto normal primero
        $data = $this->cache->get($key);
        if ($data !== false) {
            return $data;
        }
        
        // Intentar obtener como objeto fragmentado
        $metadata = $this->cache->get($key . ':meta');
        if ($metadata === false) {
            return false;
        }
        
        // Reconstruir desde fragmentos
        $reconstructed = '';
        for ($i = 0; $i < $metadata['fragments']; $i++) {
            $fragmentKey = $key . ':frag:' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $fragment = $this->cache->get($fragmentKey);
            
            if ($fragment === false) {
                Yii::warning("Missing fragment {$i} for key: {$key}", 'fastly-cache');
                return false;
            }
            
            $reconstructed .= $fragment;
        }
        
        // Verificar integridad
        if (md5($reconstructed) !== $metadata['checksum']) {
            Yii::error("Checksum mismatch for fragmented key: {$key}", 'fastly-cache');
            return false;
        }
        
        return unserialize($reconstructed);
    }
    
    private function cleanupFragments($key, $maxIndex)
    {
        for ($i = 0; $i < $maxIndex; $i++) {
            $fragmentKey = $key . ':frag:' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $this->cache->delete($fragmentKey);
        }
        $this->cache->delete($key . ':meta');
    }
}
```

### 2. Compresión Automática

```php
class CompressedCache extends FastlyKvCache
{
    public $compressionThreshold = 1024; // 1KB
    public $compressionLevel = 6;
    public $maxCompressedSize = 20 * 1024 * 1024; // 20MB después de compresión
    
    protected function setValue($key, $value, $duration)
    {
        $originalSize = strlen($value);
        $compressed = false;
        
        if ($originalSize > $this->compressionThreshold) {
            $compressedValue = gzcompress($value, $this->compressionLevel);
            $compressedSize = strlen($compressedValue);
            
            if ($compressedSize < $originalSize && $compressedSize <= $this->maxCompressedSize) {
                $value = base64_encode($compressedValue);
                $key = $key . ':gz';
                $compressed = true;
                
                Yii::info("Compressed {$originalSize} -> {$compressedSize} bytes ({$key})", 'fastly-cache');
            }
        }
        
        // Validar tamaño final
        if (strlen($value) > FastlyKvValidator::MAX_VALUE_SIZE) {
            Yii::error("Value still too large after compression: " . strlen($value) . " bytes", 'fastly-cache');
            return false;
        }
        
        return parent::setValue($key, $value, $duration);
    }
    
    protected function getValue($key)
    {
        // Intentar versión comprimida primero
        $compressedValue = parent::getValue($key . ':gz');
        if ($compressedValue !== false) {
            return gzuncompress(base64_decode($compressedValue));
        }
        
        // Fallback a versión normal
        return parent::getValue($key);
    }
}
```

### 3. Monitoreo de Uso del Store

```php
class StoreUsageMonitor
{
    private $cache;
    private $keyCount = 0;
    private $totalSize = 0;
    
    public function __construct($cache)
    {
        $this->cache = $cache;
        $this->loadUsageStats();
    }
    
    public function trackSet($key, $valueSize)
    {
        $this->keyCount++;
        $this->totalSize += $valueSize;
        
        $this->checkLimits();
        $this->saveUsageStats();
    }
    
    public function trackDelete($key, $valueSize)
    {
        $this->keyCount--;
        $this->totalSize -= $valueSize;
        
        $this->saveUsageStats();
    }
    
    private function checkLimits()
    {
        // Verificar límite de claves
        if ($this->keyCount > 900000) { // 90% del límite
            Yii::warning("Approaching key limit: {$this->keyCount}/1,000,000", 'fastly-cache');
        }
        
        // Verificar límite de tamaño
        $maxSize = 1024 * 1024 * 1024; // 1GB
        if ($this->totalSize > $maxSize * 0.9) { // 90% del límite
            Yii::warning("Approaching size limit: " . number_format($this->totalSize) . "/{$maxSize}", 'fastly-cache');
        }
    }
    
    public function getUsageReport()
    {
        return [
            'key_count' => $this->keyCount,
            'key_limit' => 1000000,
            'key_usage_percent' => ($this->keyCount / 1000000) * 100,
            'total_size' => $this->totalSize,
            'size_limit' => 1024 * 1024 * 1024,
            'size_usage_percent' => ($this->totalSize / (1024 * 1024 * 1024)) * 100,
        ];
    }
}
```

## Rate Limiting

### Implementación de Rate Limiter

```php
class FastlyRateLimiter
{
    private $writeTokens = 1000;
    private $readTokens = 50000;
    private $deleteTokens = 1000;
    private $lastRefill;
    
    public function __construct()
    {
        $this->lastRefill = time();
    }
    
    public function canWrite()
    {
        $this->refillTokens();
        
        if ($this->writeTokens > 0) {
            $this->writeTokens--;
            return true;
        }
        
        return false;
    }
    
    public function canRead()
    {
        $this->refillTokens();
        
        if ($this->readTokens > 0) {
            $this->readTokens--;
            return true;
        }
        
        return false;
    }
    
    public function canDelete()
    {
        $this->refillTokens();
        
        if ($this->deleteTokens > 0) {
            $this->deleteTokens--;
            return true;
        }
        
        return false;
    }
    
    private function refillTokens()
    {
        $now = time();
        $elapsed = $now - $this->lastRefill;
        
        if ($elapsed >= 1) { // Refill cada segundo
            $this->writeTokens = min(1000, $this->writeTokens + (1000 * $elapsed));
            $this->readTokens = min(50000, $this->readTokens + (50000 * $elapsed));
            $this->deleteTokens = min(1000, $this->deleteTokens + (1000 * $elapsed));
            $this->lastRefill = $now;
        }
    }
}
```

## Mejores Prácticas para Límites

### 1. Diseño de Claves Eficiente

```php
class EfficientKeyDesign
{
    // Usar prefijos cortos pero descriptivos
    const PREFIXES = [
        'u' => 'user',      // u:123 en lugar de user:123
        'p' => 'product',   // p:456 en lugar de product:456
        's' => 'session',   // s:abc en lugar de session:abc
        'c' => 'config',    // c:app en lugar de config:app
    ];
    
    public static function createKey($type, $id, $suffix = null)
    {
        $prefix = self::PREFIXES[$type] ?? $type;
        $key = $prefix . ':' . $id;
        
        if ($suffix) {
            $key .= ':' . $suffix;
        }
        
        // Asegurar que no exceda el límite
        if (strlen($key) > 1000) { // Dejar margen
            $hash = substr(md5($key), 0, 8);
            $key = substr($key, 0, 990) . ':' . $hash;
        }
        
        return $key;
    }
}
```

### 2. Limpieza Automática

```php
class AutoCleanup
{
    private $cache;
    private $maxKeys = 950000; // 95% del límite
    
    public function __construct($cache)
    {
        $this->cache = $cache;
    }
    
    public function cleanupIfNeeded()
    {
        $usage = $this->getUsageStats();
        
        if ($usage['key_count'] > $this->maxKeys) {
            $this->performCleanup();
        }
    }
    
    private function performCleanup()
    {
        // Estrategias de limpieza:
        // 1. Eliminar claves expiradas
        // 2. Eliminar claves menos usadas (LRU)
        // 3. Eliminar claves temporales
        
        Yii::info("Starting automatic cleanup", 'fastly-cache');
        
        $this->cleanupExpiredKeys();
        $this->cleanupLRUKeys();
        $this->cleanupTemporaryKeys();
    }
}
```

## Alertas y Monitoreo

### Sistema de Alertas

```php
class LimitAlerting
{
    public function checkAndAlert()
    {
        $usage = $this->getUsageStats();
        
        // Alerta por uso de claves
        if ($usage['key_usage_percent'] > 90) {
            $this->sendAlert('KEY_LIMIT_WARNING', [
                'current' => $usage['key_count'],
                'limit' => $usage['key_limit'],
                'percentage' => $usage['key_usage_percent']
            ]);
        }
        
        // Alerta por uso de almacenamiento
        if ($usage['size_usage_percent'] > 90) {
            $this->sendAlert('SIZE_LIMIT_WARNING', [
                'current' => $usage['total_size'],
                'limit' => $usage['size_limit'],
                'percentage' => $usage['size_usage_percent']
            ]);
        }
    }
    
    private function sendAlert($type, $data)
    {
        // Enviar a Slack, email, etc.
        Yii::error("FASTLY LIMIT ALERT: {$type} - " . json_encode($data), 'fastly-alerts');
    }
}
```