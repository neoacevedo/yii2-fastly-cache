# Changelog

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere al [Versionado Semántico](https://semver.org/lang/es/).

## [25.10.11] - 2025-10-11

### Agregado
- Implementación inicial de FastlyKvCache para Yii2
- Soporte completo para operaciones básicas de caché (get, set, add, delete, exists)
- Integración con Fastly Key-Value Store API
- Compatibilidad con dependencias de caché de Yii2 (FileDependency, TagDependency, ExpressionDependency)
- Manejo de errores robusto con logging detallado
- Documentación completa en español

#### Documentación
- **Guía de instalación** con verificación de requisitos
- **Guía de configuración** para diferentes entornos
- **Guía de uso** con patrones comunes y ejemplos prácticos
- **Referencia completa de API** con todos los métodos y propiedades
- **Guía de optimización de rendimiento** con estrategias avanzadas
- **Guía de solución de problemas** con diagnósticos y fixes
- **Guía de migración** desde otros sistemas de caché
- **Documentación de limitaciones** de Fastly KV Store
- **Guía de seguridad y privacidad** con validaciones y mejores prácticas

#### Características de Seguridad
- Validador automático de datos sensibles
- Documentación exhaustiva sobre datos prohibidos (PII, credenciales, información financiera)
- Ejemplos seguros vs inseguros en todos los patrones de uso
- Consideraciones de cumplimiento regulatorio (GDPR, HIPAA, PCI DSS)
- Herramientas de sanitización de datos

#### Limitaciones Documentadas
- Tamaño máximo por valor: 25 MB
- Máximo de claves por store: 1,000,000
- Tamaño total del store: 1 GB
- Rate limits: 1,000 escrituras/seg, 50,000 lecturas/seg
- Caracteres válidos en claves: A-Z, a-z, 0-9, -, _, ., ~
- Longitud máxima de clave: 1,024 bytes

### Características Técnicas
- **PHP**: >= 8.2
- **Yii2**: >= 2.0.54
- **Dependencias**: cURL, JSON
- **User-Agent**: Yii2-FastlyKV/1.0
- **Content-Type**: application/octet-stream para operaciones PUT
- **Verificación SSL**: Habilitada por defecto

### Métodos Implementados
- `get($key)` - Obtener valor de caché
- `set($key, $value, $duration, $dependency)` - Establecer valor en caché
- `add($key, $value, $duration, $dependency)` - Agregar solo si no existe
- `delete($key)` - Eliminar valor de caché
- `exists($key)` - Verificar existencia de clave
- `flush()` - No implementado (limitación de Fastly KV)

### Configuración
- `apiToken` (requerido) - Token de API de Fastly
- `storeId` (requerido) - ID del KV Store
- `keyPrefix` (opcional) - Prefijo para todas las claves
- `defaultDuration` (opcional) - Duración por defecto en segundos
- `serializer` (opcional) - Configuración del serializador

### Limitaciones Conocidas
- El método `flush()` no está implementado debido a limitaciones de la API de Fastly KV
- Los TTL (Time To Live) son manejados por Fastly, no por el componente
- Sin soporte nativo para operaciones batch
- Sin historial de versiones de datos almacenados

### Notas de Seguridad
- **CRÍTICO**: No almacenar información personal identificable (PII)
- **CRÍTICO**: No almacenar credenciales, tokens o secretos
- **CRÍTICO**: No almacenar información financiera o médica
- Consultar [Fastly Compliance FAQ](https://www.fastly.com/legal/compliance-law-faq) para más detalles

### Archivos de Documentación
```
docs/
├── README.md              # Índice principal
├── installation.md        # Guía de instalación
├── configuration.md       # Configuración avanzada
├── usage.md              # Guía de uso con ejemplos
├── api-reference.md      # Referencia completa de API
├── performance.md        # Optimización de rendimiento
├── troubleshooting.md    # Solución de problemas
├── migration.md          # Migración desde otros sistemas
├── limitations.md        # Limitaciones de Fastly KV Store
└── security.md           # Seguridad y privacidad
```

### Licencia
- **GPL-3.0+** - Ver LICENSE.md para detalles completos

### Autor
- **Néstor Acevedo** - contacto@neoacevedo.nom.co
- Website: [neoacevedo.nom.co](https://neoacevedo.nom.co)

---

## Formato de Versiones Futuras

### [Unreleased]
#### Agregado
#### Cambiado
#### Obsoleto
#### Eliminado
#### Corregido
#### Seguridad

---

**Nota**: Las fechas están en formato YYYY-MM-DD siguiendo el estándar ISO 8601.