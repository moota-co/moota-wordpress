<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Maybank;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class MaybankGateway extends BaseBankTransfer {
    public $bankCode = 'Maybank';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/Maybank/Maybank.png', MOOTA_FULL_PATH );
    }
}