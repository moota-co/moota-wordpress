<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BCA;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BCAGateway extends BaseBankTransfer {
    public $bankCode = 'BCA';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BCA/BCA.png', MOOTA_FULL_PATH );
    }
}