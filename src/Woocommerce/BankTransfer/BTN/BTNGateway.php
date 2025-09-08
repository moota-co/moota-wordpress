<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BTN;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BTNGateway extends BaseBankTransfer {
    public $bankCode = 'BTN';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BTN/BTN.png', MOOTA_FULL_PATH );
    }
}