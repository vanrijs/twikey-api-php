<?php
declare(strict_types=1);

namespace Twikey\Api;

use Exception;
use phpDocumentor\Reflection\Types\This;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Twikey\Api\Gateway\InvoiceGateway;
use Twikey\Api\Gateway\LinkGateway;
use Twikey\Api\Gateway\DocumentGateway;
use Twikey\Api\Gateway\RefundGateway;
use Twikey\Api\Gateway\TransactionGateway;
use Twikey\Api\Exception\TwikeyException;
use Twikey\Api\Gateway\CollectionGateway;

const TWIKEY_DEBUG = false;

class Twikey
{
    const VERSION = '3.0.1';

    private string $lang = 'en';
    private string $endpoint = 'https://api.twikey.com';
    private string $salt = 'own';
    private string $apiKey;
    private ?string $privKey;
    private int $lastLogin = 0;
    private ?string $apitoken = null;

    public DocumentGateway $document;
    public TransactionGateway $transaction;
    public LinkGateway $link;
    public InvoiceGateway $invoice;
    public RefundGateway $refund;
    public CollectionGateway $collection;

    /**
     * @var ClientInterface
     */
    private ClientInterface $httpClient;

    public function __construct(ClientInterface $httpClient, string $apikey, bool $testMode = false, string $privKey = "")
    {
        $this->httpClient = $httpClient;
        $this->endpoint = "https://api.twikey.com";
        if ($testMode) {
            $this->endpoint = "https://api.beta.twikey.com";
        }
        $this->apiKey = trim($apikey);
        $this->privKey = trim($privKey);
        $this->document = new DocumentGateway($this);
        $this->transaction = new TransactionGateway($this);
        $this->link = new LinkGateway($this);
        $this->invoice = new InvoiceGateway($this);
        $this->refund = new RefundGateway($this);
        $this->collection = new CollectionGateway($this);
    }

    /**
     * @throws TwikeyException
     */
    public static function validateSignature(string $website_key, string $documentNumber, string $status, string $token, string $signature): bool
    {
        $payload = sprintf("%s/%s", $documentNumber, $status);
        if ($token != "") {
            $payload = sprintf("%s/%s/%s", $documentNumber, $status, $token);
        }
        $calculated = strtoupper(hash_hmac('sha256', $payload, $website_key));
        if (!hash_equals($calculated, $signature)) {
            error_log("Invalid signature : expected=" . $calculated . ' was=' . $signature, 0);
            throw new TwikeyException('Invalid signature');
        }
        return true;
    }

    /**
     * @param $queryString $_SERVER['QUERY_STRING']
     * @param $signatureHeader $_SERVER['HTTP_X_SIGNATURE']
     */
    public static function validateWebhook(string $apikey, string $queryString, string $signatureHeader): bool
    {
        $calculated = strtoupper(hash_hmac('sha256', urldecode($queryString), $apikey));

//        error_log("Calculated: " . $calculated);
//        error_log("Given: " . $signatureHeader);
//        error_log("Message: " . $queryString);
//        error_log("Same: " . ($calculated == $signatureHeader));

        return hash_equals($calculated, $signatureHeader);
    }

    public function refreshTokenIfRequired()
    {
        if (time() - $this->lastLogin < 23) {
            return $this->apitoken;
        }

        $options['headers'] = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            "User-Agent" => "twikey-php/v" . Twikey::VERSION,
            "Accept-Language" => $this->lang
        ];
        $options['form_params'] = [
            "apiToken" => $this->apiKey
        ];
        if ($this->privKey != "") {
            $otp = $this->calcOtp();
            $options['form_params']["otp"] = $otp;
        }

        $response = $this->httpClient->request('POST', $this->endpoint . '/creditor', $options);
        if (count($response->getHeader("Authorization")) == 1) {
            $this->apitoken = $response->getHeader("Authorization")[0];
            $this->lastLogin = time();
            return $this->apitoken;
        } else if (count($response->getHeader("Apierror")) == 1) {
            $this->apitoken = "";
            $this->lastLogin = 0;
            throw new TwikeyException($response->getHeader("Apierror")[0]);
        } else {
            throw new TwikeyException("General Twikey exception : " . $response->getReasonPhrase());
        }
    }

    public function logout()
    {
        $options['headers'] = [
            "User-Agent" => "twikey-php/v" . Twikey::VERSION,
            "Accept-Language" => $this->lang,
            "Authorization" => $this->apitoken
        ];
        $response = $this->httpClient->request('GET', $this->endpoint . '/creditor', $options);
        if ($response->getStatusCode() >= 400) {
            throw new TwikeyException($response->getReasonPhrase());
        }
    }

    private function calcOtp () {
        $secret = $this->salt . hex2bin($this->privKey);
        $len=8;
        $ctr = (int)floor(time() / 30);

        $binctr = pack ('NNC*', $ctr>>32, $ctr & 0xFFFFFFFF);
        $hash = hash_hmac ("sha256", $binctr, $secret);
        // This is where hashing stops and truncation begins
        $ofs = 2*hexdec (substr ($hash, 39, 1));
        $int = hexdec (substr ($hash, $ofs, 8)) & 0x7FFFFFFF;
        $pin = substr ("".$int, -$len);
        $pin = str_pad ($pin, $len, "0", STR_PAD_LEFT);
        return $pin;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function request(string $method, string $uri = '', array $options = []): ResponseInterface
    {
        $fulluri = sprintf("%s/%s", $this->endpoint, $uri);
        $headers = $options['headers'] ?? [];
        $headers = array_merge($headers, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            "User-Agent" => "twikey-php/v" . Twikey::VERSION,
            "Accept-Language" => $this->lang,
            "Authorization" => $this->refreshTokenIfRequired()
        ]);
        $options['headers'] = $headers;
        return $this->httpClient->request($method, $fulluri, $options);
    }

}
