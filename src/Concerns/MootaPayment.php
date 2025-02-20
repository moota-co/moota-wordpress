<?php

namespace Moota\MootaSuperPlugin\Concerns;
use Moota\Moota\Config;
use Moota\Moota\MootaApi;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Contracts\Cache\ItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class MootaPayment
{
    private ?string $access_token = null;

    public function __construct(?string $access_token = null)
    {

        Config::$ACCESS_TOKEN = $access_token;
        
        $this->access_token = $access_token;
    }

    public function getBanks() : ?array
{
    if (empty($this->access_token)) {
        return null;
    }

    try {
        // Dapatkan nilai koneksi database dari WordPress
        $db_user = DB_USER;
        $db_password = DB_PASSWORD;
        $db_host = DB_HOST;
        $db_name = DB_NAME;

        // Buat koneksi database menggunakan Doctrine DBAL
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql', // Tambahkan driver
            'user' => $db_user,
            'password' => $db_password,
            'host' => $db_host,
            'dbname' => $db_name,
        ]);

        // Buat instance DoctrineDbalAdapter
        $cache = new DoctrineDbalAdapter(
            $connection, // Koneksi database
            'cache',
            0 
        );

        // Dapatkan access token yang tersimpan sebelumnya
        $stored_token_item = $cache->getItem('moota_stored_access_token');

        // Jika access token berubah, hapus cache sebelumnya
        if (!$stored_token_item->isHit() || $stored_token_item->get() !== $this->access_token) {
            // Hapus cache yang ada
            $cache->deleteItem("moota-bank-account-lists");
            $cache->deleteItem("moota-account-lists");
            
            // Simpan access token baru
            $stored_token_item->set($this->access_token);
            $cache->save($stored_token_item);
        }

        // Ambil data dari cache atau API
        $response = $cache->get("moota-bank-account-lists", function(ItemInterface $item) : ?object {
            $response = MootaApi::getAccountList();
            $item->expiresAfter((60 * 60) * 5); // Cache kedaluwarsa setelah 5 jam
            return $response;
        });

        // Jika data tidak ditemukan di cache pertama, coba cache kedua
        if (!isset($response->data)) {
            $response = $cache->getItem("moota-account-lists")->get();
        }

        // Jika data tetap tidak ditemukan, kembalikan null
        if (!isset($response->data)) {
            return null;
        }

        return $response->data;

    } catch (Exception $e) {
        // Tangani error yang mungkin terjadi
        echo "Terjadi Error : {$e->getMessage()}";
    }
}

    public function getPayments() : ?object
    {
        $moota_settings = get_option("moota_settings", []);

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

    public function createTag(string $name)
    {
        $registered_tags = MootaApi::getTag();

        $count = 0;

        foreach($registered_tags as $tag){
            if($tag->name == $name){
                $count++;
            }
        }

        if(!$count){

            MootaApi::createTag($name);

            return true;
        }

        return true;

    }

    public function attachTransactionId(string $mutation_id, ?string $transaction_id)
    {
        if(empty($this->access_token)){
            return null;
        }

        $this->createTag($transaction_id);

        MootaApi::attachMutationTag($mutation_id, [$transaction_id]);
    }

    public function attachPlatform(string $mutation_id, ?string $platform)
    {
        if(empty($this->access_token)){
            return null;
        }

        $this->createTag($platform);

        MootaApi::attachMutationTag($mutation_id, [$platform]);
    }

    public function verifyMutation(array $mutation) : bool
    {
        if(empty($this->access_token)){
            return false;
        }

        $mutations = MootaApi::getMutationList($mutation['bank_id']);

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
        if(empty($this->access_token)){
            return null;
        }

        $moota_settings = get_option("moota_settings", []);

        $log_path = "refresh_mutation_{$bank_id}.log";

        $log = $this->getLog($log_path);
        $current_time = microtime(true);

        if(is_array($log) && ($current_time < array_get($log, "next_request"))){
            return null;
        }

        $this->writeLog($log_path, [
            "last_request" => $current_time,
            "next_request" => ($current_time + (60 *  array_get($moota_settings, "moota_refresh_mutation_interval", 5)))
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