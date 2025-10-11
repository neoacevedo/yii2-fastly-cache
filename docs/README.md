# Documentación de Yii2 Fastly Cache

Documentación completa para el componente de caché Yii2 para Fastly KV Store.

## 📚 Índice de Documentación

### Primeros Pasos

1. **[Instalación](installation.md)** - Guía paso a paso para instalar y configurar
2. **[Limitaciones](limitations.md)** ⚠️ **IMPORTANTE** - Límites de Fastly KV Store que debes conocer
3. **[Configuración](configuration.md)** - Opciones de configuración detalladas
4. **[Guía de Uso](usage.md)** - Ejemplos prácticos y patrones de uso

### Documentación Técnica

5. **[Seguridad y Privacidad](security.md)** ⚠️ **CRÍTICO** - Guía de seguridad y datos prohibidos
6. **[Referencia de API](api-reference.md)** - Documentación completa de métodos y propiedades
7. **[Optimización de Rendimiento](performance.md)** - Estrategias para maximizar el rendimiento
8. **[Solución de Problemas](troubleshooting.md)** - Diagnóstico y resolución de problemas comunes

### Migración y Mantenimiento

9. **[Guía de Migración](migration.md)** - Migrar desde otros sistemas de caché

## 🚀 Inicio Rápido

### 1. Instalación

```bash
composer require neoacevedo/yii2-fastly-cache
```

### 2. Configuración Básica

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID'],
    ],
],
```

### 3. Uso Básico

```php
// Guardar
Yii::$app->cache->set('mi_clave', 'mi_valor', 3600);

// Obtener
$valor = Yii::$app->cache->get('mi_clave');

// Eliminar
Yii::$app->cache->delete('mi_clave');
```

## ⚠️ Limitaciones Importantes

**Antes de usar este componente, es CRÍTICO que leas la [documentación de limitaciones](limitations.md):**

- **Tamaño máximo por valor**: 25 MB
- **Máximo de claves por store**: 1,000,000
- **Tamaño total del store**: 1 GB
- **Rate limits**: 1,000 escrituras/seg, 50,000 lecturas/seg

## 📖 Guías por Caso de Uso

### Para Desarrolladores Nuevos

1. [Instalación](installation.md) → [Limitaciones](limitations.md) → [Configuración](configuration.md) → [Uso](usage.md)

### Para Migración

1. [Limitaciones](limitations.md) → [Migración](migration.md) → [Configuración](configuration.md)

### Para Optimización

1. [Rendimiento](performance.md) → [Limitaciones](limitations.md) → [API Reference](api-reference.md)

### Para Troubleshooting

1. [Solución de Problemas](troubleshooting.md) → [Limitaciones](limitations.md) → [Configuración](configuration.md)

## 🔧 Herramientas y Scripts

### Scripts de Utilidad

```bash
# Verificar instalación
php scripts/verify-cache.php

# Diagnóstico completo
php scripts/diagnose-cache.php

# Migración desde otros sistemas
php scripts/migrate-from-redis.php
php scripts/migrate-from-filecache.php

# Validación post-migración
php scripts/validate-migration.php
```

### Comandos de Consola

```bash
# Calentar caché
./yii cache/warmup

# Limpiar caché
./yii cache/flush

# Estadísticas de uso
./yii cache/stats
```

## 📊 Monitoreo y Métricas

### Métricas Clave a Monitorear

- **Hit Rate**: Porcentaje de aciertos de caché
- **Latencia**: Tiempo de respuesta de operaciones
- **Uso de Storage**: Porcentaje del límite de 1GB usado
- **Número de Claves**: Porcentaje del límite de 1M claves usado
- **Rate Limiting**: Operaciones rechazadas por límites

### Alertas Recomendadas

- Uso de storage > 90%
- Número de claves > 90%
- Hit rate < 80%
- Latencia promedio > 200ms
- Errores de rate limiting

## 🛠️ Desarrollo y Contribución

### Estructura del Proyecto

```
yii2-fastly-cache/
├── src/
│   └── FastlyKvCache.php
├── docs/
│   ├── installation.md
│   ├── limitations.md
│   ├── configuration.md
│   ├── usage.md
│   ├── api-reference.md
│   ├── performance.md
│   ├── troubleshooting.md
│   └── migration.md
├── tests/
├── scripts/
└── examples/
```

### Contribuir

1. Fork el repositorio
2. Crea una rama para tu feature
3. Implementa los cambios
4. Agrega tests
5. Actualiza documentación
6. Envía Pull Request

## 📞 Soporte

### Recursos de Ayuda

- **Documentación**: Esta documentación completa
- **Issues**: [GitHub Issues](https://github.com/neoacevedo/yii2-fastly-cache/issues)
- **Email**: contacto@neoacevedo.nom.co
- **Fastly Docs**: [Fastly KV Store Documentation](https://developer.fastly.com/reference/api/key-value-store/)

### Información para Reportes

Al reportar problemas, incluye:

- Versión de PHP
- Versión de Yii2
- Versión del componente
- Configuración (sin credenciales)
- Logs de error
- Pasos para reproducir

## 📄 Licencia

Este proyecto está licenciado bajo GPL-3.0+. Ver [LICENSE.md](../LICENSE.md) para más detalles.

---

**⭐ Si esta documentación te es útil, considera dar una estrella al proyecto en GitHub!**