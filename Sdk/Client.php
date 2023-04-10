<?php

namespace P3\PaymentGateway\Sdk;

/**
 * Class Client
 */
class Client implements ClientInterface
{
    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var array
     */
    private $serverData;

    public function __construct(
        string $url,
        array $serverData
    )
    {
        $this->endpoint = $url;
        $this->serverData = $serverData;
    }

    /**
     * @param array $request
     * @return array
     */
    public function post(array $request)
    {
        if (function_exists('curl_init')) {
            $opts = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($request, '', '&'),
                CURLOPT_HEADER => false,
                CURLOPT_FAILONERROR => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => $this->serverData['HTTP_USER_AGENT'] ?? '',
            ];

            if (($ch = curl_init($this->endpoint)) === false) {
                throw new \RuntimeException('Failed to initialise communications with Payment Gateway');
            }

            if (curl_setopt_array($ch, $opts) === false || ($data = curl_exec($ch)) === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException('Failed to communicate with Payment Gateway: ' . $err);
            }

        } elseif (ini_get('allow_url_fopen')) {

            $opts = [
                'http' => [
                    'method' => 'POST',
                    'user_agent' => $this->serverData['HTTP_USER_AGENT'] ?? '',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query($request, '', '&'),
                    'timeout' => 5,
                ]
            ];

            $context = stream_context_create($opts);

            if (($data = file_get_contents($this->endpoint, false, $context)) === false) {
                throw new \RuntimeException('Failed to send request to Payment Gateway');
            }

        } else {
            throw new \RuntimeException('No means of communicate with Payment Gateway, please enable CURL or HTTP Stream Wrappers');
        }

        if (!$data) {
            throw new \RuntimeException('No response from Payment Gateway');
        }

        $response = null;
        parse_str($data, $response);

        return $response;
    }
}
