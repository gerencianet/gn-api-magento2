<?php

namespace Gerencianet\Magento2\Model\Adminhtml\Source;

/**
 * Class Ambiente
 */
class Ambiente {

  const PRODUCTION = 'production';
  const DEVELOPER = 'developer';

  /**
   * {@inheritdoc}
   */
  public function toOptionArray() {
    return [
      [
        'value' => self::DEVELOPER,
        'label' => __('Desenvolvimento')
      ],
      [
        'value' => self::PRODUCTION,
        'label' => __('Produção')
      ],
    ];
  }
}
