<?php
namespace Moota\MootaSuperPlugin\Woocommerce\BankTransfer\Mandiri;

use Moota\MootaSuperPlugin\Woocommerce\BankTransfer\BaseBankTransfer;

class MandiriGateway extends BaseBankTransfer {
    public $bankCode = 'Mandiri';
    public $icon;

    public function __construct() {
        parent::__construct();
        $this->icon = plugins_url( 'assets/img/logo/Mandiri/Mandiri.png', MOOTA_FULL_PATH );
    }
}