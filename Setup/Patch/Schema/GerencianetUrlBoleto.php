<?php

namespace Gerencianet\Magento2\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class GerencianetUrlBoleto implements SchemaPatchInterface {

  /** @var ModuleDataSetupInterface */
  private $moduleDataSetup;

  public function __construct(ModuleDataSetupInterface $moduleDataSetup) {
    $this->moduleDataSetup = $moduleDataSetup;
  }

  public static function getDependencies() {
    return [];
  }

  public function getAliases() {
    return [];
  }

  public function apply() {
    $this->moduleDataSetup->startSetup();

    $this->moduleDataSetup->getConnection()->addColumn(
      $this->moduleDataSetup->getTable('sales_order'),
      'gerencianet_url_boleto',
      [
        'type' => Table::TYPE_TEXT,
        'length' => 255,
        'nullable' => true,
        'comment'  => 'URL do Boleto do Gerencianet',
      ]
    );


    $this->moduleDataSetup->endSetup();
  }
}
