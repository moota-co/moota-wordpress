<?php

namespace Test;

use Moota\Moota\Config;
use Moota\Moota\MootaApi;

class ApiRequestorTest
{
    public function attachMutationTagTest()
    {
        Config::$ACCESS_TOKEN = 'access_token';

        MootaApi::attachMutationTag([
            'tag #1', 'tag #2'
        ]);

        // TODO Assert Equal()

    }

}