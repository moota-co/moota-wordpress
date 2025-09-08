<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BRI;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class BRIGateway extends BaseBankTransfer {
    public $bankCode = 'BRI';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/BRI/BRI.png', MOOTA_FULL_PATH );
    }
}