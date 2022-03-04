<?php
declare(strict_types=1);
namespace Twikey\Api\Gateway;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Twikey\Api\Twikey;
use Twikey\Api\Exception\TwikeyException;
use const Twikey\Api\TWIKEY_DEBUG;

abstract class BaseGateway
{
    /**
     * @var Twikey
     */
    protected Twikey $twikey;

    public function __construct(Twikey $twikey)
    {
        $this->twikey = $twikey;
    }

    /**
     * @throws TwikeyException
     */
    protected function checkResponse($response, $context = "No context") : ?string
    {
        if ($response) {
            $http_code = $response->getStatusCode();
            $server_output = (string)$response->getBody();
            if ($http_code == 400) { // normal user error
                try {
                    $jsonError = json_decode($server_output);
                    $translatedError = $jsonError->message;
                    error_log(sprintf("%s : Error = %s [%d]", $context, $translatedError, $http_code), 0);
                } catch (Exception $e) {
                    $translatedError = "General error";
                    error_log(sprintf("%s : Error = %s [%d]", $context, $server_output, $http_code), 0);
                }
                throw new TwikeyException($translatedError);
            } else if ($http_code > 400) {
                error_log(sprintf("%s : Error = %s (%s)", $context, $server_output, $this->endpoint), 0);
                throw new TwikeyException("General error");
            }
            if (TWIKEY_DEBUG) {
                error_log(sprintf("Response %s : %s", $context, $server_output), 0);
            }
            return $server_output;
        }
        error_log(sprintf("Response was strange %s : %s", $context, $response), 0);
        return null;
    }
}
