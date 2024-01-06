<?php

namespace Moota\MootaSuperPlugin\Concerns;
use Moota\Moota\Config;
use Moota\Moota\MootaApi;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class MootaPayment
{
    private string $access_token;

    public function __construct(string $access_token)
    {
        Config::$ACCESS_TOKEN = $access_token;
        
        $this->access_token = $access_token;
    }

    public function getBanks() : ?array
    {
        $cache = new FilesystemAdapter;
        
        $response = MootaApi::getAccountList();

        $cache->get("moota-account-lists", function(ItemInterface $item) use ($response) : ?object {
            $item->expiresAfter((60 * 60) * 5);

            return $response;
        });

        if(!isset($response->data)){
            $response = $cache->getItem("moota-account-lists")->get();
        }

        if(!isset($response->data)){
            return null;
        }

        return $response->data;
    }

    public function getPayments() : ?object
    {
        $moota_settings = get_option("moota_settings");

        $banks = $this->getBanks();

        if(empty($banks)){
            return null;
        }

        $payments = [];

        foreach($banks as $bank){
            if(array_has($moota_settings, $bank->bank_id) && array_get($moota_settings, $bank->bank_id)){
                $payments[] = $bank;
            }
        }
        

        return (object)$payments;
    }

    public function attachTransactionId(string $mutation_id, mixed $transaction_id)
    {
        MootaApi::attachMutationTag($mutation_id, [$transaction_id]);
    }

    public function attachMerchant(string $mutation_id, ?string $merchant)
    {
        MootaApi::attachMutationTag($mutation_id, [$merchant]);
    }

    public function attachPlatform(string $mutation_id, ?string $platform)
    {
        MootaApi::attachMutationTag($mutation_id, [$platform]);
    }

    public function verifyMutation(array $mutation) : bool
    {
        // die(var_dump(MootaApi::getMutationList($mutation['bank_id'])->data));

        $mutations = MootaApi::getMutationList($mutation['bank_id']);

        // die(var_dump($mutations));

        $match_count = 0;

        foreach((array)$mutations->data as $real_mutation){

            if(
                $real_mutation->mutation_id == $mutation['mutation_id'] && 
                $real_mutation->amount == $mutation['amount'] &&
                $real_mutation->token == $mutation['token']
            ){
                $match_count++;
            }

        }

        if($match_count == 0){
            return false;
        }

        return true;
    }

    public function refreshMutation(string $bank_id) : ?object
    {
        $log_path = "refresh_mutation_{$bank_id}.log";

        $log = $this->getLog($log_path);
        $current_time = microtime(true);

        if(is_array($log) && ($current_time < array_get($log, "next_request"))){
            return null;
        }

        $this->writeLog($log_path, [
            "last_request" => $current_time,
            "next_request" => ($current_time + (60*5))
        ]);

        return MootaApi::refreshMutationNow($bank_id);
    }

    private function writeLog(string $filename, ?array $data)
    {
        $path = MOOTA_LOGS_PATH."/{$filename}";

        file_put_contents($path, json_encode($data));
    }

    private function getLog($filename) : ?array
    {
        $path = MOOTA_LOGS_PATH."/{$filename}";

        $data = file_get_contents($path);

        if(empty($data)){
            return null;
        }

        return json_decode($data, true);
    }

}