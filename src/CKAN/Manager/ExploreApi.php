<?php
/**
 * Project: ckan-php-manager
 * @author Alex Perfilov
 * @date   7/1/14
 */

namespace CKAN\Manager;

/**
 * Class ExploreApi
 * @package CKAN\Manager
 */
class ExploreApi
{

    /**
     * @var string
     */
    private $api_url = '';

    /**
     * cURL handler
     * @var resource
     */
    private $ch;

    /**
     * cURL headers
     * @var array
     */
    private $ch_headers;

    /**
     * @param      $api_url
     */
    public function __construct($api_url)
    {
        $this->api_url = $api_url;

        // Create cURL object.
        $this->ch = curl_init();
        // Follow any Location: headers that the server sends.
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        // However, don't follow more than five Location: headers.
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 5);
        // Automatically set the Referrer: field in requests
        // following a Location: redirect.
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
        // Return the transfer as a string instead of dumping to screen.
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        // If it takes more than 5 minutes => fail
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 60 * 5);
        // We don't want the header (use curl_getinfo())
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        // Track the handle's request string
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
        // Attempt to retrieve the modification date of the remote document.
        curl_setopt($this->ch, CURLOPT_FILETIME, true);
        // Initialize cURL headers
        $this->set_headers();
    }

    /**
     * Sets the custom cURL headers.
     * @access    private
     * @return    void
     * @since     Version 0.1.0
     */
    private function set_headers()
    {
        $date = new \DateTime(null, new \DateTimeZone('UTC'));
        $this->ch_headers = [
            'Date: ' . $date->format('D, d M Y H:i:s') . ' GMT', // RFC 1123
            'Accept: application/json',
            'Accept-Charset: utf-8',
            'Accept-Encoding: gzip'
        ];
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function get_json($id)
    {
        return $this->make_request(
            'GET',
            'views/' . $id . '.json'
        );
    }

    /**
     * @param string $method // HTTP method (GET, POST)
     * @param string $uri // URI fragment to CKAN resource
     * @param string $data // Optional. String in JSON-format that will be in request body
     *
     * @return mixed    // If success, either an array or object. Otherwise FALSE.
     * @throws \Exception
     */
    private function make_request($method, $uri, $data = null)
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'])) {
            throw new \Exception('Method ' . $method . ' is not supported');
        }
        // Set cURL URI.
        curl_setopt($this->ch, CURLOPT_URL, $this->api_url . $uri);
        if ($method === 'POST') {
            if ($data) {
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, urlencode($data));
            } else {
                $method = 'GET';
            }
        }

        // Set cURL method.
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);

        // Set headers.
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->ch_headers);
        // Execute request and get response headers.
        $response = curl_exec($this->ch);
        $info = curl_getinfo($this->ch);
        // Check HTTP response code
        if ($info['http_code'] !== 200) {
            switch ($info['http_code']) {
                case 0:
                    var_dump($info);
                    break;
                case 404:
                    throw new \Exception($data);
                    break;
                default:
                    throw new \Exception(
                        $info['http_code'] . ': ' . PHP_EOL . $data . PHP_EOL
                    );
            }
        }

        return $response;
    }

    /**
     * Since it's possible to leave cURL open, this is the last chance to close it
     */
    public function __destruct()
    {
        if ($this->ch) {
            curl_close($this->ch);
            unset($this->ch);
        }
    }
}
