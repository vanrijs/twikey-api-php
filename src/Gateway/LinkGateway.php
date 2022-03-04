<?php
declare(strict_types=1);
namespace Twikey\Api\Gateway;

use Psr\Http\Client\ClientExceptionInterface;
use Twikey\Api\Callback\PaylinkCallback;
use Twikey\Api\Exception\TwikeyException;

class LinkGateway extends BaseGateway
{
    /**
     * @throws TwikeyException
     */
    public function create($data, ?array $optionalHeaders = [])
    {
        $response = $this->twikey->request('POST', "/creditor/payment/link", ['form_params' => $data, 'headers' => $optionalHeaders]);
        $server_output = $this->checkResponse($response, "Creating a new paymentlink!");
        return json_decode($server_output);
    }

    /**
     * Note this is rate limited
     * @throws TwikeyException
     * @throws ClientExceptionInterface
     */
    public function get($linkid, $ref)
    {
        if (empty($ref)) {
            $item = "id=" . $linkid;
        } else {
            $item = "ref=" . urlencode($ref);
        }
        $response = $this->twikey->request('GET', sprintf("/creditor/payment/link?%s", $item), []);
        $server_output = $this->checkResponse($response, "Verifying a paymentlink ");
        return json_decode($server_output);
    }

    /**
     * Read until empty
     * @throws TwikeyException
     * @throws ClientExceptionInterface
     */
    public function feed(PaylinkCallback $callback)
    {
        $count = 0;
        do {
            $response = $this->twikey->request('GET', "/creditor/payment/link/feed", [], $lang);
            $server_output = $this->checkResponse($response, "Retrieving paymentlink feed!");
            $links = json_decode($server_output);
            foreach ($links as $pl){
                $count++;
                $callback->handle($pl);
            }
        }
        while(count($links) > 0);
        return $count;
    }
}
