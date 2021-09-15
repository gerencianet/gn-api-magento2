<?php

namespace Gerencianet\Magento2\Model\Payment;

use Exception;
use Gerencianet\Exception\GerencianetException;
use Magento\Payment\Model\Method\AbstractMethod;
use Gerencianet\Gerencianet;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Gerencianet\Magento2\Helper\Data as GerencianetHelper;
use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Checkout\Model\Session;


class CreditCard extends AbstractMethod
{

    protected $_code = 'gerencianet_cc';

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
        $this->_checkoutSession = $checkoutSession;
        $this->_helperData = $helperData;
        $this->_storeMagerInterface = $storeManager;
    }

    public function order(InfoInterface $payment, $amount)
    {

        try {
            $options = $this->_helperData->getOptions();

            $paymentInfo = $payment->getAdditionalInformation();

            /** @var \Magento\Sales\Model\Order */
            $order = $payment->getOrder();
            $billingaddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();

            $data = [];

            $i = 0;
            $orderitems = $order->getAllItems();
            /** @var \Magento\Catalog\Model\Product */
            foreach ($orderitems as $item) {
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

            $data['metadata']['notification_url'] = $this->_storeMagerInterface->getStore()->getBaseUrl() . 'gerencianet/notification/updatestatus';

            if (isset($shippingAddress)) {
                $data['shippings'][0]['name'] = $shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname();
                $data['shippings'][0]['value'] = $order->getShippingAmount() * 100;

                $street = $shippingAddress->getStreet();
                $data['payment']['credit_card']['customer']['address']['street'] = $street[0];
                $data['payment']['credit_card']['customer']['address']['number'] = $street[1];
                if (isset($street[3])) {
                    $data['payment']['credit_card']['customer']['address']['complement'] = $street[2];
                    $data['payment']['credit_card']['customer']['address']['neighborhood'] = $street[3];
                } else {
                    $data['payment']['credit_card']['customer']['address']['neighborhood'] = $street[2];
                }
                $cep = preg_replace('/[^0-9]/', '', $shippingAddress->getPostcode());
                $data['payment']['credit_card']['customer']['address']['zipcode'] = $cep;
                $data['payment']['credit_card']['customer']['address']['city'] = $shippingAddress->getCity();
                $data['payment']['credit_card']['customer']['address']['state'] = $shippingAddress->getRegionCode();
            }

            $data['payment']['credit_card']['customer']['name'] = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
            $data['payment']['credit_card']['customer']['email'] = $billingaddress->getEmail();
            if ($paymentInfo['documentType'] == "CPF") {
                $data['payment']['credit_card']['customer']['cpf'] = $paymentInfo['cpfCustomer'];
            } else if ($paymentInfo['documentType'] == "CNPJ") {
                $data['payment']['credit_card']['customer']['juridical_person']['corporate_name'] = $paymentInfo['companyName'];
                $data['payment']['credit_card']['customer']['juridical_person']['cnpj'] = $paymentInfo['cpfCustomer'];
            }
            $data['payment']['credit_card']['customer']['birth'] = date("Y-m-d", strtotime($order->getCustomerDob()));

            $billingAddPhone = $this->formatPhone($billingaddress->getTelephone());
            $data['payment']['credit_card']['customer']['phone_number'] = $paymentInfo['phone'] ?? $billingAddPhone;

            $street = $billingaddress->getStreet();
            $data['payment']['credit_card']['billing_address']['street'] = $street[0];
            $data['payment']['credit_card']['billing_address']['number'] = $street[1];
            if (isset($street[3])) {
                $data['payment']['credit_card']['billing_address']['complement'] = $street[2];
                $data['payment']['credit_card']['billing_address']['neighborhood'] = $street[3];
            } else {
                $data['payment']['credit_card']['billing_address']['neighborhood'] = $street[2];
            }
            $cep = preg_replace('/[^0-9]/', '', $billingaddress->getPostcode());
            $data['payment']['credit_card']['billing_address']['zipcode'] = (string)$cep;
            $data['payment']['credit_card']['billing_address']['city'] = $billingaddress->getCity();
            $data['payment']['credit_card']['billing_address']['state'] = $billingaddress->getRegionCode();

            $discountValue = str_replace("-", "", $order->getDiscountAmount());
            if ($discountValue > 0) {
                $data['payment']['credit_card']['discount']['type'] = 'currency';
                $data['payment']['credit_card']['discount']['value'] = $discountValue * 100;
            }
            $data['payment']['credit_card']['installments'] = (int)$paymentInfo['installments'];

            $data['payment']['credit_card']['payment_token'] = $paymentInfo['cardHash'];

            $api = new Gerencianet($options);
            $pay_charge = $api->oneStep([], $data);
            $order->setCustomerTaxvat($paymentInfo['cpfCustomer']);
            $order->setGerencianetTransactionId($pay_charge['data']['charge_id']);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    public function assignData(DataObject $data)
    {
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('cpfCustomer', $data['additional_data']['cpfCustomer'] ?? null);
        $info->setAdditionalInformation('cardHash', $data['additional_data']['cc_card_hash'] ?? null);
        $info->setAdditionalInformation('companyName', $data['additional_data']['companyName'] ?? null);
        $info->setAdditionalInformation('documentType', $data['additional_data']['documentType'] ?? null);
        $info->setAdditionalInformation('installments', $data['additional_data']['cc_installments'] ?? 1);
        $info->setAdditionalInformation('phone', $data['additional_data']['cc_phone'] ?? null);
        return $this;
    }

    public function isAvailable(CartInterface $quote = null)
    {
        $total = $this->_checkoutSession->getQuote()->getGrandTotal();
        return ($this->_helperData->isCreditCardActive() && $total >= 5) ? true : false;
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
