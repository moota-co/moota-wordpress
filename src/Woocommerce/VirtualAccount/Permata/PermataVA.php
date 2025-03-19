<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\Permata;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class PermataVA extends BaseVirtualAccount {
    public $bankCode = 'Permata';
    public $bankName = 'Permata Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}