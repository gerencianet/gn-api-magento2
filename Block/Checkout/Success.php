<?php

namespace Gerencianet\Magento2\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;

class Success extends Template {

  /** @var Session */
  protected $_checkoutSession;

  public function __construct(
    Session $checkoutSession,
    Context $context,
    array $data = null
  ) {
    if ($data == null) {
      $data = [];
    }
    parent::__construct($context, $data);
    $this->_checkoutSession = $checkoutSession;
  }

  /** Return Last Order */
  public function getOrder() {
    return $this->_checkoutSession->getLastRealOrder();
  }
}
