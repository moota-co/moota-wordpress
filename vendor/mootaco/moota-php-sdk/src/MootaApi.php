<?php

namespace Moota\Moota;

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

    public static function getAccountList() : ?object
    {
        return ApiRequester::get(
            Config::BASE_URL . Config::ENDPOINT_BANK_INDEX,
            Config::$ACCESS_TOKEN
        );
    }

    public static function getMutationList(?string $bank_id = null) : ?object
    {
        return ApiRequester::get(
            Config::BASE_URL . Config::ENDPOINT_MUTATION_INDEX,
            Config::$ACCESS_TOKEN
        );
    }

    public static function attachMutationNote(string $mutation_id, string $message) : ?object
    {
        return ApiRequester::post(
            Config::BASE_URL . \str_replace("{mutation_id}", $mutation_id, Config::ENDPOINT_MUTATION_NOTE),
            Config::$ACCESS_TOKEN,
            [
                "note" => $message
            ]
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

    public function __clone()
    {
    }

    public function __wakeup()
    {
    }
}