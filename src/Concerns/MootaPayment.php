<?php

namespace Moota\MootaSuperPlugin\Concerns;

use Exception;
use Moota\Moota\Config;
use Moota\Moota\MootaApi;

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

    public function clearCache(): void
    {
        try {
            // Hapus semua transient yang terkait
            delete_transient('moota-bank-account-lists');
            delete_transient('moota-account-lists');
            delete_transient('moota_stored_access_token');
        } catch (Exception $e) {
            error_log("Gagal menghapus cache: " . $e->getMessage());
        }
    }

    public function attachMerchant(string $mutation_id, ?string $merchant)
    {
        if(empty($this->access_token)){
            return null;
        }

        $this->createTag($merchant);

        MootaApi::attachMutationTag($mutation_id, [$merchant]);
    }

    public function getBanks(bool $forceRefresh = false)
{
    if (empty($this->access_token)) {
        return null;
    }

    if ($forceRefresh) {
        $this->clearCache();
    }

    try {
        // Dapatkan access token yang tersimpan
        $stored_token = get_transient('moota_stored_access_token');
        
        // Jika access token berubah, hapus cache sebelumnya
        if (!$stored_token || $stored_token !== $this->access_token) {
            $this->clearCache();
            set_transient('moota_stored_access_token', $this->access_token, DAY_IN_SECONDS);
        }

        // Ambil data dari cache atau API
        $response = get_transient('moota-bank-account-lists');
        if (false === $response) {
            $allData = [];
            $currentPage = 1;
            $totalPages = 1;
        
            do {
                $response = MootaApi::getAccountList($currentPage);

                if (!isset($response->data)) break;

                // Gabungkan data dari semua halaman
                $allData = array_merge($allData, $response->data);
                $totalPages = $response->last_page ?? 1; // Ambil total halaman dari respons
                $currentPage++;
            } while ($currentPage <= $totalPages);
        
            // Simpan semua data ke transient
            set_transient('moota-bank-account-lists', (object) [
                'data' => $allData,
                'total' => count($allData)
            ], 5 * HOUR_IN_SECONDS); // 5 jam
        }

        // Jika data tetap tidak ditemukan, kembalikan null
        if (!isset($response->data)) {
            return null;
        }

        return $allData; // Pastikan ini mengembalikan semua data yang telah digabungkan

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