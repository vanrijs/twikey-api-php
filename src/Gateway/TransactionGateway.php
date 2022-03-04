<?php
declare(strict_types=1);
namespace Twikey\Api\Gateway;

use Twikey\Api\Callback\TransactionCallback;
use Twikey\Api\Exception\TwikeyException;

class TransactionGateway extends BaseGateway
{
    /**
     * @param $data
     * @return array|mixed|object
     * @throws TwikeyException
     */
    public function create($data)
    {
        $response = $this->twikey->request('POST', "/creditor/transaction", ['form_params' => $data]);
        $server_output = $this->checkResponse($response, "Creating a new transaction!");
        return json_decode($server_output);
    }

    /**
     * Note this is rate limited
     * @throws TwikeyException
     */
    public function get($txid, $ref)
    {
        if (empty($ref)) {
            $item = "id=" . $txid;
        } else {
            $item = "ref=" . $ref;
        }

        $response = $this->twikey->request('GET', sprintf("/creditor/transaction/detail?%s", $item), []);
        $server_output = $this->checkResponse($response, "Retrieving payments!");
        return json_decode($server_output);
    }

    /**
     * Read until empty
     * @throws TwikeyException
     */
    public function feed(TransactionCallback $callback)
    {
        $count = 0;
        do {
            $response = $this->twikey->request('GET', "/creditor/transaction", []);
            $server_output = $this->checkResponse($response, "Retrieving transaction feed!");
            $transactions = json_decode($server_output);
            foreach ($transactions->Entries as $tx){
                $count++;
                $callback->handle($tx);
            }
        }
        while(count($transactions->Entries) > 0);
        return $count;
    }

    /**
     * @throws TwikeyException
     */
    public function sendPending(int $ct)
    {
        $response = $this->twikey->request('POST', "/creditor/collect", ['form_params' => ["ct" => $ct]]);
        $server_output = $this->checkResponse($response, "Retrieving transaction feed!");
        return json_decode($server_output);
    }

    /**
     * @throws TwikeyException
     */
    public function cancel(?string $id, ?string $ref)
    {
        $queryPrefix = isset($id) || isset($ref) ? '?' : null;
        $queryId = isset($id) ? "id=$id" : null;
        $queryRef = isset($ref) ? sprintf("%ref=$ref", isset($id) ? '&' : null) : null;
        $response = $this->twikey->request('DELETE', sprintf('/creditor/transaction%s%s%s', $queryPrefix, $queryId, $queryRef), []);
        $server_output = $this->checkResponse($response, "Cancel a transaction!");
        return json_decode($server_output);
    }

}
