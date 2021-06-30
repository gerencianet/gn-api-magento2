define([
	"underscore",
	"Magento_Checkout/js/view/payment/default",
	"Magento_Payment/js/model/credit-card-validation/credit-card-data",
	"Magento_Payment/js/model/credit-card-validation/credit-card-number-validator",
	"jquery",
	"Magento_Ui/js/model/messageList",
	"Magento_Checkout/js/model/quote",
	], 
	function (
	_,
	Component,
	creditCardData,
	cardNumberValidator,
	$,
	messageList,
	quote
	) {
		"use strict";
		return Component.extend({
			defaults: {
				template: "Gerencianet_Magento2/payment/creditcard",
				creditCardType: "",
				creditCardExpYear: "",
				creditCardExpMonth: "",
				creditCardNumber: "",
				creditCardVerificationNumber: "",
				creditCardOwnerCpf: "",
				creditCardInstallments: "",
				creditCardMobilePhone: "",
				creditCardOwnerName: "",
				selectedCardType: null,
				cardHash: "",
				documentType: null,
				companyName: "",
			},

			initObservable: function () {
				this._super().observe([
					"creditCardType",
					"creditCardExpYear",
					"creditCardExpMonth",
					"creditCardNumber",
					"creditCardVerificationNumber",
					"creditCardOwnerName",
					"creditCardOwnerCpf",
					"creditCardMobilePhone",
					"creditCardInstallments",
					"selectedCardType",
					"cardHash",
					"documentType",
					"companyName",
				]);
				return this;
			},

			initialize: function () {
				var self = this;
				this._super();

				const identificadorConta = window.checkoutConfig.payment.cc.identificadorConta;
				const url = window.checkoutConfig.payment.cc.urlGerencianet;

				//Set credit card number to credit card data object
				this.creditCardNumber.subscribe(function (value) {

					if (value == "" || value == null) {
						return false;
					}

					var result = cardNumberValidator(value);

					if (result.isPotentiallyValid && result.isValid) {
						function handleInstallments(result) {
							var total = quote.totals().grand_total * 100;
							var ccbrand = result.card.title.toLowerCase();

							fetch(`/gerencianet/installments/index?total=${total}&brand=${ccbrand}`)
							.then((response) => response.json())
							.then((installments) => {
								const data = installments.data.installments;
								let values = [];

								data.forEach((item, key) => {
									var parcela = item.installment;
									var valorParcela = (item.value / 100 )
														.toLocaleString("pt-br", {
															style: "currency",
															currency: "BRL",
														});
									var total = ((item.value * item.installment) / 100)
												.toLocaleString("pt-br", {
													style: "currency",
													currency: "BRL",
												});

									values[key] = parcela + "x de R$ " + valorParcela + ". Total: R$ " + total;
								});

								var select = document.getElementById("gerencianet_cc_payment_profile_id");

								if (select !== null) {
									for (var i = 0; i < select.length; i++) {
										select.remove(i);
									}
									
									values.forEach((value, key) => {
										var opt = document.createElement("option");
										opt.value = key + 1;
										opt.innerHTML = value;
										select.appendChild(opt);
									});
								}
							});
						}
						handleInstallments(result);
					} else {
						return false;
					}

					if (result.card !== null) {
						if (isGenerateCardHash()) {
							self.getCardHash(identificadorConta, url);
						}
						self.selectedCardType(result.card.type);
						creditCardData.creditCard = result.card;
					}

					if (result.isValid) {
						if (isGenerateCardHash()) {
							self.getCardHash(identificadorConta, url);
						}
						creditCardData.creditCardNumber = value;
						self.creditCardType(result.card.type);
					}

					if (isGenerateCardHash()) {
						self.getCardHash(identificadorConta, url);
					}
				});

				//Set expiration year to credit card data object
				this.creditCardExpYear.subscribe(function (value) {
					if (isGenerateCardHash()) {
						self.getCardHash(identificadorConta, url);
					}
					creditCardData.expirationYear = value;
				});

				//Set expiration month to credit card data object
				this.creditCardExpMonth.subscribe(function (value) {
					if (isGenerateCardHash()) {
						self.getCardHash(identificadorConta, url);
					}
					creditCardData.expirationMonth = value;
				});

				//Set cvv code to credit card data objtotalect
				this.creditCardVerificationNumber.subscribe(function (value) {
					if (isGenerateCardHash()) {
						self.getCardHash(identificadorConta, url);
					}
					creditCardData.cvvCode = value;
				});

				this.creditCardOwnerCpf.subscribe(function () {
					if (isGenerateCardHash()) {
						self.getCardHash(identificadorConta, url);
					}

					var element = document.getElementById("gerencianet_company_name");
					
					if (self.creditCardOwnerCpf().length <= 14) {
						element.style.display = "none";
					} else {
						element.style.display = null;
					}

					self.cardHash(
						document.getElementById("gerencianet_cc_card_hash").value
					);
				});

				this.creditCardMobilePhone.subscribe(function () {
					if (isGenerateCardHash()) {
						self.getCardHash(identificadorConta, url);
					}
					self.cardHash(
						document.getElementById("gerencianet_cc_card_hash").value
					);
				});

				this.creditCardOwnerName.subscribe(function () {
					if (isGenerateCardHash()) {
						self.getCardHash(identificadorConta, url);
					}
					self.cardHash(
						document.getElementById("gerencianet_cc_card_hash").value
					);
				});

				function isGenerateCardHash() {
					return (creditCardData.cvvCode && 
							creditCardData.expirationMonth && 
							creditCardData.expirationYear && 
							creditCardData.creditCardNumber) 
					? true 
					: false;
				}
			},

			getCardHash: function (identificadorConta, url) {
				this.loadGerencianet(identificadorConta, url);

				var callback = function (error, response) {
					if (error) {
						// Trata o erro ocorrido
						console.log("Erro ao Gerar Token de Pagamento: " + error);
					} else {
						// Insere o hash do cartao no input hidden
						document.getElementById("gerencianet_cc_card_hash").value = response.data.payment_token;
					}
				}

				var fnc = function (checkout) {
					console.log("entrou em 2");

					checkout.getPaymentToken( 
						{
							brand: creditCardData.creditCard.title.toLowerCase(), // bandeira do cartão
							number: creditCardData.creditCardNumber, // número do cartão
							cvv: creditCardData.cvvCode, // código de segurança
							expiration_month: creditCardData.expirationMonth, // mês de vencimento
							expiration_year: creditCardData.expirationYear, // ano de vencimento
						},
						callback
					);
				}

				$gn.ready(fnc);
			},

			loadGerencianet: function (identificadorConta, url) {
				if (!document.getElementById(identificadorConta)) {
					if (document.querySelector(".payment-method")) {
						const s2 = document.createElement("script");
						s2.id = "gerencianet";
						s2.async = false;

						document.getElementsByTagName("head")[0].appendChild(s2);
						document.getElementById("gerencianet").innerHTML = "";
						document.getElementById("gerencianet").innerHTML = 
							`var $gn = { 
								validForm: true, 
								processed: false, 
								done: {}, 
								ready: function (fn) { $gn.done = fn; } 
							};`;

						var v = parseInt(Math.random() * 1000000);
						const s = document.createElement("script");
						s.src = `${url}/v1/cdn/${identificadorConta}/` + v;
						s.async = false;
						s.id = identificadorConta;
					
						document.getElementsByTagName("head")[0].parentNode.childNodes[0].appendChild(s);
					}
				}
			},

			
			getData: function () {
				return {
					method: this.item.method,
					additional_data: {
						cc_cid: this.creditCardVerificationNumber(),
						cc_type: this.creditCardType(),
						cc_owner_name: this.creditCardOwnerName(),
						cpfCustomer: this.creditCardOwnerCpf().replace(/[^\d]+/g, ""),
						cc_installments: this.creditCardInstallments(),
						cc_phone: this.creditCardMobilePhone().replace(/[^\d]/g, ""),
						cc_card_hash: this.cardHash(),
						documentType: this.documentType(),
						companyName: this.companyName(),
					},
				};
			},
			
			isActive: function () { return true; },
			getCode: function () { return this.item.method; },
			getCcAvailableTypes: function () { return window.checkoutConfig.payment.cc; },
			getCcMonths: function () { return window.checkoutConfig.payment.cc.months["gerencianet_cc"]; },
			getCcYears: function () { return window.checkoutConfig.payment.cc.years["gerencianet_cc"]; },
			hasVerification: function () {return true; },
			getCvvImageUrl: function () { return window.checkoutConfig.payment.cc.cvvImageUrl; },

			getCvvImageHtml: function () {
				return (
				'<img src="' + this.getCvvImageUrl() +
					'" alt="' + _("Card Verification Number Visual Reference") +
					'" title="' + _("Card Verification Number Visual Reference") +
				'" />'
				);
			},

			getAvailableInstallments: function (key) {
				const keys = Object.keys(
					window.checkoutConfig.payment.cc.availableTypes.cc
				);
				const values = Object.values(
					window.checkoutConfig.payment.cc.availableTypes.cc
				);
				let type;
				keys.forEach((element, index) => {
					if (element === key) {
						type = values[index];
					}
				});
				return type;
			},

			validate: function () {
				let message = "";
				var isValid;

				if (!creditCardData.expirationYear || !creditCardData.expirationMonth) {
					messageList.addErrorMessage({
						message: "Data de expiração inválida ou não informada.",
					});
					return false;
				}

				if (!creditCardData.creditCardNumber) {
					messageList.addErrorMessage({
						message: "Por favor, informe um cartão de crédito válido.",
					});
					return false;
				}

				if ( creditCardData.creditCard.code.size !== creditCardData.cvvCode.length ) {
					messageList.addErrorMessage({
						message: "Código de verificação inválido.",
					});
					return false;
				}

				if (this.creditCardOwnerCpf().length <= 14) {
					isValid = this.validaCpf(this.creditCardOwnerCpf());
					message = "CPF";
					this.documentType(message);
				} else {
					isValid = this.validaCnpj(this.creditCardOwnerCpf());
					message = "CNPJ";
					this.documentType(message);
				}

				if (this.companyName() == "" && this.documentType == "CNPJ") {
					messageList.addErrorMessage({ message: "Razão social é obrigatório" });
					return false;
				}

				const creditCard = this.getCcAvailableTypesValues();

				return creditCard.map((key) => {
					let type = false;
					if (key.value === creditCardData.creditCard.type) type = true;
					return type;
				});
			},

			getIcons: function (type) {
				return window.checkoutConfig.payment.ccform.icons.hasOwnProperty(type)
				? window.checkoutConfig.payment.ccform.icons[type]
				: false;
			},

			getCcAvailableTypesValues: function () {
				let types = [];
				const keys = Object.keys(
					window.checkoutConfig.payment.cc.availableTypes.gerencianet_cc
				);

				const values = Object.values(
					window.checkoutConfig.payment.cc.availableTypes.gerencianet_cc
				);
				
				keys.forEach((element, index) => {
					types[index] = {
						value: element,
						type: values[index],
					};
				});

				return types;
			},

			getCcMonthsValues: function () {
				return _.map(
					this.getCcMonths(), 
					function (value, key) {
						return {
							value: key,
							month: value,
						};
					}
				);
			},

			getCcYearsValues: function () {
				return _.map(
					this.getCcYears(), 
					function (value, key) {
						return {
							value: key,
							year: value,
						};
					}
				);
			},

			validaCnpj: function (cnpj) {
				cnpj = cnpj.replace(/[^\d]+/g, "");

				if (cnpj == "") { return false };
				if (cnpj.length != 14) { return false };

				// Elimina CNPJs invalidos conhecidos
				if	(
					cnpj == "00000000000000" ||
					cnpj == "11111111111111" ||
					cnpj == "22222222222222" ||
					cnpj == "33333333333333" ||
					cnpj == "44444444444444" ||
					cnpj == "55555555555555" ||
					cnpj == "66666666666666" ||
					cnpj == "77777777777777" ||
					cnpj == "88888888888888" ||
					cnpj == "99999999999999"
					) {
						return false;
				}

				// Valida DVs
				var tamanho = cnpj.length - 2;
				var numeros = cnpj.substring(0, tamanho);
				var digitos = cnpj.substring(tamanho);
				var soma = 0;
				var pos = tamanho - 7;
				
				for (i = tamanho; i >= 1; i--) {
					soma += numeros.charAt(tamanho - i) * pos--;
					if (pos < 2) pos = 9;
				}

				var resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
				if (resultado != digitos.charAt(0)) { return false };

				tamanho = tamanho + 1;
				numeros = cnpj.substring(0, tamanho);
				soma = 0;
				pos = tamanho - 7;
				
				for (i = tamanho; i >= 1; i--) {
					soma += numeros.charAt(tamanho - i) * pos--;
					if (pos < 2) { pos = 9 };
				}

				resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
				if (resultado != digitos.charAt(1)) { return false };

				return true;
			},

			validaCpf: function (cpf) {
				var cpf;
				var i;
				var add;
				var rev;

				cpf = cpf.replace(/[^\d]+/g, "");

				if (cpf == "") { 
					return false 
				};
				
				// Elimina CPFs invalidos conhecidos
				if	(cpf.length != 11 ||
					cpf == "00000000000" ||
					cpf == "11111111111" ||
					cpf == "22222222222" ||
					cpf == "33333333333" ||
					cpf == "44444444444" ||
					cpf == "55555555555" ||
					cpf == "66666666666" ||
					cpf == "77777777777" ||
					cpf == "88888888888" ||
					cpf == "99999999999" ) {
						return false; 
				}
				
				// Valida 1o digito
				add = 0;
				for (i = 0; i < 9; i++) { 
					add += parseInt(cpf.charAt(i)) * (10 - i) 
				};
				
				rev = 11 - (add % 11);
				if (rev == 10 || rev == 11) { 
					rev = 0 
				};
				
				if (rev != parseInt(cpf.charAt(9))) { 
					return false 
				};
				
				// Valida 2o digito
				add = 0;
				for (i = 0; i < 10; i++) { 
					add += parseInt(cpf.charAt(i)) * (11 - i) 
				};
				
				rev = 11 - (add % 11);
				if (rev == 10 || rev == 11) { 
					rev = 0 
				};
				
				if (rev != parseInt(cpf.charAt(10))) { 
					return false 
				};
				
				return true;
			},
		});
	}
);
