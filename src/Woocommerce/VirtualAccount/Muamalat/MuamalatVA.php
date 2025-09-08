<?php
namespace Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\Muamalat;

use Moota\MootaSuperPlugin\Woocommerce\VirtualAccount\BaseVirtualAccount;

class MuamalatVA extends BaseVirtualAccount {
    public $bankCode = 'Muamalat';
    public $bankName = 'Muamalat Virtual Account';
    public $icon;
    
    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/Muamalat/Muamalat.png', MOOTA_FULL_PATH );
    }
}