<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BNC;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class BNCVA extends BaseVirtualAccount {
    public $bankCode = 'bnc';
    public $bankName = 'BNC Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BNC/BNC.png', MOOTA_FULL_PATH );
    }
}