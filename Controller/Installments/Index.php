<?php

declare(strict_types=1);

namespace Gerencianet\Magento2\Controller\Installments;

use Gerencianet\Magento2\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Gerencianet\Exception\GerencianetException;
use Gerencianet\Gerencianet;

class Index extends Action implements HttpGetActionInterface {

  /** @var Data */
  private $_helperData;

  /** @var JsonFactory */
  private $_jsonResultFactory;

  /**
   * @param RequestInterface $request
   * @param Data $helperData
   * @param JsonFactory $jsonResultFactory
   */
  public function __construct( 
      Context $context, 
      Data $helperData, 
      JsonFactory $jsonResultFactory 
  ) {
    $this->_helperData = $helperData;
    $this->_jsonResultFactory = $jsonResultFactory;
    
    parent::__construct($context);
  }

  /** @inheritdoc */
  public function execute() {

    $brand = $this->getRequest()->getParam('brand');
    $total = $this->getRequest()->getParam('total');
    $options = $this->_helperData->getOptions();

    $params = ['total' => $total, 'brand' => $brand];

    try {
        $api = new Gerencianet($options);
        $response = $api->getInstallments($params, []);
    
        $r = $this->_jsonResultFactory->create();
        $r->setData($response);

        return $r;

    } catch (GerencianetException $e) {
        print_r($e->code . " - " . $e->error . "<br>");
        print_r($e->errorDescription);

    } catch (Exception $e) {
        print_r($e->getMessage());
    }
  }
}