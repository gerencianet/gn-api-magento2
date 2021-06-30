<?php

namespace Gerencianet\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Model\CcConfig;
use Gerencianet\Magento2\Helper\Data;

class PaymentConfigProvider implements ConfigProviderInterface {

  protected $_methodCode = Data::METHOD_CODE_CREDIT_CARD;

  /** @var CcConfig */
  protected $_ccConfig;

  /** @var Data */
  protected $_helperData;

  public function __construct(CcConfig $ccConfig, Data $helperData) {
    $this->_ccConfig = $ccConfig;
    $this->_helperData = $helperData;
  }

  public function getConfig() {
    return [
      'payment' => [
        'cc' => [
          'availableTypes' => [
            $this->_methodCode => [
              'AE' => 'American Express',
              'ELO' => 'Elo',
              'HC' => 'Hipercard',
              'MC' => 'Mastercard',
              'VI' => 'Visa'
            ]
          ],
          'months' => [$this->_methodCode => $this->_ccConfig->getCcMonths()],
          'years' => [$this->_methodCode => $this->_ccConfig->getCcYears()],
          'hasVerification' => $this->_ccConfig->hasVerification(),
          'cvvImageUrl' => $this->_ccConfig->getCvvImageUrl(),
          'minPrice' => $this->_helperData->getPrecoMinimo(),
          'identificadorConta' => $this->_helperData->getIdentificadorConta(),
          'urlGerencianet' => $this->_helperData->getUrl()
        ],
      ]
    ];
  }
}
