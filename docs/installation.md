# Instalación

Esta guía te ayudará a instalar y configurar Yii2 Fastly Cache en tu proyecto.

## Requisitos del Sistema

### Requisitos Mínimos

- **PHP**: >= 8.2
- **Yii2**: >= 2.0.54
- **Extensiones PHP**:
  - cURL (requerida)
  - JSON (requerida)
- **Cuenta Fastly**: Con KV Store habilitado

### Limitaciones Importantes

⚠️ **Antes de continuar, revisa las [limitaciones de Fastly KV Store](limitations.md):**
- Máximo 25MB por valor
- Máximo 1,000,000 claves por store
- Máximo 1GB total por store
- Rate limits: 1,000 escrituras/seg, 50,000 lecturas/seg

### Verificar Requisitos

```bash
# Verificar versión de PHP
php --version

# Verificar extensiones
php -m | grep curl
php -m | grep json

# Verificar Yii2
composer show yiisoft/yii2
```

## Instalación via Composer

### 1. Instalar el Paquete

```bash
composer require neoacevedo/yii2-fastly-cache
```

### 2. Verificar Instalación

```bash
composer show neoacevedo/yii2-fastly-cache
```

## Configuración de Fastly

### 1. Crear API Token

1. Accede a [Fastly Dashboard](https://manage.fastly.com/account/personal/tokens)
2. Clic en "Create Token"
3. Selecciona los permisos necesarios:
   - `global:read`
   - `global:write`
   - `kv_store:read`
   - `kv_store:write`
4. Guarda el token de forma segura

### 2. Crear KV Store

1. En el dashboard de Fastly, ve a "KV Stores"
2. Clic en "Create a KV Store"
3. Asigna un nombre descriptivo
4. Copia el Store ID generado

## Configuración en Yii2

### Configuración Básica

Agrega en `config/web.php` o `config/main.php`:

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => 'tu_fastly_api_token_aqui',
        'storeId' => 'tu_store_id_aqui',
    ],
],
```

### Usando Variables de Entorno

Crea un archivo `.env`:

```env
FASTLY_API_TOKEN=tu_token_aqui
FASTLY_STORE_ID=tu_store_id_aqui
```

Configuración:

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID'],
    ],
],
```

## Verificación de Instalación

### Test Básico

```php
// En un controlador o consola
try {
    Yii::$app->cache->set('test_key', 'test_value', 60);
    $value = Yii::$app->cache->get('test_key');
    
    if ($value === 'test_value') {
        echo "✅ Instalación exitosa\n";
    } else {
        echo "❌ Error en la configuración\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```

### Script de Verificación

Crea `scripts/verify-cache.php`:

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

$app = new yii\console\Application(require __DIR__ . '/../config/console.php');

echo "Verificando configuración de Fastly Cache...\n";

try {
    $cache = Yii::$app->cache;
    
    // Test de escritura
    $testKey = 'verify_' . time();
    $testValue = 'verification_' . uniqid();
    
    if ($cache->set($testKey, $testValue, 60)) {
        echo "✅ Escritura exitosa\n";
    } else {
        echo "❌ Error en escritura\n";
        exit(1);
    }
    
    // Test de lectura
    $retrievedValue = $cache->get($testKey);
    if ($retrievedValue === $testValue) {
        echo "✅ Lectura exitosa\n";
    } else {
        echo "❌ Error en lectura\n";
        exit(1);
    }
    
    // Test de eliminación
    if ($cache->delete($testKey)) {
        echo "✅ Eliminación exitosa\n";
    } else {
        echo "❌ Error en eliminación\n";
        exit(1);
    }
    
    echo "🎉 Todas las pruebas pasaron exitosamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Solución de Problemas Comunes

### Error: "apiToken y storeId son requeridos"

- Verifica que ambos valores estén configurados
- Asegúrate de que no estén vacíos o null

### Error de Conexión cURL

```bash
# Verificar conectividad
curl -H "Fastly-Key: TU_TOKEN" https://api.fastly.com/resources/stores/kv/TU_STORE_ID/keys/test
```

### Permisos Insuficientes

- Verifica que el token tenga permisos de KV Store
- Regenera el token si es necesario

## Próximos Pasos

- [Limitaciones Importantes](limitations.md) ⚠️ **LEER PRIMERO**
- [Configuración Avanzada](configuration.md)
- [Guía de Uso](usage.md)
- [Ejemplos Prácticos](examples/basic-usage.md)