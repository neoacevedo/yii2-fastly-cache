# Referencia de API

Documentación completa de la API de Yii2 Fastly Cache.

## Clase FastlyKvCache

### Herencia

```
yii\base\Component
└── yii\caching\Cache
    └── neoacevedo\yii2\fastly\FastlyKvCache
```

### Propiedades

#### Propiedades Públicas

| Propiedad | Tipo | Descripción |
|-----------|------|-------------|
| `$apiToken` | string | Token de API de Fastly (requerido) |
| `$storeId` | string | ID del KV Store de Fastly (requerido) |

#### Propiedades Heredadas

| Propiedad | Tipo | Valor por Defecto | Descripción |
|-----------|------|-------------------|-------------|
| `$keyPrefix` | string | '' | Prefijo para todas las claves de caché |
| `$defaultDuration` | int | 0 | Duración por defecto en segundos (0 = sin expiración) |
| `$serializer` | array\|false | null | Configuración del serializador |

#### Propiedades Protegidas

| Propiedad | Tipo | Valor por Defecto | Descripción |
|-----------|------|-------------------|-------------|
| `$baseUrl` | string | 'https://api.fastly.com' | URL base de la API de Fastly |

## Métodos Públicos

### init()

Inicializa el componente y valida la configuración.

```php
public function init(): void
```

**Excepciones:**
- `InvalidConfigException` - Si `apiToken` o `storeId` no están configurados

**Ejemplo:**
```php
// Se llama automáticamente durante la inicialización del componente
```

### get()

Obtiene un valor de caché por su clave.

```php
public function get($key): mixed
```

**Parámetros:**
- `$key` (string) - La clave de caché

**Retorna:**
- `mixed` - El valor almacenado o `false` si no existe

**Ejemplo:**
```php
$value = Yii::$app->cache->get('user_123');
if ($value !== false) {
    // Valor encontrado
}
```

### set()

Establece un valor en caché.

```php
public function set($key, $value, $duration = 0, $dependency = null): bool
```

**Parámetros:**
- `$key` (string) - La clave de caché
- `$value` (mixed) - El valor a almacenar
- `$duration` (int) - Duración en segundos (0 = usar defaultDuration)
- `$dependency` (Dependency|null) - Dependencia de caché

**Retorna:**
- `bool` - `true` si la operación fue exitosa

**Ejemplo:**
```php
// Guardar por 1 hora
$success = Yii::$app->cache->set('user_123', $userData, 3600);

// Guardar con dependencia
$dependency = new FileDependency(['fileName' => 'config.php']);
Yii::$app->cache->set('config', $data, 0, $dependency);
```

### add()

Agrega un valor solo si la clave no existe.

```php
public function add($key, $value, $duration = 0, $dependency = null): bool
```

**Parámetros:**
- `$key` (string) - La clave de caché
- `$value` (mixed) - El valor a almacenar
- `$duration` (int) - Duración en segundos
- `$dependency` (Dependency|null) - Dependencia de caché

**Retorna:**
- `bool` - `true` si se agregó exitosamente, `false` si la clave ya existe

**Ejemplo:**
```php
$success = Yii::$app->cache->add('user_123', $userData, 3600);
if (!$success) {
    echo "La clave ya existe";
}
```

### delete()

Elimina un valor de caché.

```php
public function delete($key): bool
```

**Parámetros:**
- `$key` (string) - La clave a eliminar

**Retorna:**
- `bool` - `true` si la operación fue exitosa

**Ejemplo:**
```php
$success = Yii::$app->cache->delete('user_123');
```

### exists()

Verifica si una clave existe en caché.

```php
public function exists($key): bool
```

**Parámetros:**
- `$key` (string) - La clave a verificar

**Retorna:**
- `bool` - `true` si la clave existe

**Ejemplo:**
```php
if (Yii::$app->cache->exists('user_123')) {
    echo "El usuario está en caché";
}
```

### flush()

Limpia toda la caché.

```php
public function flush(): bool
```

**Retorna:**
- `bool` - Siempre `false` (no implementado)

**Nota:**
Este método no está implementado debido a las limitaciones de la API de Fastly KV Store.

**Ejemplo:**
```php
// No funciona - siempre retorna false
$result = Yii::$app->cache->flush();
```

## Métodos Protegidos

### getValue()

Obtiene el valor crudo de Fastly KV Store.

```php
protected function getValue($key): string|false
```

**Parámetros:**
- `$key` (string) - La clave a obtener

**Retorna:**
- `string|false` - El valor crudo o `false` si no existe

### setValue()

Establece un valor crudo en Fastly KV Store.

```php
protected function setValue($key, $value, $duration): bool
```

**Parámetros:**
- `$key` (string) - La clave
- `$value` (string) - El valor serializado
- `$duration` (int) - Duración (ignorada por Fastly)

**Retorna:**
- `bool` - `true` si fue exitoso

### addValue()

Agrega un valor solo si no existe.

```php
protected function addValue($key, $value, $duration): bool
```

**Parámetros:**
- `$key` (string) - La clave
- `$value` (string) - El valor serializado
- `$duration` (int) - Duración

**Retorna:**
- `bool` - `true` si se agregó exitosamente

### deleteValue()

Elimina un valor de Fastly KV Store.

```php
protected function deleteValue($key): bool
```

**Parámetros:**
- `$key` (string) - La clave a eliminar

**Retorna:**
- `bool` - `true` si fue exitoso

### flushValues()

Limpia todos los valores (no implementado).

```php
protected function flushValues(): bool
```

**Retorna:**
- `bool` - Siempre `false`

## Métodos Privados

### makeRequest()

Realiza una solicitud HTTP a la API de Fastly.

```php
private function makeRequest($method, $url, $data = null): string|false
```

**Parámetros:**
- `$method` (string) - Método HTTP (GET, PUT, DELETE)
- `$url` (string) - URL completa de la API
- `$data` (string|null) - Datos a enviar

**Retorna:**
- `string|false` - Respuesta de la API o `false` en caso de error

## Eventos

### Eventos Heredados de Cache

| Evento | Descripción |
|--------|-------------|
| `EVENT_BEFORE_GET` | Antes de obtener un valor |
| `EVENT_AFTER_GET` | Después de obtener un valor |
| `EVENT_BEFORE_SET` | Antes de establecer un valor |
| `EVENT_AFTER_SET` | Después de establecer un valor |
| `EVENT_BEFORE_ADD` | Antes de agregar un valor |
| `EVENT_AFTER_ADD` | Después de agregar un valor |
| `EVENT_BEFORE_DELETE` | Antes de eliminar un valor |
| `EVENT_AFTER_DELETE` | Después de eliminar un valor |

### Uso de Eventos

```php
Yii::$app->cache->on(Cache::EVENT_AFTER_SET, function($event) {
    Yii::info("Cache set: {$event->key}", 'cache');
});

Yii::$app->cache->on(Cache::EVENT_AFTER_GET, function($event) {
    $hit = $event->result !== false ? 'HIT' : 'MISS';
    Yii::info("Cache get: {$event->key} - {$hit}", 'cache');
});
```

## Constantes

### Códigos de Respuesta HTTP

| Constante | Valor | Descripción |
|-----------|-------|-------------|
| HTTP_OK | 200 | Operación exitosa |
| HTTP_CREATED | 201 | Recurso creado |
| HTTP_NO_CONTENT | 204 | Sin contenido (eliminación exitosa) |
| HTTP_NOT_FOUND | 404 | Clave no encontrada |

## Excepciones

### InvalidConfigException

Se lanza cuando la configuración es inválida.

```php
try {
    $cache = new FastlyKvCache([
        // apiToken faltante
        'storeId' => 'store123'
    ]);
    $cache->init();
} catch (InvalidConfigException $e) {
    echo "Error de configuración: " . $e->getMessage();
}
```

### Manejo de Errores de Red

```php
try {
    $result = Yii::$app->cache->set('key', 'value');
    if (!$result) {
        // Error en la operación
        Yii::warning("No se pudo guardar en caché");
    }
} catch (Exception $e) {
    // Error de conexión u otro error
    Yii::error("Error de caché: " . $e->getMessage());
}
```

## Limitaciones

### 1. Método flush() No Implementado

```php
// No funciona
$result = Yii::$app->cache->flush(); // Siempre retorna false
```

### 2. TTL Manejado por Fastly

```php
// El parámetro duration es ignorado por Fastly
Yii::$app->cache->set('key', 'value', 3600); // TTL no aplicado
```

### 3. Operaciones Batch Limitadas

```php
// No hay soporte nativo para operaciones batch
// Debe implementarse manualmente si es necesario
```

## Ejemplos de Integración

### Con Active Record

```php
class User extends ActiveRecord
{
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        // Actualizar caché después de guardar
        $cacheKey = "user_{$this->id}";
        Yii::$app->cache->set($cacheKey, $this, 3600);
    }
    
    public function afterDelete()
    {
        parent::afterDelete();
        
        // Limpiar caché después de eliminar
        $cacheKey = "user_{$this->id}";
        Yii::$app->cache->delete($cacheKey);
    }
}
```

### Con Behaviors

```php
class CacheBehavior extends Behavior
{
    public $cacheKey;
    public $duration = 3600;
    
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }
    
    public function afterSave($event)
    {
        $key = $this->getCacheKey();
        Yii::$app->cache->set($key, $this->owner, $this->duration);
    }
}
```