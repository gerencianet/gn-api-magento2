define(
  [
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
  ],
  function (
    Component,
    rendererList
  ) {
    'use strict';
    rendererList.push(
      {
        type: 'gerencianet_pix',
        component: 'Gerencianet_Magento2/js/view/payment/method-renderer/gerencianet_pix'
      }
    );
    return Component.extend({});
  }
);
