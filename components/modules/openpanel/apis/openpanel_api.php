<?php
/**
 * Lightweight API client for OpenPanel OpenAdmin API.
 *
 * This class purposefully avoids Blesta-specific dependencies so it can be
 * reused in helpers or CLI tooling.
 */
class OpenpanelApi
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $use_ssl;
    private $verify_ssl;
    private $token;

    /**
     * @param string $host         OpenPanel hostname or IP (no protocol)
     * @param string $username     Admin username
     * @param string $password     Admin password
     * @param bool   $use_ssl      Whether to use https (otherwise http)
     * @param int    $port         API port, defaults to 2087
     * @param bool   $verify_ssl   Whether to verify TLS certificate/host
     */
    public function __construct(
        $host,
        $username,
        $password,
        $use_ssl = true,
        $port = 2087,
        $verify_ssl = true
    ) {
        $this->host = $host;
        $this->port = (int)($port ?: 2087);
        $this->username = $username;
        $this->password = $password;
        $this->use_ssl = (bool)$use_ssl;
        $this->verify_ssl = (bool)$verify_ssl;
    }

    /**
     * Returns the base URL without the /api/ suffix.
     */
    public function getBaseUrl()
    {
        $scheme = $this->use_ssl ? 'https' : 'http';

        return $scheme . '://' . $this->host . ':' . $this->port;
    }

    /**
     * Ensures we have a valid token and returns it.
     *
     * @throws Exception on authentication failure
     */
    public function token()
    {
        if ($this->token) {
            return $this->token;
        }

        $auth_url = $this->buildUrl('');
        $response = $this->send(
            $auth_url,
            'POST',
            ['Content-Type: application/json'],
            json_encode(['username' => $this->username, 'password' => $this->password])
        );

        if (!isset($response['body']['access_token']) || empty($response['body']['access_token'])) {
            $message = isset($response['body']['message'])
                ? $response['body']['message']
                : 'Authentication failed';

            throw new Exception($message);
        }

        $this->token = $response['body']['access_token'];

        return $this->token;
    }

    /**
     * Performs an authenticated request.
     *
     * @param string     $method HTTP method (GET, POST, PUT, PATCH, DELETE, CONNECT)
     * @param string     $path   Path relative to /api/
     * @param array|null $data   Optional payload
     *
     * @return array {status:int, body:mixed, raw:string}
     * @throws Exception on transport failure or authentication error
     */
    public function call($method, $path, array $data = null)
    {
        $url = $this->buildUrl($path);
        $token = $this->token();

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        $payload = $data === null ? null : json_encode($data);

        return $this->send($url, strtoupper($method), $headers, $payload);
    }

    /**
     * Normalises a path and prepends /api/.
     */
    private function buildUrl($path)
    {
        $path = ltrim($path, '/');

        return $this->getBaseUrl() . '/api/' . $path;
    }

    /**
     * Executes a cURL request and returns status/body/raw.
     *
     * @throws Exception on transport errors
     */
    private function send($url, $method, array $headers, $payload = null)
    {
        $curl = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $this->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $this->verify_ssl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_POSTREDIR => defined('CURL_REDIR_POST_ALL') ? CURL_REDIR_POST_ALL : 7
        ];

        switch (strtoupper($method)) {
            case 'GET':
                $options[CURLOPT_HTTPGET] = true;
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($payload !== null) {
                    $options[CURLOPT_POSTFIELDS] = $payload;
                }
                break;
            default:
                $options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
                if ($payload !== null) {
                    $options[CURLOPT_POSTFIELDS] = $payload;
                }
                break;
        }

        curl_setopt_array($curl, $options);

        $raw = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception('cURL error: ' . $error);
        }

        curl_close($curl);

        $body = json_decode($raw, true);
        if ($body === null && (json_last_error() !== JSON_ERROR_NONE || $raw === '')) {
            $body = $raw;
        }

        return [
            'status' => $status,
            'body' => $body,
            'raw' => $raw
        ];
    }
}
