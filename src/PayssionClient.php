<?php

namespace Payssion;

use Exception;

/**
 * Client library for Payssion API.
 */
class PayssionClient
{
    /**
     * @const string
     */
    const VERSION = '1.3.0.160615';

    /**
     * @var string
     */
    protected $api_url;
    protected $api_key = ''; //your api key
    protected $secret_key = ''; //your secret key

    protected static $sig_keys = [
        'payment/create' => [
            'api_key', 'pm_id', 'amount', 'currency', 'order_id', 'secret_key',
        ],
        'payment/details' => [
            'api_key', 'transaction_id', 'order_id', 'secret_key',
        ],
        'refunds' => [
            'api_key', 'transaction_id', 'amount', 'currency', 'secret_key',
        ],
    ];

    /**
     * @var array
     */
    protected $http_errors = [
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        500 => '500 Internal Server Error',
        501 => '501 Not Implemented',
        502 => '502 Bad Gateway',
        503 => '503 Service Unavailable',
        504 => '504 Gateway Timeout',
    ];

    /**
     * @var bool
     */
    protected $is_success = false;

    /**
     * @var array
     */
    protected $allowed_request_methods = [
        'get',
        'put',
        'post',
        'delete',
    ];

    /**
     * @var boolean
     */
    protected $ssl_verify = true;

    /**
     * Constructor
     *
     * @param string $api_key Payssion App api_key
     * @param string $secret_key Payssion App secret_key
     * @param bool $is_livemode false if you use sandbox api_key and true for live mode
     * @throws Exception
     */
    public function __construct($api_key, $secret_key, $is_livemode = true)
    {
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;

        $validate_params = [
            false === extension_loaded('curl') => 'The curl extension must be loaded for using this class!',
            false === extension_loaded('json') => 'The json extension must be loaded for using this class!',
            empty($this->api_key) => 'api_key is not set!',
            empty($this->secret_key) => 'secret_key is not set!',
        ];
        $this->checkForErrors($validate_params);

        $this->setLiveMode($is_livemode);
    }

    /**
     * Set LiveMode
     *
     * @param bool $is_livemode
     */
    public function setLiveMode($is_livemode)
    {
        if ($is_livemode) {
            $this->api_url = 'https://www.payssion.com/api/v1/';
        } else {
            $this->api_url = 'http://sandbox.payssion.com/api/v1/';
        }
    }

    /**
     * Set Api URL
     *
     * @param string $url Api URL
     */
    public function setUrl($url)
    {
        $this->api_url = $url;
    }

    /**
     * Sets SSL verify
     *
     * @param bool $ssl_verify SSL verify
     */
    public function setSSLverify($ssl_verify)
    {
        $this->ssl_verify = $ssl_verify;
    }

    /**
     * Request state getter
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->is_success;
    }

    /**
     * create payment order
     *
     * @param array $params create Params
     * @return array
     * @throws Exception
     */
    public function create(array $params)
    {
        return $this->call(
            'payment/create',
            'post',
            $params
        );
    }

    /**
     * get payment details
     *
     * @param array $params query Params
     * @return array
     * @throws Exception
     */
    public function getDetails(array $params)
    {
        return $this->call(
            'payment/details',
            'post',
            $params
        );
    }

    public function refund(array $params)
    {
        return $this->call(
            'refunds',
            'post',
            $params
        );
    }

    /**
     * Method responsible for preparing, setting state and returning answer from rest server
     *
     * @param string $method
     * @param string $request
     * @param array $params
     * @return array
     * @throws Exception
     */
    protected function call($method, $request, $params)
    {
        $this->is_success = false;

        $validate_params = [
            false === is_string($method) => 'Method name must be string',
            false === $this->checkRequestMethod($request) => 'Not allowed request method type',
            true === empty($params) => 'params is null',
        ];

        $this->checkForErrors($validate_params);

        $params['api_key'] = $this->api_key;
        $params['api_sig'] = $this->getSig($params, self::$sig_keys[$method]);

        $response = $this->pushData($method, $request, $params);

        $response = json_decode($response, true);

        if (isset($response['result_code']) && 200 == $response['result_code']) {
            $this->is_success = true;
        }

        return $response;
    }

    /**
     * Checking error mechanism
     *
     * @param array $validateArray
     * @return string
     * @throws Exception
     */
    protected function getSig(array &$params, array $sig_keys)
    {
        $msg_array = [];
        foreach ($sig_keys as $key) {
            $msg_array[$key] = isset($params[$key]) ? $params[$key] : '';
        }
        $msg_array['secret_key'] = $this->secret_key;

        $msg = implode('|', $msg_array);
        $sig = md5($msg);
        return $sig;
    }

    /**
     * Checking error mechanism
     *
     * @param $validate_params
     * @throws Exception
     */
    protected function checkForErrors(&$validate_params)
    {
        foreach ($validate_params as $key => $error) {
            if ($key) {
                throw new Exception($error, -1);
            }
        }
    }

    /**
     * Check if method is allowed
     *
     * @param string $method_type
     * @return bool
     */
    protected function checkRequestMethod($method_type)
    {
        $request_method = strtolower($method_type);

        if (in_array($request_method, $this->allowed_request_methods)) {
            return true;
        }

        return false;
    }

    /**
     * Method responsible for pushing data to server
     *
     * @param string $method
     * @param string $method_type
     * @param array|string $vars
     * @return string
     * @throws Exception
     */
    protected function pushData($method, $method_type, $vars)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->api_url . $method);
        curl_setopt($ch, CURLOPT_POST, true);

        if (is_array($vars)) $vars = http_build_query($vars, '', '&');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify);

        $response = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (isset($this->http_errors[$code])) {
            throw new Exception('Response Http Error - ' . $this->http_errors[$code], $code);
        }

        $code = curl_errno($ch);
        if (0 < $code) {
            throw new Exception('Unable to connect to ' . $this->api_url . ' Error: ' . "$code :" . curl_error($ch), $code);
        }

        curl_close($ch);

        return $response;
    }

    /**
     * @return array
     */
    protected function getHeaders()
    {
        $langVersion = phpversion();
        $uname = php_uname();
        $ua = [
            'version' => self::VERSION,
            'lang' => 'php',
            'lang_version' => $langVersion,
            'publisher' => 'payssion',
            'uname' => $uname,
        ];
        return [
            'X-Payssion-Client-User-Agent: ' . json_encode($ua),
            "User-Agent: Payssion/php/$langVersion/" . self::VERSION,
            'Content-Type: application/x-www-form-urlencoded',
        ];
    }
}