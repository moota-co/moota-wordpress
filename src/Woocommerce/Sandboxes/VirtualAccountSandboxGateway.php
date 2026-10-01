<?php

namespace Moota\MootaSuperPlugin\Woocommerce\Sandboxes;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class VirtualAccountSandboxGateway extends BaseVirtualAccount
{
    public $bankCode = 'vasandbox';
    public $bankName = 'Virtual Account (Sandbox)';

    public function __construct()
    {
        parent::__construct();
    }

    protected function is_bank_type_match($bankType)
    {
        return strcasecmp($bankType, 'vaSandbox') === 0;
    }
}
