# Seguridad y Privacidad

Guía de seguridad para el uso responsable de Yii2 Fastly Cache.

## ⚠️ Advertencias Críticas de Seguridad

### Datos Prohibidos en Fastly KV Store

**NUNCA almacenes los siguientes tipos de datos:**

- **Información Personal Identificable (PII)**
  - Nombres completos, direcciones, números de teléfono
  - Números de identificación (SSN, DNI, pasaporte)
  - Direcciones de email personales
  - Fechas de nacimiento

- **Credenciales y Secretos**
  - Contraseñas o hashes de contraseñas
  - Tokens de autenticación o API keys
  - Claves de cifrado o certificados
  - Tokens de sesión sensibles

- **Información Financiera**
  - Números de tarjetas de crédito
  - Información bancaria
  - Datos de pagos

- **Datos Médicos o Legales**
  - Información médica (HIPAA)
  - Datos legales confidenciales
  - Información regulada por GDPR

### Limitaciones de Fastly KV Store

- **Sin historial de versiones**: Fastly no mantiene versiones anteriores de los datos
- **Sin garantías de eliminación**: Los datos pueden persistir en cache edge
- **Acceso global**: Los datos están disponibles en todos los edge servers de Fastly

## Validador de Seguridad

### Implementación del Validador

```php
class SecurityValidator
{
    // Campos sensibles que nunca deben ser cacheados
    private static $sensitiveFields = [
        // Credenciales
        'password', 'password_hash', 'password_reset_token',
        'auth_key', 'access_token', 'refresh_token', 'api_key',
        'secret', 'private_key', 'certificate',
        
        // Información personal
        'email', 'phone', 'mobile', 'address', 'full_address',
        'ssn', 'social_security', 'passport', 'driver_license',
        'birth_date', 'date_of_birth', 'age',
        
        // Información financiera
        'credit_card', 'card_number', 'cvv', 'bank_account',
        'routing_number', 'payment_method',
        
        // Información técnica sensible
        'ip_address', 'user_agent', 'session_id',
        'csrf_token', 'verification_code', 'otp',
        
        // Información médica/legal
        'medical_record', 'diagnosis', 'prescription',
        'legal_document', 'contract'
    ];
    
    // Patrones de datos sensibles
    private static $sensitivePatterns = [
        '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', // Tarjetas de crédito
        '/\b\d{3}-\d{2}-\d{4}\b/',                      // SSN formato XXX-XX-XXXX
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',                // IP Address
        '/\b[A-Fa-f0-9]{32}\b/',                        // MD5 Hash
        '/\b[A-Fa-f0-9]{40}\b/',                        // SHA1 Hash
    ];
    
    public static function validateData($data, $key = '')
    {
        $violations = [];
        
        // Validar campos sensibles
        $fieldViolations = self::checkSensitiveFields($data, $key);
        $violations = array_merge($violations, $fieldViolations);
        
        // Validar patrones sensibles
        $patternViolations = self::checkSensitivePatterns($data, $key);
        $violations = array_merge($violations, $patternViolations);
        
        return $violations;
    }
    
    private static function checkSensitiveFields($data, $prefix = '')
    {
        $violations = [];
        
        if (is_array($data)) {
            foreach ($data as $field => $value) {
                $fullKey = $prefix ? "{$prefix}.{$field}" : $field;
                
                if (in_array(strtolower($field), self::$sensitiveFields)) {
                    $violations[] = "Sensitive field detected: {$fullKey}";
                }
                
                if (is_array($value) || is_object($value)) {
                    $nestedViolations = self::checkSensitiveFields($value, $fullKey);
                    $violations = array_merge($violations, $nestedViolations);
                }
            }
        } elseif (is_object($data)) {
            foreach (get_object_vars($data) as $field => $value) {
                $fullKey = $prefix ? "{$prefix}.{$field}" : $field;
                
                if (in_array(strtolower($field), self::$sensitiveFields)) {
                    $violations[] = "Sensitive field detected: {$fullKey}";
                }
                
                if (is_array($value) || is_object($value)) {
                    $nestedViolations = self::checkSensitiveFields($value, $fullKey);
                    $violations = array_merge($violations, $nestedViolations);
                }
            }
        }
        
        return $violations;
    }
    
    private static function checkSensitivePatterns($data, $key = '')
    {
        $violations = [];
        $serialized = serialize($data);
        
        foreach (self::$sensitivePatterns as $pattern) {
            if (preg_match($pattern, $serialized)) {
                $violations[] = "Sensitive data pattern detected in: {$key}";
            }
        }
        
        return $violations;
    }
    
    public static function sanitizeData($data)
    {
        if (is_array($data)) {
            foreach (self::$sensitiveFields as $field) {
                unset($data[$field]);
            }
            
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = self::sanitizeData($value);
                }
            }
        } elseif (is_object($data)) {
            foreach (self::$sensitiveFields as $field) {
                if (property_exists($data, $field)) {
                    unset($data->$field);
                }
            }
            
            foreach (get_object_vars($data) as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data->$key = self::sanitizeData($value);
                }
            }
        }
        
        return $data;
    }
}
```

## Cache Seguro

### Implementación de Cache con Validación

```php
class SecureFastlyCache extends FastlyKvCache
{
    public $enforceSecurityValidation = true;
    public $logSecurityViolations = true;
    
    public function set($key, $value, $duration = 0, $dependency = null)
    {
        if ($this->enforceSecurityValidation) {
            $violations = SecurityValidator::validateData($value, $key);
            
            if (!empty($violations)) {
                if ($this->logSecurityViolations) {
                    foreach ($violations as $violation) {
                        Yii::error("SECURITY VIOLATION: {$violation}", 'fastly-security');
                    }
                }
                
                throw new SecurityException(
                    "Cannot cache sensitive data. Violations: " . implode(', ', $violations)
                );
            }
        }
        
        return parent::set($key, $value, $duration, $dependency);
    }
    
    public function setSafe($key, $value, $duration = 0, $dependency = null)
    {
        // Sanitizar automáticamente los datos
        $sanitizedValue = SecurityValidator::sanitizeData($value);
        
        // Validar después de sanitizar
        $violations = SecurityValidator::validateData($sanitizedValue, $key);
        
        if (!empty($violations)) {
            Yii::warning("Data still contains sensitive information after sanitization: {$key}", 'fastly-security');
            return false;
        }
        
        return parent::set($key, $sanitizedValue, $duration, $dependency);
    }
}
```

## Datos Seguros para Cachear

### ✅ Datos Recomendados

```php
class SafeCacheExamples
{
    public function cachePublicProductData()
    {
        $products = Product::find()
            ->select(['id', 'name', 'description', 'price', 'category_id'])
            ->where(['status' => 'active'])
            ->all();
        
        Yii::$app->cache->set('public_products', $products, 3600);
    }
    
    public function cachePublicArticles()
    {
        $articles = Article::find()
            ->select(['id', 'title', 'summary', 'published_at'])
            ->where(['status' => 'published'])
            ->all();
        
        Yii::$app->cache->set('published_articles', $articles, 1800);
    }
    
    public function cacheAppConfiguration()
    {
        $config = [
            'app_name' => Yii::$app->name,
            'version' => '1.0.0',
            'features' => ['feature1', 'feature2'],
            'public_settings' => Setting::getPublicSettings(),
        ];
        
        Yii::$app->cache->set('app_config', $config, 86400);
    }
    
    public function cachePublicStatistics()
    {
        $stats = [
            'total_products' => Product::find()->count(),
            'total_articles' => Article::find()->where(['status' => 'published'])->count(),
            'categories' => Category::find()->select(['id', 'name'])->all(),
        ];
        
        Yii::$app->cache->set('public_stats', $stats, 3600);
    }
}
```

### ❌ Datos Peligrosos (NUNCA Cachear)

```php
class DangerousExamples
{
    // ❌ NUNCA hacer esto
    public function dangerousUserCache()
    {
        $user = User::findOne($id);
        
        // PELIGROSO: Contiene información personal
        Yii::$app->cache->set("user_{$id}", $user, 3600);
    }
    
    // ❌ NUNCA hacer esto
    public function dangerousSessionCache()
    {
        $sessionData = [
            'user_id' => $userId,
            'email' => $userEmail,        // PII
            'auth_token' => $token,       // Credencial
            'ip_address' => $ip,          // Información técnica sensible
        ];
        
        Yii::$app->cache->set("session_{$sessionId}", $sessionData, 1800);
    }
    
    // ❌ NUNCA hacer esto
    public function dangerousPaymentCache()
    {
        $paymentData = [
            'card_number' => $cardNumber,     // Información financiera
            'cvv' => $cvv,                    // Información financiera
            'user_email' => $email,           // PII
        ];
        
        Yii::$app->cache->set("payment_{$id}", $paymentData, 300);
    }
}
```

## Configuración de Seguridad

### Configuración Recomendada

```php
'components' => [
    'cache' => [
        'class' => 'neoacevedo\yii2\fastly\SecureFastlyCache',
        'apiToken' => $_ENV['FASTLY_API_TOKEN'],
        'storeId' => $_ENV['FASTLY_STORE_ID'],
        'keyPrefix' => 'public_',  // Indicar que son datos públicos
        'enforceSecurityValidation' => true,
        'logSecurityViolations' => true,
    ],
],

'log' => [
    'targets' => [
        [
            'class' => 'yii\log\FileTarget',
            'levels' => ['error', 'warning'],
            'categories' => ['fastly-security'],
            'logFile' => '@runtime/logs/fastly-security.log',
            'maxFileSize' => 10240, // 10MB
            'maxLogFiles' => 5,
        ],
    ],
],
```

## Monitoreo de Seguridad

### Alertas de Seguridad

```php
class SecurityMonitor
{
    public static function checkForViolations()
    {
        $logFile = Yii::getAlias('@runtime/logs/fastly-security.log');
        
        if (!file_exists($logFile)) {
            return;
        }
        
        $recentViolations = self::getRecentViolations($logFile);
        
        if (count($recentViolations) > 10) { // Más de 10 violaciones en la última hora
            self::sendSecurityAlert($recentViolations);
        }
    }
    
    private static function sendSecurityAlert($violations)
    {
        $message = "SECURITY ALERT: Multiple attempts to cache sensitive data detected.\n";
        $message .= "Violations in the last hour: " . count($violations) . "\n";
        $message .= "Recent violations:\n" . implode("\n", array_slice($violations, 0, 5));
        
        // Enviar alerta por email, Slack, etc.
        Yii::error($message, 'security-alert');
    }
}
```

### Auditoría de Datos

```php
class CacheAudit
{
    public static function auditCacheContents()
    {
        // Script para auditar contenido existente en caché
        $report = [
            'total_keys' => 0,
            'potential_violations' => [],
            'recommendations' => [],
        ];
        
        // Implementar lógica de auditoría según las capacidades de Fastly API
        
        return $report;
    }
}
```

## Mejores Prácticas de Seguridad

### 1. Principio de Mínimos Privilegios

```php
// Solo cachear los campos mínimos necesarios
$publicUserData = [
    'id' => $user->id,
    'display_name' => $user->display_name,
    'avatar_url' => $user->avatar_url,
    // NO incluir email, teléfono, etc.
];
```

### 2. Prefijos Descriptivos

```php
// Usar prefijos que indiquen el tipo de datos
$keys = [
    'public_user_' . $id,      // Datos públicos de usuario
    'anon_stats_' . $date,     // Estadísticas anónimas
    'config_public_' . $key,   // Configuración pública
];
```

### 3. TTL Cortos para Datos Sensibles

```php
// Si absolutamente necesitas cachear datos semi-sensibles,
// usa TTL muy cortos
Yii::$app->cache->set('temp_data', $data, 300); // 5 minutos máximo
```

### 4. Logging y Monitoreo

```php
// Registrar todas las operaciones de caché
Yii::$app->cache->on(Cache::EVENT_AFTER_SET, function($event) {
    Yii::info("Cache set: {$event->key}", 'cache-audit');
});
```

## Cumplimiento Regulatorio

### GDPR Compliance

- **No cachear datos personales** de ciudadanos de la UE
- **Implementar "derecho al olvido"** - capacidad de eliminar datos
- **Documentar el flujo de datos** hacia Fastly

### HIPAA Compliance

- **Prohibido cachear información médica** en Fastly KV Store
- **Usar solo para datos públicos** no relacionados con salud

### PCI DSS Compliance

- **Nunca cachear datos de tarjetas** de crédito
- **No almacenar información de pagos** en edge cache

## Recursos Adicionales

- [Fastly Compliance and Law FAQ](https://www.fastly.com/legal/compliance-law-faq)
- [Fastly Privacy Policy](https://www.fastly.com/privacy)
- [GDPR Compliance Guide](https://gdpr.eu/)
- [OWASP Caching Security](https://owasp.org/www-community/controls/Caching)