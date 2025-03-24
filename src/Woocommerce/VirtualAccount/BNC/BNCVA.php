<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BNC;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BNCVA extends BaseVirtualAccount {
    public $bankCode = 'bnc';
    public $bankName = 'BNC Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}