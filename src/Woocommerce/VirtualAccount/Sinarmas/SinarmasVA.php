<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\Sinarmas;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class SinarmasVA extends BaseVirtualAccount {
    public $bankCode = 'Sinarmas';
    public $bankName = 'Sinarmas Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/Sinarmas/Sinarmas.png', MOOTA_FULL_PATH );
    }
}