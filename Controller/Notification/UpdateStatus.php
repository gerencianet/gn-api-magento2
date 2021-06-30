<?php

namespace Gerencianet\Magento2\Controller\Notification;

use Exception;
use Gerencianet\Gerencianet;
use Gerencianet\Magento2\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class UpdateStatus extends Action implements CsrfAwareActionInterface {

	const PAID = 'paid';
	const UNPAID = 'unpaid';
	const REFUNDED = 'refunded';
	const CONTESTED = 'contested';
	const CANCELED = 'canceled';
	const SETTLED = 'settled';
	const WAITING = 'waiting';
	const NEW = 'new';

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
		OrderRepositoryInterface $orderRepository
	) {
		$this->_helperData = $helperData;
		$this->_orderRepository = $orderRepository;
		$this->_searchCriteriaBuilder = $searchCriteriaBuilder;
		parent::__construct($context);
	}

	public function execute() {
		try {
			$body = $this->getRequest()->getPostValue();
			$this->_helperData->logger($body);

			$params = ["token" => $body['notification']];
			$options = $this->_helperData->getOptions();
			$api = new Gerencianet($options);

			$chargeNotification = $api->getNotification($params, []);

			$i = count($chargeNotification["data"]);
			$ultimoStatus = $chargeNotification["data"][$i - 1];
			$chargeId = $ultimoStatus['identifiers']['charge_id'];
			$status = $ultimoStatus['status']['current'];

			$searchCriteria = $this->_searchCriteriaBuilder
				->addFilter(
					'gerencianet_transaction_id',
					$chargeId,
					'eq'
				)->create();

			$collection = $this->_orderRepository->getList($searchCriteria);

			/** @var Order */
			foreach ($collection as $order) {
				switch ($status) {
					case self::NEW: {
							$order->setState($this->_helperData->getOrderStatus());
							$order->setStatus($this->_helperData->getOrderStatus());
							break;
						}
					case self::WAITING: {
							$order->setState($this->_helperData->getOrderStatus());
							$order->setStatus($this->_helperData->getOrderStatus());
							break;
						}
					case self::PAID: {
							$order->setState(Order::STATE_PROCESSING);
							$order->setStatus(Order::STATE_PROCESSING);
							break;
						}
					case self::UNPAID: {
							$order->setState($this->_helperData->getOrderStatus());
							$order->setStatus($this->_helperData->getOrderStatus());
							break;
						}
					case self::REFUNDED: {
							$order->cancel();
							break;
						}
					case self::CANCELED: {
							$order->cancel();
							break;
						}
					case self::CONTESTED: {
							$order->setState(Order::STATE_HOLDED);
							$order->setStatus(Order::STATE_HOLDED);
							break;
						}
					case self::SETTLED: {
							$order->setState(Order::STATE_PROCESSING);
							$order->setStatus(Order::STATE_PROCESSING);
							break;
						}
				}
				$this->_orderRepository->save($order);
			}
		} catch (Exception $e) {
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
