<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BCA;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BCAVA extends BaseVirtualAccount {
    public $bankCode = 'bca';
    public $bankName = 'BCA Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}