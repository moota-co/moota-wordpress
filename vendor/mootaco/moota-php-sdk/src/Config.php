<?php


namespace Moota\Moota;

class Config
{
    /**
     * Access token
     *
     * @access_token
     */
    public static string $ACCESS_TOKEN;

    /**
     * Target Url Moota v2
     *
     * @BASE_URL
     */
    const BASE_URL = 'https://app.moota.co';

    const ENDPOINT_MUTATION_INDEX = '/api/v2/mutation';
    const ENDPOINT_MUTATION_NOTE = '/api/v2/mutation/{mutation_id}/note';
    const ENDPOINT_BANK_INDEX = '/api/v2/bank';
    const ENDPOINT_BANK_REFRESH_MUTATION = '/api/v2/bank/{bank_id}/refresh';
    const ENDPOINT_ATTATCH_TAGGING_MUTATION = '/api/v2/tagging/mutation/{mutation_id}';
    public function getBaseUrl()
    {
        return self::BASE_URL;
    }

}