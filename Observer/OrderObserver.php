<?php

namespace Gerencianet\Magento2\Observer;

use Gerencianet\Magento2\Helper\Data;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class OrderObserver implements ObserverInterface {

  /** @var Data */
  protected $_helperData;

  public function __construct(Data $helperData) {
    $this->_helperData = $helperData;
  }

  public function execute(Observer $observer) {
    $order = $observer->getEvent()->getOrder();
    $order->setState("new")->setStatus($this->_helperData->getOrderStatus());
    $order->save();
  }
}
