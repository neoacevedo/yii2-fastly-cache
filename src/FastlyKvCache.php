<?php

/**
 * @copyright Copyright (c) 2025 neoacevedo
 * @subpackage yii2-fastly-cache
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace neoacevedo\yii2\fastly;

use yii\caching\Cache;
use yii\base\InvalidConfigException;

/**
 * FastlyKvCache implements a cache application component based on Fastly Key-Value Store (KV).
 *
 * Para usar este componente, debe configurar los siguientes parámetros en la configuración de su aplicación:
 *
 * ```php
 * 'components' => [
 *     'cache' => [
 *         'class' => 'neoacevedo\yii2\fastly\FastlyKvCache',
 *         'apiToken' => 'TU_FASTLY_API_TOKEN',
 *         'storeId' => 'TU_FASTLY_STORE_ID',
 *     ],
 * ],
 * ```
 * 
 * Las otras opciones como `keyPrefix`, `serializer` y `defaultCacheDuration` se heredan de la clase Cache.
 *
 * @see https://developer.fastly.com/reference/api/key-value-store/
 * @see https://developer.fastly.com/learning/concepts/key-value-store/
 */
class FastlyKvCache extends Cache
{
    /** @var string API Token  */
    public $apiToken;
    /** @var string ID del almacenamiento */
    public $storeId;

    /** @var string URL base del API de Fastly */
    protected $baseUrl = 'https://api.fastly.com';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if (!$this->apiToken || !$this->storeId) {
            throw new InvalidConfigException('apiToken y storeId son requeridos');
        }
    }

    /**
     * Obtiene el valor de la clave.
     * @param string $key la clave a obtener
     * @return mixed el valor de la clave o false si no existe
     */
    protected function getValue($key)
    {
        $url = "{$this->baseUrl}/resources/stores/kv/{$this->storeId}/keys/{$key}";
        $response = $this->makeRequest('GET', $url);
        return $response !== false ? $response : false;
    }

    /**
     * Establece el valor de la clave.
     * @param string $key la clave a establecer
     * @param mixed $value el valor a establecer
     * @param int $duration la duración de la clave en segundos
     * @return bool true si la operación fue exitosa, false en caso contrario
     */
    protected function setValue($key, $value, $duration)
    {
        $url = "{$this->baseUrl}/resources/stores/kv/{$this->storeId}/keys/{$key}";
        $response = $this->makeRequest('PUT', $url, $value) !== false;
        return $response;
    }

    /**
     * Agrega un valor a la clave.
     * @param string $key la clave a agregar
     * @param mixed $value el valor a agregar
     * @param int $duration la duración de la clave en segundos
     * @return bool true si la operación fue exitosa, false en caso contrario
     */
    protected function addValue($key, $value, $duration)
    {
        if ($this->getValue($key) !== false) {
            return false;
        }
        return $this->setValue($key, $value, $duration);
    }

    /**
     * Elimina el valor de la clave.
     * @param string $key la clave a eliminar
     * @return bool true si la operación fue exitosa, false en caso contrario
     */
    protected function deleteValue($key)
    {
        $url = "{$this->baseUrl}/resources/stores/kv/{$this->storeId}/keys/{$key}";
        return $this->makeRequest('DELETE', $url) !== false;
    }

    /**
     * Limpia todos los valores en el almacenamiento.
     * @todo Validar si este método se usa o se elimina.
     */
    protected function flushValues()
    {
        return false;
    }

    /**
     * Realiza una solicitud HTTP a la API de Fastly.
     * @param string $method el método HTTP a utilizar
     * @param string $url la URL a la que enviar la solicitud
     * @param mixed $data los datos a enviar en el cuerpo de la solicitud
     * @return mixed la respuesta de la API o false si hubo un error
     */
    private function makeRequest($method, $url, $data = null)
    {
        $ch = curl_init();

        $token = $this->apiToken;

        $headers = [
            'Fastly-Key: ' . $token,
            'Accept: application/json'
        ];

        // Solo agregar Content-Type para PUT/POST con datos
        if ($data !== null && $method === 'PUT') {
            $headers[] = 'Content-Type: application/octet-stream';
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Yii2-FastlyKV/1.0'
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log detallado para debug
        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 204) {
            \Yii::error("Fastly KV Error: {$response}", 'fastly-cache');
        }

        return $httpCode >= 200 && $httpCode < 300 ? $response : false;
    }
}
