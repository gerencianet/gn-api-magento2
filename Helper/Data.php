<?php

namespace Gerencianet\Magento2\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{

	const METHOD_CODE_CREDIT_CARD = 'gerencianet_cc';
	const METHOD_CODE_BILLET = 'gerencianet_boleto';

	const URL_SANDBOX = 'https://sandbox.gerencianet.com.br';
	const URL_PRODUCTION = 'https://api.gerencianet.com.br';


	/** @var EncryptorInterface */
	protected $_encryptor;

	public function __construct(Context $context, EncryptorInterface $encryptor)
	{
		parent::__construct($context);
		$this->_encryptor = $encryptor;
	}

	/** 
	 * @param string $path
	 * Retorna o a configuração informada no Path 
	 */
	protected function getConfig($path)
	{
		$storeScope = ScopeInterface::SCOPE_STORE;
		return $this->scopeConfig->getValue($path, $storeScope);
	}

	public function getOptions()
	{
		$options = [];
		if ($this->getConfig('payment/gerencianet_configuracoes/ambiente') == 'developer') {
			$options = [
				'client_id' => $this->getConfig('payment/gerencianet_configuracoes/gerencianet_credenciais_develop/client_id'),
				'client_secret' => $this->getConfig('payment/gerencianet_configuracoes/gerencianet_credenciais_develop/client_secret'),
				'sandbox' => true
			];
		} else if ($this->getConfig('payment/gerencianet_configuracoes/ambiente') == 'production') {
			$options = [
				'client_id' => $this->getConfig('payment/gerencianet_configuracoes/gerencianet_credenciais_production/client_id'),
				'client_secret' => $this->getConfig('payment/gerencianet_configuracoes/gerencianet_credenciais_production/client_secret'),
				'sandbox' => false
			];
		}
		$partnerToken = $this->getConfig('payment/gerencianet_configuracoes/partner_token');
		if ($partnerToken != "") {
			$options['partner_token'] = $partnerToken;
		}
		return $options;
	}

	public function getSkipMtls()
	{
		return $this->getConfig('payment/gerencianet_configuracoes/mtls');
	}

	public function getBilletInstructions()
	{
		for ($i = 1; $i <= 4; $i++) {
			$data[] = $this->getConfig("payment/gerencianet_boleto/gerencianet_instrucoes_boleto/linha$i");
		}
		$response = '';
		if (isset($data)) {
			$response = implode("\n", $data);
		}
		return $response;
	}

	public function getBilletSettings()
	{
		$configurations = [];
		$configurations = [ // configurações de juros e mora
			'fine' => $this->getConfig('payment/gerencianet_boleto/multa') * 100,
			'interest' => $this->getConfig('payment/gerencianet_boleto/juros') * 100
		];
		return $configurations;
	}

	public function isBilletActive()
	{
		return $this->getConfig('payment/gerencianet_boleto/active');
	}

	public function isCreditCardActive()
	{
		return $this->getConfig('payment/gerencianet_cc/active');
	}

	public function isPixActive()
	{
		return $this->getConfig('payment/gerencianet_pix/active');
	}

	/** Escreve um log na pasta var */
	public function logger($string)
	{
		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/gerencianet_magento2.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer);
		$logger->info(json_encode($string));
	}

	public function getPrecoMinimo()
	{
		return $this->getConfig('payment/gerencianet_cc/price_min');
	}

	public function getPixCert()
	{
		return $this->getConfig('payment/gerencianet_pix/certificado');
	}

	public function getChavePix()
	{
		return $this->getConfig('payment/gerencianet_pix/chave_pix');
	}

	public function getStoreName()
	{
		return $this->getConfig('general/store_information/name');
	}

	public function getIdentificadorConta()
	{
		return $this->getConfig('payment/gerencianet_configuracoes/identificador_conta');
	}

	public function getUrl()
	{
		if ($this->getConfig('payment/gerencianet_configuracoes/ambiente') == 'developer') {
			return self::URL_SANDBOX;
		} else {
			return self::URL_PRODUCTION;
		}
	}

	public function getOrderStatus()
	{
		return $this->getConfig('payment/gerencianet_configuracoes/order_status');
	}
}
