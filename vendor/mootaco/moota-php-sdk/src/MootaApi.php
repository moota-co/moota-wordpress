<?php

namespace Moota\Moota;

use Moota\Moota\Data\CreateTransactionData;
use Moota\MootaSuperPlugin\Contracts\MootaWebhook;

class MootaApi
{
    private static MootaApi $instance;

    public function __construct()
    {
    }

    public static function getInstance(): MootaApi
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function getAccountList(int $page = 1) : ?object
{
    $endpoint = Config::BASE_URL . Config::ENDPOINT_BANK_INDEX . "?page=$page";
    
    // Inisialisasi cURL
    $ch = curl_init($endpoint);
    
    // Set option untuk mengembalikan hasil sebagai string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . Config::$ACCESS_TOKEN
    ]);
    
    // Eksekusi cURL
    $response = curl_exec($ch);
    
    // Dapatkan informasi tentang request
    $info = curl_getinfo($ch);
    
    // Tutup cURL
    curl_close($ch);
    
    // Logging hasil request
    $log = [
        'request' => [
            'url' => $info['url'],
            'method' => $info['request_method'],
            'headers' => $info['request_header'],
        ],
        'response' => [
            'status' => $info['http_code'],
            'body' => $response,
        ]
    ];
    
    // Simpan log ke file
    $log_path = 'moota_api_request.log';
    MootaWebhook::addLog(
        "Transaksi Bank Transfer berhasil dibuat: \n" . 
        print_r($log, true)
    );
    
    // Decode response JSON
    $result = json_decode($response);
    
    return $result;
}

    public static function getMutationList(?string $bank_id = null) : ?object
    {
        return ApiRequester::get(
            Config::BASE_URL . Config::ENDPOINT_MUTATION_INDEX,
            Config::$ACCESS_TOKEN
        );
    }

    public static function attachMutationTag(string $mutation_id, array $tags) : ?object
    {
        return ApiRequester::post(
            Config::BASE_URL . \str_replace("{mutation_id}", $mutation_id, Config::ENDPOINT_ATTATCH_TAGGING_MUTATION),
            Config::$ACCESS_TOKEN,
            [
                "name" => $tags
            ]
        );
    }

    public static function refreshMutationNow(string $bank_id) : ?object
    {
        return ApiRequester::post(
            Config::BASE_URL . \str_replace("{bank_id}", $bank_id, Config::ENDPOINT_BANK_REFRESH_MUTATION),
            Config::$ACCESS_TOKEN
        );
    }

    public static function getTag() : ?object
    {
        return ApiRequester::get(
            Config::BASE_URL . Config::ENDPOINT_TAGGING_INDEX,
            Config::$ACCESS_TOKEN
        );
    }
    
    public static function createTag(string $name) : ?object
    {
        return ApiRequester::post(
            Config::BASE_URL . Config::ENDPOINT_TAGGING_INDEX,
            Config::$ACCESS_TOKEN,
            [
                "name" => $name
            ]
        );
    }

    public static function createTransaction(CreateTransactionData $data) : ?object
    {
        $settings = get_option("moota_settings", []);
        $token    = array_get($settings, 'moota_v2_api_key', []);

        return ApiRequester::post(
            Config::BASE_URL . Config::ENDPOINT_CREATE_TRANSACTION,
            Config::$ACCESS_TOKEN = $token,
            CreateTransactionData::transform()
        );
    }
}