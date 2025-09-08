<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\Permata;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class PermataVA extends BaseVirtualAccount {
    public $bankCode = 'Permata';
    public $bankName = 'Permata Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/Permata/Permata.png', MOOTA_FULL_PATH );
    }
}