# Configuración

Guía completa de configuración para Yii2 Fastly Cache.

## Parámetros de Configuración

### Parámetros Obligatorios

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `apiToken` | string | Token de API de Fastly |
| `storeId` | string | ID del KV Store de Fastly |

### Parámetros Opcionales (Heredados de Cache)

| Parámetro | Tipo | Valor por Defecto | Descripción |
|-----------|------|-------------------|-------------|
| `keyPrefix` | string | '' | Prefijo para todas las claves |
| `defaultDuration` | int | 0 | Duración por defecto en segundos |
| `serializer` | array | null | Configuración del serializador |

## Configuraciones por Entorno

### Desarrollo

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID_DEV'],
        'keyPrefix' => 'dev_',
        'defaultDuration' => 300, // 5 minutos
    ],
],
```

### Producción

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID_PROD'],
        'keyPrefix' => 'prod_',
        'defaultDuration' => 3600, // 1 hora
    ],
],
```

### Testing

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID_TEST'],
        'keyPrefix' => 'test_',
        'defaultDuration' => 60, // 1 minuto
    ],
],
```

## Configuración de Serialización

### Serialización por Defecto

```php
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    // Usa serialización PHP por defecto
],
```

### Serialización JSON

```php
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    'serializer' => [
        'serialize' => 'json_encode',
        'unserialize' => 'json_decode',
    ],
],
```

### Serialización Personalizada

```php
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    'serializer' => [
        'serialize' => function($data) {
            return gzcompress(serialize($data));
        },
        'unserialize' => function($data) {
            return unserialize(gzuncompress($data));
        },
    ],
],
```

## Configuración de Múltiples Stores

### Configuración Multi-Store

```php
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
        'defaultDuration' => 1800, // 30 minutos
    ],
    'dataCache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID_DATA'],
        'keyPrefix' => 'data_',
        'defaultDuration' => 7200, // 2 horas
    ],
],
```

### Uso de Múltiples Stores

```php
// Cache principal
Yii::$app->cache->set('user_data', $userData);

// Cache de sesiones
Yii::$app->sessionCache->set('session_key', $sessionData);

// Cache de datos
Yii::$app->dataCache->set('api_response', $apiData);
```

## Configuración de Seguridad

### Variables de Entorno

Archivo `.env`:

```env
# Fastly Configuration
FASTLY_API_TOKEN=your_secure_token_here
FASTLY_STORE_ID_DEV=dev_store_id
FASTLY_STORE_ID_PROD=prod_store_id
FASTLY_STORE_ID_TEST=test_store_id
```

### Configuración con Secrets Manager

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => function() {
            // Obtener desde AWS Secrets Manager, Azure Key Vault, etc.
            return SecretManager::getSecret('fastly-api-token');
        },
        'storeId' => $_ENV['FASTLY_STORE_ID'],
    ],
],
```

## Configuración Avanzada

### Con Logging Personalizado

```php
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    'on afterSet' => function($event) {
        Yii::info("Cache set: {$event->key}", 'fastly-cache');
    },
    'on afterGet' => function($event) {
        Yii::info("Cache get: {$event->key}", 'fastly-cache');
    },
],
```

### Con Métricas

```php
'cache' => [
    'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
    'apiToken' => $_ENV['FASTLY_API_TOKEN'],
    'storeId' => $_ENV['FASTLY_STORE_ID'],
    'on afterSet' => function($event) {
        // Incrementar contador de escrituras
        Metrics::increment('cache.writes');
    },
    'on afterGet' => function($event) {
        // Incrementar contador de lecturas
        Metrics::increment('cache.reads');
    },
],
```

## Configuración de Fallback

### Cache con Fallback Local

```php
'components' => [
    'cache' => [
        'class' => 'yii\caching\ChainedDependency',
        'caches' => [
            [
                'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
                'apiToken' => $_ENV['FASTLY_API_TOKEN'],
                'storeId' => $_ENV['FASTLY_STORE_ID'],
            ],
            [
                'class' => 'yii\caching\FileCache',
                'cachePath' => '@runtime/cache',
            ],
        ],
    ],
],
```

## Validación de Configuración

### Script de Validación

```php
<?php
// config/validate-cache.php

$config = require __DIR__ . '/web.php';
$cacheConfig = $config['components']['cache'];

// Validar configuración obligatoria
if (empty($cacheConfig['apiToken'])) {
    throw new Exception('apiToken es requerido');
}

if (empty($cacheConfig['storeId'])) {
    throw new Exception('storeId es requerido');
}

// Validar formato del token
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $cacheConfig['apiToken'])) {
    throw new Exception('Formato de apiToken inválido');
}

echo "✅ Configuración válida\n";
```

## Mejores Prácticas

### 1. Separación por Entorno

- Usa diferentes Store IDs para cada entorno
- Configura prefijos descriptivos
- Ajusta duraciones según el entorno

### 2. Seguridad

- Nunca hardcodees tokens en el código
- Usa variables de entorno o secrets managers
- Rota tokens periódicamente

### 3. Monitoreo

- Implementa logging de operaciones
- Configura métricas de rendimiento
- Establece alertas para errores

### 4. Fallback

- Configura cache local como respaldo
- Implementa degradación elegante
- Maneja errores de conectividad