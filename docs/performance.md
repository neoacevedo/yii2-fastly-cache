# Optimización de Rendimiento

Guía para optimizar el rendimiento de Yii2 Fastly Cache.

## Métricas de Rendimiento

### Benchmarks Típicos

| Operación | Latencia Promedio | Throughput |
|-----------|-------------------|------------|
| GET (hit) | 50-100ms | 1000+ ops/sec |
| GET (miss) | 50-100ms | 1000+ ops/sec |
| SET | 100-200ms | 500+ ops/sec |
| DELETE | 100-200ms | 500+ ops/sec |

### Factores que Afectan el Rendimiento

1. **Ubicación geográfica** - Distancia a los edge servers de Fastly
2. **Tamaño de datos** - Objetos más grandes tardan más en transferir
3. **Frecuencia de operaciones** - Rate limiting puede aplicar
4. **Conectividad de red** - Latencia y ancho de banda

## Estrategias de Optimización

### 1. Cache Layering (Caché en Capas)

Implementa múltiples niveles de caché para optimizar rendimiento:

```php
class LayeredCache extends Component
{
    public $localCache;    // APCu o Redis local
    public $fastlyCache;   // Fastly KV Store
    
    public function get($key)
    {
        // Nivel 1: Caché local (más rápido)
        $value = $this->localCache->get($key);
        if ($value !== false) {
            return $value;
        }
        
        // Nivel 2: Fastly (edge cache)
        $value = $this->fastlyCache->get($key);
        if ($value !== false) {
            // Guardar en caché local por corto tiempo
            $this->localCache->set($key, $value, 300); // 5 min
            return $value;
        }
        
        return false;
    }
    
    public function set($key, $value, $duration)
    {
        // Escribir en ambos niveles
        $this->localCache->set($key, $value, min($duration, 300));
        return $this->fastlyCache->set($key, $value, $duration);
    }
}
```

### 2. Batch Operations

Agrupa operaciones para reducir llamadas a la API:

```php
class BatchCacheWriter
{
    private $queue = [];
    private $batchSize = 10;
    
    public function queueSet($key, $value, $duration)
    {
        $this->queue[] = ['set', $key, $value, $duration];
        
        if (count($this->queue) >= $this->batchSize) {
            $this->flush();
        }
    }
    
    public function queueDelete($key)
    {
        $this->queue[] = ['delete', $key];
        
        if (count($this->queue) >= $this->batchSize) {
            $this->flush();
        }
    }
    
    public function flush()
    {
        if (empty($this->queue)) {
            return;
        }
        
        // Procesar en paralelo usando cURL multi
        $this->processParallel($this->queue);
        $this->queue = [];
    }
    
    private function processParallel($operations)
    {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        
        foreach ($operations as $i => $operation) {
            $ch = $this->createCurlHandle($operation);
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$i] = $ch;
        }
        
        // Ejecutar todas las operaciones en paralelo
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);
        
        // Limpiar
        foreach ($curlHandles as $ch) {
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        curl_multi_close($multiHandle);
    }
}
```

### 3. Async Operations

Implementa operaciones asíncronas para no bloquear la aplicación:

```php
class AsyncCacheManager
{
    private $queue;
    
    public function __construct()
    {
        $this->queue = new SplQueue();
    }
    
    public function setAsync($key, $value, $duration)
    {
        $this->queue->enqueue([
            'operation' => 'set',
            'key' => $key,
            'value' => $value,
            'duration' => $duration,
            'timestamp' => microtime(true)
        ]);
        
        // Procesar en background
        $this->processInBackground();
    }
    
    private function processInBackground()
    {
        // Usar process forking o job queue
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid == 0) {
                // Proceso hijo - procesar queue
                $this->processQueue();
                exit(0);
            }
        } else {
            // Fallback: procesar en el mismo proceso
            register_shutdown_function([$this, 'processQueue']);
        }
    }
    
    public function processQueue()
    {
        while (!$this->queue->isEmpty()) {
            $item = $this->queue->dequeue();
            $this->executeOperation($item);
        }
    }
}
```

### 4. Connection Pooling

Reutiliza conexiones cURL para reducir overhead:

```php
class ConnectionPool
{
    private static $pool = [];
    private static $maxConnections = 10;
    
    public static function getConnection($baseUrl)
    {
        $key = md5($baseUrl);
        
        if (!isset(self::$pool[$key])) {
            self::$pool[$key] = new SplQueue();
        }
        
        if (!self::$pool[$key]->isEmpty()) {
            return self::$pool[$key]->dequeue();
        }
        
        // Crear nueva conexión
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        return $ch;
    }
    
    public static function returnConnection($baseUrl, $ch)
    {
        $key = md5($baseUrl);
        
        if (!isset(self::$pool[$key])) {
            self::$pool[$key] = new SplQueue();
        }
        
        if (self::$pool[$key]->count() < self::$maxConnections) {
            // Reset handle for reuse
            curl_reset($ch);
            self::$pool[$key]->enqueue($ch);
        } else {
            curl_close($ch);
        }
    }
}
```

## Optimización de Datos

### 1. Compresión

Comprime datos grandes antes de almacenar:

```php
class CompressedCache extends FastlyKvCache
{
    public $compressionThreshold = 1024; // 1KB
    public $compressionLevel = 6;
    
    protected function setValue($key, $value, $duration)
    {
        $originalSize = strlen($value);
        
        if ($originalSize > $this->compressionThreshold) {
            $compressed = gzcompress($value, $this->compressionLevel);
            
            if (strlen($compressed) < $originalSize) {
                $value = base64_encode($compressed);
                $key = $key . ':compressed';
            }
        }
        
        return parent::setValue($key, $value, $duration);
    }
    
    protected function getValue($key)
    {
        // Intentar versión comprimida primero
        $compressedValue = parent::getValue($key . ':compressed');
        if ($compressedValue !== false) {
            return gzuncompress(base64_decode($compressedValue));
        }
        
        return parent::getValue($key);
    }
}
```

### 2. Serialización Optimizada

Usa serialización más eficiente:

```php
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    'serializer' => [
        'serialize' => function($data) {
            // Usar MessagePack para mejor rendimiento
            return msgpack_pack($data);
        },
        'unserialize' => function($data) {
            return msgpack_unpack($data);
        },
    ],
],
```

### 3. Fragmentación de Datos Grandes

Divide objetos grandes en fragmentos:

```php
class FragmentedCache
{
    private $cache;
    private $fragmentSize = 1024 * 100; // 100KB por fragmento
    
    public function setLarge($key, $data, $duration)
    {
        $serialized = serialize($data);
        $size = strlen($serialized);
        
        if ($size <= $this->fragmentSize) {
            return $this->cache->set($key, $data, $duration);
        }
        
        // Fragmentar datos
        $fragments = str_split($serialized, $this->fragmentSize);
        $fragmentCount = count($fragments);
        
        // Guardar metadatos
        $metadata = [
            'fragments' => $fragmentCount,
            'size' => $size,
            'created' => time()
        ];
        
        $this->cache->set($key . ':meta', $metadata, $duration);
        
        // Guardar fragmentos
        foreach ($fragments as $i => $fragment) {
            $fragmentKey = $key . ':frag:' . $i;
            $this->cache->set($fragmentKey, $fragment, $duration);
        }
        
        return true;
    }
    
    public function getLarge($key)
    {
        // Obtener metadatos
        $metadata = $this->cache->get($key . ':meta');
        if ($metadata === false) {
            return $this->cache->get($key); // Intentar como objeto normal
        }
        
        // Reconstruir desde fragmentos
        $data = '';
        for ($i = 0; $i < $metadata['fragments']; $i++) {
            $fragmentKey = $key . ':frag:' . $i;
            $fragment = $this->cache->get($fragmentKey);
            
            if ($fragment === false) {
                return false; // Fragmento faltante
            }
            
            $data .= $fragment;
        }
        
        return unserialize($data);
    }
}
```

## Monitoreo de Rendimiento

### 1. Métricas en Tiempo Real

```php
class PerformanceMonitor
{
    private static $metrics = [];
    
    public static function startTimer($operation, $key)
    {
        $id = uniqid();
        self::$metrics[$id] = [
            'operation' => $operation,
            'key' => $key,
            'start' => microtime(true)
        ];
        return $id;
    }
    
    public static function endTimer($id, $success = true)
    {
        if (!isset(self::$metrics[$id])) {
            return;
        }
        
        $metric = self::$metrics[$id];
        $metric['duration'] = microtime(true) - $metric['start'];
        $metric['success'] = $success;
        $metric['timestamp'] = time();
        
        // Enviar a sistema de métricas
        self::recordMetric($metric);
        
        unset(self::$metrics[$id]);
    }
    
    private static function recordMetric($metric)
    {
        // Enviar a StatsD, CloudWatch, etc.
        if (class_exists('StatsD')) {
            StatsD::timing('cache.' . $metric['operation'], $metric['duration'] * 1000);
            StatsD::increment('cache.' . $metric['operation'] . '.' . ($metric['success'] ? 'success' : 'error'));
        }
    }
}

// Uso en FastlyKvCache
protected function setValue($key, $value, $duration)
{
    $timerId = PerformanceMonitor::startTimer('set', $key);
    
    try {
        $result = parent::setValue($key, $value, $duration);
        PerformanceMonitor::endTimer($timerId, $result);
        return $result;
    } catch (Exception $e) {
        PerformanceMonitor::endTimer($timerId, false);
        throw $e;
    }
}
```

### 2. Dashboard de Métricas

```php
class CacheDashboard
{
    public function getStats($period = '1h')
    {
        return [
            'operations' => $this->getOperationStats($period),
            'performance' => $this->getPerformanceStats($period),
            'errors' => $this->getErrorStats($period),
            'hit_rate' => $this->getHitRate($period),
        ];
    }
    
    private function getHitRate($period)
    {
        $hits = $this->getMetricCount('cache.get.hit', $period);
        $misses = $this->getMetricCount('cache.get.miss', $period);
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }
    
    private function getPerformanceStats($period)
    {
        return [
            'avg_get_time' => $this->getAverageTime('cache.get', $period),
            'avg_set_time' => $this->getAverageTime('cache.set', $period),
            'p95_get_time' => $this->getPercentileTime('cache.get', 95, $period),
            'p95_set_time' => $this->getPercentileTime('cache.set', 95, $period),
        ];
    }
}
```

## Mejores Prácticas de Rendimiento

### 1. Estrategias de TTL

```php
class TTLStrategy
{
    // TTL basado en tipo de datos
    const TTL_USER_DATA = 3600;        // 1 hora
    const TTL_CONFIG = 86400;          // 24 horas
    const TTL_API_RESPONSE = 900;      // 15 minutos
    const TTL_SESSION = 1800;          // 30 minutos
    const TTL_TEMPORARY = 300;         // 5 minutos
    
    // TTL dinámico basado en uso
    public static function getDynamicTTL($key, $accessCount)
    {
        $baseTTL = 3600; // 1 hora base
        
        if ($accessCount > 100) {
            return $baseTTL * 2; // Datos muy accedidos duran más
        } elseif ($accessCount < 10) {
            return $baseTTL / 2; // Datos poco accedidos duran menos
        }
        
        return $baseTTL;
    }
}
```

### 2. Cache Warming Inteligente

```php
class IntelligentCacheWarmer
{
    public function warmupByPriority()
    {
        $priorities = [
            'critical' => $this->getCriticalKeys(),
            'high' => $this->getHighPriorityKeys(),
            'medium' => $this->getMediumPriorityKeys(),
        ];
        
        foreach ($priorities as $level => $keys) {
            $this->warmupKeys($keys, $level);
        }
    }
    
    private function warmupKeys($keys, $priority)
    {
        $batchSize = $this->getBatchSize($priority);
        $chunks = array_chunk($keys, $batchSize);
        
        foreach ($chunks as $chunk) {
            $this->warmupBatch($chunk);
            
            // Pausa entre batches para no sobrecargar
            if ($priority !== 'critical') {
                usleep(100000); // 100ms
            }
        }
    }
    
    private function getBatchSize($priority)
    {
        return match($priority) {
            'critical' => 50,
            'high' => 25,
            'medium' => 10,
            default => 5
        };
    }
}
```

### 3. Circuit Breaker para Resilencia

```php
class CacheCircuitBreaker
{
    private $failureCount = 0;
    private $lastFailureTime = 0;
    private $state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN
    
    const FAILURE_THRESHOLD = 5;
    const RECOVERY_TIMEOUT = 60; // 1 minuto
    
    public function execute(callable $operation)
    {
        if ($this->state === 'OPEN') {
            if (time() - $this->lastFailureTime > self::RECOVERY_TIMEOUT) {
                $this->state = 'HALF_OPEN';
            } else {
                throw new Exception('Circuit breaker is OPEN');
            }
        }
        
        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }
    
    private function onSuccess()
    {
        $this->failureCount = 0;
        $this->state = 'CLOSED';
    }
    
    private function onFailure()
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        
        if ($this->failureCount >= self::FAILURE_THRESHOLD) {
            $this->state = 'OPEN';
        }
    }
}
```

## Herramientas de Profiling

### 1. Cache Profiler

```php
class CacheProfiler
{
    private static $operations = [];
    
    public static function profile($operation, $key, callable $callback)
    {
        $start = microtime(true);
        $memoryBefore = memory_get_usage();
        
        try {
            $result = $callback();
            $success = true;
        } catch (Exception $e) {
            $result = null;
            $success = false;
            throw $e;
        } finally {
            $duration = microtime(true) - $start;
            $memoryUsed = memory_get_usage() - $memoryBefore;
            
            self::$operations[] = [
                'operation' => $operation,
                'key' => $key,
                'duration' => $duration,
                'memory' => $memoryUsed,
                'success' => $success,
                'timestamp' => microtime(true)
            ];
        }
        
        return $result;
    }
    
    public static function getReport()
    {
        $report = [
            'total_operations' => count(self::$operations),
            'total_time' => array_sum(array_column(self::$operations, 'duration')),
            'avg_time' => 0,
            'operations_by_type' => [],
        ];
        
        if ($report['total_operations'] > 0) {
            $report['avg_time'] = $report['total_time'] / $report['total_operations'];
            
            foreach (self::$operations as $op) {
                $type = $op['operation'];
                if (!isset($report['operations_by_type'][$type])) {
                    $report['operations_by_type'][$type] = [
                        'count' => 0,
                        'total_time' => 0,
                        'avg_time' => 0,
                    ];
                }
                
                $report['operations_by_type'][$type]['count']++;
                $report['operations_by_type'][$type]['total_time'] += $op['duration'];
                $report['operations_by_type'][$type]['avg_time'] = 
                    $report['operations_by_type'][$type]['total_time'] / 
                    $report['operations_by_type'][$type]['count'];
            }
        }
        
        return $report;
    }
}
```

### 2. Uso del Profiler

```bash
# Al final de la ejecución, mostrar reporte
register_shutdown_function(function() {
    if (YII_DEBUG) {
        $report = CacheProfiler::getReport();
        error_log("Cache Performance Report: " . json_encode($report));
    }
});
```