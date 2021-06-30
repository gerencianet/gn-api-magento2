<?php
namespace Gerencianet\Magento2\Model\Payment;

use DateTime;
use Exception;
use Gerencianet\Gerencianet;
use Gerencianet\Magento2\Helper\Data as GerencianetHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Catalog\Model\Product;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\DataObject;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Checkout\Model\Session;

class Billet extends AbstractMethod {

  /**
   * @var string
   */
  protected $_code = 'gerencianet_boleto';

  /** @var GerencianetHelper */
  protected $_helperData;

  /** @var StoreManagerInterface */
  protected $_storeMagerInterface;

  /** @var Session */
  protected $_checkoutSession;

  public function __construct(
    Context $context,
    Registry $registry,
    ExtensionAttributesFactory $extensionFactory,
    AttributeValueFactory $customAttributeFactory,
    Data $paymentData,
    ScopeConfigInterface $scopeConfig,
    Logger $logger,
    AbstractResource $resource = null,
    AbstractDb $resourceCollection = null,
    array $data = [],
    GerencianetHelper $helperData,
    StoreManagerInterface $storeManager,
    Session $checkoutSession
  ) {
    parent::__construct(
      $context,
      $registry,
      $extensionFactory,
      $customAttributeFactory,
      $paymentData,
      $scopeConfig,
      $logger,
      $resource,
      $resourceCollection,
      $data
    );
    $this->_helperData = $helperData;
    $this->_storeMagerInterface = $storeManager;
    $this->_checkoutSession = $checkoutSession;
  }

  public function order(InfoInterface $payment, $amount) {
    try {

      $paymentInfo = $payment->getAdditionalInformation();
      $days = $this->_scopeConfig->getValue('payment/gerencianet_boleto/validade');
      $date = new DateTime("+$days days");

      /** @var Order */
      $order = $payment->getOrder();
      $billingaddress = $order->getBillingAddress();

      $options = $this->_helperData->getOptions();
      
      $data = [];

      $i = 0;
      $items = $order->getAllItems();
      /** @var Product */
      foreach ($items as $item) {
        if ($item->getProductType() != 'configurable') {
          if ($item->getPrice() == 0) {
            $parentItem = $item->getParentItem();
            $price = $parentItem->getPrice();
          } else {
            $price = $item->getPrice();
          }
          $data['items'][$i]['name'] = $item->getName();
          $data['items'][$i]['value'] = $price * 100;
          $data['items'][$i]['amount'] = $item->getQtyOrdered();
          $i++;
        }
      }

      $shippingAddress = $order->getShippingAddress();

      
      if (isset($shippingAddress)) {
        $data['shippings'][0]['name'] = $shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname();
        $data['shippings'][0]['value'] = $order->getShippingAmount() * 100;
      }
      
      $data['metadata']['notification_url'] = $this->_storeMagerInterface->getStore()->getBaseUrl() . 'gerencianet/notification/updatestatus';

      $data['payment']['banking_billet']['customer']['name'] = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();

      if ($paymentInfo['documentType'] == "CPF") {
        $data['payment']['banking_billet']['customer']['cpf'] = $paymentInfo['cpfCustomer'];
      } else if ($paymentInfo['documentType'] == "CNPJ") {
        $data['payment']['banking_billet']['customer']['juridical_person']['corporate_name'] = $paymentInfo['companyName'];
        $data['payment']['banking_billet']['customer']['juridical_person']['cnpj'] = $paymentInfo['cpfCustomer'];
      }

      $billingAddPhone = $this->formatPhone($billingaddress->getTelephone());
      $data['payment']['banking_billet']['customer']['phone_number'] = $billingAddPhone;
      $data['payment']['banking_billet']['customer']['email'] = $billingaddress->getEmail();
      try {
        $street = $billingaddress->getStreet();
        $data['payment']['banking_billet']['customer']['address']['street'] = $street[0];
        $data['payment']['banking_billet']['customer']['address']['number'] = $street[1];
        if (isset($street[3])) {
          $data['payment']['banking_billet']['customer']['address']['complement'] = $street[2];
          $data['payment']['banking_billet']['customer']['address']['neighborhood'] = $street[3];
        } else {
          $data['payment']['banking_billet']['customer']['address']['neighborhood'] = $street[2];
        }
        $data['payment']['banking_billet']['customer']['address']['state'] = $billingaddress->getRegionCode();
      } catch (Exception $e) {
        throw new Exception("Erro, por favor verifique seus campos de endereço!", 1);
      }

      $data['payment']['banking_billet']['expire_at'] = $date->format('Y-m-d');

      $discountValue = str_replace("-", "", $order->getDiscountAmount());
      if ($discountValue > 0) {
        $data['payment']['banking_billet']['discount']['type'] = 'currency';
        $data['payment']['banking_billet']['discount']['value'] = $discountValue * 100;
      }

      $message = $this->_helperData->getBilletInstructions();
      if ($message !== "") {
        $data['payment']['banking_billet']['message'] = $message;
      }

      $billetConfig = $this->_helperData->getBilletSettings();
      if ($billetConfig['fine'] != "") {
        $data['payment']['banking_billet']['configurations']['fine'] = $billetConfig['fine'];
      }
      if ($billetConfig['interest'] != "") {
        $data['payment']['banking_billet']['configurations']['interest'] = $billetConfig['interest'];
      }
      
      $api = new Gerencianet($options);

      $payCharge = $api->oneStep([], $data);

      $order->setGerencianetCodigoDeBarras($payCharge['data']['barcode']);
      $order->setGerencianetTransactionId($payCharge['data']['charge_id']);
      $order->setGerencianetUrlBoleto($payCharge['data']['pdf']['charge']);
    } catch (Exception $e) {
      throw new LocalizedException(__($e->getMessage()));
    }
  }

  public function assignData(DataObject $data) {
    $info = $this->getInfoInstance();
    $info->setAdditionalInformation('cpfCustomer', $data['additional_data']['cpfCustomer'] ?? null);
    $info->setAdditionalInformation('companyName', $data['additional_data']['companyName'] ?? null);
    $info->setAdditionalInformation('documentType', $data['additional_data']['documentType'] ?? null);
    return $this;
  }

  public function isAvailable(CartInterface $quote = null) {
    $total = $this->_checkoutSession->getQuote()->getGrandTotal();
    return ($this->_helperData->isBilletActive() && $total >= 5) ? true : false;
  }

  public function formatPhone($phone)
    {
        $formatedPhone = preg_replace('/[^0-9]/', '', $phone);
        $matches = [];

        if (strlen($formatedPhone) == 13) {
            preg_match('/^([0-9]{2})([0-9]{2})([0-9]{4,5})([0-9]{4})$/', $formatedPhone, $matches);
            if ($matches) {
                return '+'.$matches[1] . ' ('.$matches[2] . ')' . $matches[3] . '-' . $matches[4] ;
            }
        } else {
            preg_match('/^([0-9]{2})([0-9]{4,5})([0-9]{4})$/', $formatedPhone, $matches);
            if ($matches) {
                return $matches[1].$matches[2].$matches[3] ;
            }
        }

        return $formatedPhone;
    }
}
