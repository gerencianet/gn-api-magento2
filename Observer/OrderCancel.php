<?php

namespace Gerencianet\Magento2\Observer;

use Exception;
use Gerencianet\Exception\GerencianetException;
use Gerencianet\Magento2\Helper\Data;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;
use Gerencianet\Gerencianet;

class OrderCancel implements ObserverInterface {

  /** @var Data */
  protected $_helperData;

  /** @var LoggerInterface */
  protected $_logger;

  public function __construct(Data $helperData, LoggerInterface $logger) {
    $this->_helperData = $helperData;
    $this->_logger = $logger;
  }

  public function execute(Observer $observer) {
    $order = $observer->getEvent()->getOrder();
    $options = $this->_helperData->getOptions();
    $chargeId = $order->getGerencianetTransactionId();

    $params = [
      'id' => $chargeId
    ];

    try {
      $api = new Gerencianet($options);
      $charge = $api->cancelCharge($params, []);
      $order->addStatusToHistory(
        $order->getStatus(),
        'Campainha recebida do Gerencianet: Transaction ID ' . $chargeId . ' - Status Cancelado',
        true
      );
      $this->_logger->info(json_encode($charge));
    } catch (Exception $e) {
      $this->_logger->info(json_encode($e->getMessage()));
      throw new Exception($e->getMessage(), 1);
    }
  }
}
