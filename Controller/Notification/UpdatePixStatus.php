<?php

namespace Gerencianet\Magento2\Controller\Notification;

use Exception;
use Gerencianet\Gerencianet;
use Gerencianet\Magento2\Helper\Data;
use Gerencianet\Exception\GerencianetException;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Filesystem\DirectoryList;

class UpdatePixStatus extends Action implements CsrfAwareActionInterface {

	const ATIVA = 'ATIVA';
	const CONCLUIDA = 'CONCLUIDA';
	const REMOVIDA_PELO_USUARIO_RECEBEDOR = 'REMOVIDA_PELO_USUARIO_RECEBEDOR';
	const REMOVIDA_PELO_PSP = 'REMOVIDA_PELO_PSP';

	/** @var Data */
	protected $_helperData;

	/** @var OrderRepositoryInterface */
	protected $_orderRepository;

	/** @var SearchCriteriaBuilder */
	protected $_searchCriteriaBuilder;

	public function __construct(
		Context $context,
		Data $helperData,
		SearchCriteriaBuilder $searchCriteriaBuilder,
		OrderRepositoryInterface $orderRepository,
        DirectoryList $dl
	) {
		$this->_helperData = $helperData;
		$this->_orderRepository = $orderRepository;
		$this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_dir = $dl;

		parent::__construct($context);
	}

	public function execute() {
		try {
			$body = json_decode($this->getRequest()->getContent(), true);
			$this->_helperData->logger($body);

            if (isset($body['pix']) && isset($body['pix'][0])) {
                $pixBody = $body['pix'][0];
                $txId = $pixBody['txid'];
                $params = ["txid" => $txId];

                $certificadopath = $this->_dir->getPath('media') . "/test/" . $this->_helperData->getPixCert();
                $certificadoPix = file_exists($certificadopath) ? $certificadopath : false;

                $options = $this->_helperData->getOptions();
                $options['pix_cert'] = $certificadoPix;
                $api = new Gerencianet($options);

                $chargeNotification = $api->pixDetailCharge($params, []);
                $status = $chargeNotification['status'];

                $searchCriteria = $this->_searchCriteriaBuilder
                    ->addFilter('gerencianet_transaction_id', $txId, 'eq')
                    ->create();

                $collection = $this->_orderRepository->getList($searchCriteria);
            
                /** @var Order */
                foreach ($collection as $order) {
                    switch ($status) {
                        case self::ATIVA: {
                                $order->setState(Order::STATE_PENDING_PAYMENT);
                                $order->setStatus(Order::STATE_PENDING_PAYMENT);
                                break;
                            }
                        case self::CONCLUIDA: {
                                $order->setState(Order::STATE_PROCESSING);
                                $order->setStatus(Order::STATE_PROCESSING);
                                break;
                            }
                        case self::REMOVIDA_PELO_USUARIO_RECEBEDOR: {
                                $order->cancel();
                                break;
                            }
                        case self::REMOVIDA_PELO_PSP: {
                                $order->cancel();
                                break;
                            }
                    }
                    $this->_orderRepository->save($order);
                }
            }
		} catch (GerencianetException $e) {
			$this->_helperData->logger($e->getMessage());
			throw new Exception("Error Processing Request", 1);
		}
	}

	/** * @inheritDoc */
	public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException {
		return null;
	}

	/** * @inheritDoc */
	public function validateForCsrf(RequestInterface $request): ?bool {
		return true;
	}
}
