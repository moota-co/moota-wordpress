<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BNI;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BNIVA extends BaseVirtualAccount {
    public $bankCode = 'bni';
    public $bankName = 'BNI Virtual Account';
    
    public function __construct() {
        parent::__construct();
    }
}