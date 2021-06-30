define(
  [
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'Magento_Ui/js/model/messageList'
  ],
  function (Component, $, messageList) {
    'use strict';
    return Component.extend({
      defaults: {
        template: 'Gerencianet_Magento2/payment/boleto',
        boletoOwnerCpf: '',
        documentType: '',
        companyName: ''
      },

      initObservable: function () {
        this._super()
          .observe([
            'boletoOwnerCpf',
            'documentType',
            'companyName'
          ]);
        return this;
      },

      initialize: function () {
        var self = this;
        this._super();
        self.boletoOwnerCpf.subscribe(function () {
          var element = document.getElementById('gerencianet_boleto_company_name');
          if (self.boletoOwnerCpf().length <= 14) {
            element.style.display = 'none';
          } else {
            element.style.display = null;
          }
        })
      },

      validate: function () {
        var isValid;
        var message;
        if (this.boletoOwnerCpf().length <= 14) {
          isValid = this.validaCpf(this.boletoOwnerCpf());
          message = 'CPF';
        } else {
          isValid = this.validaCnpj(this.boletoOwnerCpf());
          message = 'CNPJ';
        }
        this.documentType(message);

        if (!isValid) {
          messageList.addErrorMessage({
            message: message + " inválido"
          });
          return false;
        }

        if (this.companyName() == '' && this.documentType == 'CNPJ') {
          messageList.addErrorMessage({
            message: "Razão social é obrigatório"
          });
          return false;
        }

        var $form = $('#' + this.getCode() + '-form');
        return $form.validation() && $form.validation('isValid') && isValid;
      },

      getData: function () {
        return {
          'method': this.item.method,
          'additional_data': {
            'cpfCustomer': this.boletoOwnerCpf().replace(/[^\d]+/g, ''),
            'documentType': this.documentType(),
            'companyName': this.companyName()
          }
        };
      },

      getCode: function () {
        return this.item.method;
      },

      validaCnpj: function (cnpj) {
        cnpj = cnpj.replace(/[^\d]+/g, '');

        if (cnpj == '') return false;

        if (cnpj.length != 14)
          return false;

        // Elimina CNPJs invalidos conhecidos
        if (cnpj == "00000000000000" ||
          cnpj == "11111111111111" ||
          cnpj == "22222222222222" ||
          cnpj == "33333333333333" ||
          cnpj == "44444444444444" ||
          cnpj == "55555555555555" ||
          cnpj == "66666666666666" ||
          cnpj == "77777777777777" ||
          cnpj == "88888888888888" ||
          cnpj == "99999999999999")
          return false;

        // Valida DVs
        var tamanho = cnpj.length - 2
        var numeros = cnpj.substring(0, tamanho);
        var digitos = cnpj.substring(tamanho);
        var soma = 0;
        var pos = tamanho - 7;
        for (let i = tamanho; i >= 1; i--) {
          soma += numeros.charAt(tamanho - i) * pos--;
          if (pos < 2)
            pos = 9;
        }
        var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(0))
          return false;

        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;
        for (let i = tamanho; i >= 1; i--) {
          soma += numeros.charAt(tamanho - i) * pos--;
          if (pos < 2)
            pos = 9;
        }
        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(1))
          return false;

        return true;
      },

      validaCpf: function (cpf) {
        var cpf;
        var i;
        var add;
        var rev;
        cpf = cpf.replace(/[^\d]+/g, '');
        if (cpf == '') return false;
        // Elimina CPFs invalidos conhecidos	
        if (cpf.length != 11 ||
          cpf == "00000000000" ||
          cpf == "11111111111" ||
          cpf == "22222222222" ||
          cpf == "33333333333" ||
          cpf == "44444444444" ||
          cpf == "55555555555" ||
          cpf == "66666666666" ||
          cpf == "77777777777" ||
          cpf == "88888888888" ||
          cpf == "99999999999")
          return false;
        // Valida 1o digito	
        add = 0;
        for (i = 0; i < 9; i++)
          add += parseInt(cpf.charAt(i)) * (10 - i);
        rev = 11 - (add % 11);
        if (rev == 10 || rev == 11)
          rev = 0;
        if (rev != parseInt(cpf.charAt(9)))
          return false;
        // Valida 2o digito	
        add = 0;
        for (i = 0; i < 10; i++)
          add += parseInt(cpf.charAt(i)) * (11 - i);
        rev = 11 - (add % 11);
        if (rev == 10 || rev == 11)
          rev = 0;
        if (rev != parseInt(cpf.charAt(10)))
          return false;
        return true;
      }
    }
    );
  }
);