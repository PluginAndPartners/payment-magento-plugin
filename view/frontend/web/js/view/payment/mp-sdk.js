// noinspection DuplicatedCode

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/* @api */
define([
    'underscore',
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'MercadoPago_PaymentMagento/js/view/payment/method-renderer/validate-form-security',
    'mage/url',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/url-builder',
    'MercadoPago_PaymentMagento/js/action/checkout/set-finance-cost',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
], function (
    _,
    $,
    Component,
    quote,
    validateFormSF,
    urlFormatter,
    fullScreenLoader,
    urlBuilder,
    setFinanceCost,
    messageList,
    $t,
) {
    'use strict';

    return Component.extend({

        defaults: {
            mpCardForm: {},
            fields: {},
            installmentWasCalculated: false,
            generatedCards: [],
            // html fields
            mpCardHolderName: '',
            mpPayerDocument: '',
            mpPayerType: '',
            mpCardListInstallments: '',
            mpCardInstallment: '',
            mpPayerOptionsTypes: '',
            mpSelectedCardType: '',
            mpCardType: '',
            mpCardBin: '',
            mpCardPublicId: '',
            mpUserId: '',
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super()
                .observe([
                    'mpCardHolderName',
                    'mpPayerDocument',
                    'mpPayerType',
                    'mpCardListInstallments',
                    'mpCardInstallment',
                    'mpPayerOptionsTypes',
                    'mpSelectedCardType',
                    'mpCardType',
                    'mpCardBin',
                    'installmentWasCalculated',
                    'mpCardPublicId',
                    'mpUserId',
                ]);
            return this;
        },

        /**
         * Init component
         */
        initialize: function () {
            let self = this,
                defaultTypeDocument;

            this._super();

            self.active.subscribe((value) => {
                if (value === true) {
                    self.getSelectDocumentTypes();
                }
            });

            self.mpCardBin.subscribe((value) => {
                self.getListOptionsToInstallments(self.amount());
            });

            self.mpCardInstallment.subscribe((value) => {
                self.addFinanceCost();
            });

            self.mpPayerDocument.subscribe((value) => {
                if (self.getMpSiteId() === 'MLB' && value) {
                    defaultTypeDocument = value.replace(/\D/g, '').length <= 11 ? 'CPF' : 'CNPJ';
                    self.mpPayerType(defaultTypeDocument);
                }
            });
        },

        /**
         * Un Mount Cart Form
         * @return {void}
         */
        resetCardForm() {
            self.mpCardForm.cardNumber.unmount();
            self.mpCardForm.securityCode.unmount();
            self.mpCardForm.expirationMonth.unmount();
            self.mpCardForm.expirationYear.unmount();
            self.mpCardForm = {};
            self.fields = {};
            self.installmentWasCalculated(false);
            self.mpCardHolderName('');
            self.mpPayerType('');
        },

        /**
         * Mount Cart Form
         * @return {void}
         */
        mountCardForm({fieldCcNumber, fieldSecurityCode, fieldExpMonth, fieldExpYear}) {
            let self = this,
                styleField = {
                    height: '100%',
                    padding: '30px 15px'
                },
                codeCardtype;

            self.fields = {fieldCcNumber, fieldSecurityCode, fieldExpMonth, fieldExpYear};

            self.mpCardForm = {
                cardNumber: window.mp.fields.create('cardNumber', {style: styleField}),
                securityCode: window.mp.fields.create('securityCode', {style: styleField}),
                expirationMonth: window.mp.fields.create('expirationMonth', {style: styleField}),
                expirationYear: window.mp.fields.create('expirationYear', {style: styleField}),
            };

            self.mpCardForm.cardNumber
                .mount(fieldCcNumber)
                .on('error', () => {
                    self.mountCardForm({fieldCcNumber, fieldSecurityCode, fieldExpMonth, fieldExpYear});
                })
                .on('binChange', (event) => {
                    if (event.bin) {
                        if (event.bin.length === 8) {
                            self.mpCardBin(event.bin);
                            window.mp.getPaymentMethods({bin: event.bin}).then((binDetails) => {
                                codeCardtype = self.getCodeCardType(binDetails.results[0].id);
                                self.mpSelectedCardType(codeCardtype);
                                self.mpCardType(codeCardtype);
                            });
                        }
                    }
                })
                .on('blur', () => {
                    validateFormSF.removeClassesIfEmpyt(fieldCcNumber);
                })
                .on('focus', () => {
                    validateFormSF.toogleFocusStyle(fieldCcNumber);
                })
                .on('validityChange', (event) => {
                    validateFormSF.toogleValidityState(fieldCcNumber, event.errorMessages);
                });

            self.mpCardForm.securityCode
                .mount(fieldSecurityCode)
                .on('error', () => {
                    self.mountCardForm({fieldCcNumber, fieldSecurityCode, fieldExpMonth, fieldExpYear});
                })
                .on('blur', () => {
                    validateFormSF.removeClassesIfEmpyt(fieldSecurityCode);
                })
                .on('focus', () => {
                    validateFormSF.toogleFocusStyle(fieldSecurityCode);
                })
                .on('validityChange', (event) => {
                    validateFormSF.toogleValidityState(fieldSecurityCode, event.errorMessages);
                });

            self.mpCardForm.expirationMonth
                .mount(fieldExpMonth)
                .on('error', () => {
                    self.mountCardForm({fieldCcNumber, fieldSecurityCode, fieldExpMonth, fieldExpYear});
                })
                .on('blur', () => {
                    validateFormSF.removeClassesIfEmpyt(fieldExpMonth);
                })
                .on('focus', () => {
                    validateFormSF.toogleFocusStyle(fieldExpMonth);
                })
                .on('validityChange', (event) => {
                    validateFormSF.toogleValidityState(fieldExpMonth, event.errorMessages);
                });

            self.mpCardForm.expirationYear
                .mount(fieldExpYear)
                .on('error', () => {
                    self.mountCardForm({fieldCcNumber, fieldSecurityCode, fieldExpMonth, fieldExpYear});
                })
                .on('blur', () => {
                    validateFormSF.removeClassesIfEmpyt(fieldExpYear);
                })
                .on('focus', () => {
                    validateFormSF.toogleFocusStyle(fieldExpYear);
                })
                .on('validityChange', (event) => {
                    validateFormSF.toogleValidityState(fieldExpYear, event.errorMessages);
                })
                .on('ready', () => {
                    self.isLoading(false);
                });
        },

        async generateToken() {
            var self = this,
                isVaultEnabled = this.vaultEnabler.isVaultEnabled(),
                saveCard = this.vaultEnabler.isActivePaymentTokenEnabler(),
                quoteId = quote.getQuoteId(),
                unsupportedPreAuth = self.getUnsupportedPreAuth(),
                mpSiteId = self.getMpSiteId();

            if (unsupportedPreAuth[mpSiteId].includes(self.mpCardType())) {
                isVaultEnabled = false;
                saveCard = false;
            }

            if (self.mpPayerDocument) {
                self.mpPayerDocument(self.mpPayerDocument().replace(/\D/g, ''));
            }

            fullScreenLoader.startLoader();

            const payload = {
                cardholderName: self.mpCardHolderName(),
                identificationType: self.mpPayerType(),
                identificationNumber: self.mpPayerDocument(),
            };

            try {
                const token = await window.mp.fields.createCardToken(payload);

                fullScreenLoader.stopLoader();

                if (saveCard && isVaultEnabled) {
                    fullScreenLoader.startLoader();

                    const serviceUrl = urlBuilder.createUrl('/carts/mine/mp-create-vault', {});

                    const payloadCreateVault = {
                        cartId: quoteId,
                        vaultData: {
                            token: token.id,
                            identificationNumber: self.mpPayerDocument(),
                            identificationType: self.mpPayerType(0),
                        }
                    };

                    try {
                        const response = await $.ajax({
                            url: urlFormatter.build(serviceUrl),
                            data: JSON.stringify(payloadCreateVault),
                            global: true,
                            contentType: 'application/json',
                            type: 'POST',
                            async: false
                        });

                        self.mpCardPublicId(response[0].card_id);
                        self.mpUserId(response[0].mp_user_id);

                        fullScreenLoader.stopLoader();

                    } catch (e) {
                        fullScreenLoader.stopLoader();
                    }
                }

                self.generatedCards.push({
                    token,
                    cardNumber: token.first_six_digits + 'xxxxxx' + token.last_four_digits,
                    cardExpirationYear: token.expiration_year,
                    cardExpirationMotn: token.expiration_month,
                    cardPublicId: self.mpCardPublicId(),
                    cardType: self.mpCardType(),
                    documentType: self.mpPayerType(),
                    documentValue: self.mpPayerDocument(),
                    mpUserId: self.mpUserId(),
                    holderName: self.mpCardHolderName(),
                    cardInstallment: self.mpCardInstallment(),
                });
            } catch(e) {
                messageList.addErrorMessage({
                    message: $t('Unable to make payment, check card details.')
                });
                fullScreenLoader.stopLoader();
            }
        },

        /**
         * Display Error in Field
         * @param {Array} error
         * @return {void}
         */
        displayErrorInField(error) {
            let field = error.field,
                msg = error.message,
                fieldsMage = {
                    cardNumber: this.fields.fieldCcNumber,
                    securityCode: this.fields.fieldSecurityCode,
                    expirationMonth: this.fields.fieldExpMonth,
                    expirationYear: this.fields.fieldExpYear,
                };

            validateFormSF.singleToogleValidityState(fieldsMage[field], msg);
        },

        /**
         * Get Select Document Types
         * @returns {void}
         */
        async getSelectDocumentTypes() {
            const self = this;

            self.mpPayerOptionsTypes(await window.mp.getIdentificationTypes());

            if (quote.billingAddress()) {
                const vatId = quote.billingAddress().vatId;
                if (vatId) {
                    self.mpPayerDocument(vatId);
                }
            }
        },

        /**
         * Get List Options to Instalments
         * @returns {Array}
         */
        getListOptionsToInstallments(amount) {
            var self = this,
                installments = {},
                ccNumber = self.mpCardBin(),
                bin = ccNumber;

            self.installmentWasCalculated(false);

            if (bin.length === 8) {
                window.mp.getInstallments({
                    amount: String(self.FormattedCurrencyToInstallments(amount)),
                    bin: bin
                }).then((result) => {
                    self.installmentWasCalculated(true);
                    self.mpCardListInstallments(result[0].payer_costs);
                });
            }

            return installments;
        },

        /**
         * Add Text for Installments
         * @param {Array} labels
         * @return {void}
         */
        addTextForInstallment(labels) {
            var self = this,
                texts;

            self.installmentTextInfo(true);

            _.map(labels, (label) => {
                texts = label.split('|');
                _.map(texts, (text) => {
                    if (text.includes('TEA')) {
                        self.installmentTextTEA(text.replace('_', ' '));
                    }
                    if (text.includes('CFT')) {
                        self.installmentTextCFT(text.replace('_', ' '));
                    }
                });
            });
        },

        /**
         * Get Validation For Document.
         * @returns {Array}
         */
        getValidationForDocument() {
            let self = this,
                mpSiteId = self.getMpSiteId();

            if (mpSiteId === 'MLB') {
                return {
                    'required': true,
                    'mp-validate-document-identification': '#' + self.getCode() + '_document_identification'
                };
            }
            return {'required': true};
        },

        /**
         * Get Code Card Type.
         * @param {String} cardTypeName
         * @returns {String}
         */
        getCodeCardType(cardTypeName) {
            return cardTypeName;
        },

        /**
         * Get list of available credit card types
         * @returns {Object}
         */
        getCcAvailableTypes: function () {
            return window.checkoutConfig.payment.ccform.availableTypes[this.getCode()];
        },

        /**
         * Get payment icons
         * @param {String} type
         * @returns {Boolean}
         */
        getIcons: function (type) {
            return window.checkoutConfig.payment.mercadopago_paymentmagento_cc.icons.hasOwnProperty(type) ?
                window.checkoutConfig.payment.mercadopago_paymentmagento_cc.icons[type]
                : false;
        },

        /**
         * Get list of months
         * @returns {Object}
         */
        getCcMonths: function () {
            return window.checkoutConfig.payment.ccform.months['cc'];
        },

        /**
         * Get list of years
         * @returns {Object}
         */
        getCcYears: function () {
            return window.checkoutConfig.payment.ccform.years['cc'];
        },

        /**
         * Get list of available credit card types values
         * @returns {Object}
         */
        getCcAvailableTypesValues: function () {
            return _.map(this.getCcAvailableTypes(), function (value, key) {
                return {
                    'value': key,
                    'type': value
                };
            });
        },

        /**
         * Get list of available month values
         * @returns {Object}
         */
        getCcMonthsValues: function () {
            return _.map(this.getCcMonths(), function (value, key) {
                return {
                    'value': key,
                    'month': value
                };
            });
        },

        /**
         * Get list of available year values
         * @returns {Object}
         */
        getCcYearsValues: function () {
            return _.map(this.getCcYears(), function (value, key) {
                return {
                    'value': key,
                    'year': value
                };
            });
        },

        /**
         * Get available credit card type by code
         * @param {String} code
         * @returns {String}
         */
        getCcTypeTitleByCode: function (code) {
            var title = '',
                keyValue = 'value',
                keyType = 'type';

            _.each(this.getCcAvailableTypesValues(), function (value) {
                if (value[keyValue] === code) {
                    title = value[keyType];
                }
            });

            return title;
        },

        /**
         * Get credit card details
         * @returns {Array}
         */
        getInfo: function () {
            return [
                {
                    'name': 'Credit Card Type', value: this.getCcTypeTitleByCode(this.mpCardType())
                },
                {
                    'name': 'Credit Card Number', value: this.mpCardNumber()
                }
            ];
        },

        /**
         * Is document identification capture
         * @returns {Boolean}
         */
        DocumentIdentificationCapture() {

            if (this.getMpSiteId() === 'MLM') {
                return false;
            }

            if (this.getMpSiteId() !== 'MLB') {
                return true;
            }

            if (!quote.billingAddress()) {
                return window.checkoutConfig.payment[this.getCode()].document_identification_capture;
            }

            if (!quote.billingAddress().vatId) {
                return true;
            }

            return window.checkoutConfig.payment[this.getCode()].document_identification_capture;
        },

        /**
         * Get logo
         * @returns {String}
         */
        getLogo() {
            return window.checkoutConfig.payment[this.getCode()].logo;
        },

        /**
         * Get title
         * @returns {String}
         */
        getTitle() {
            return window.checkoutConfig.payment[this.getCode()].title;
        },

        /**
         * Get Payment Id Method
         * @returns {String}
         */
        getPaymentIdMethod() {
            return window.checkoutConfig.payment[this.getCode()].payment_method_id;
        },

        /**
         * Get Expiration
         * @returns {String}
         */
        getExpiration() {
            return window.checkoutConfig.payment[this.getCode()].expiration;
        },

        /**
         * Get Mp Site Id
         * @returns {String}
         */
        getMpSiteId() {
            return window.checkoutConfig.payment['mercadopago_paymentmagento'].mp_site_id;
        },

        addFinanceCost() {
            var self = this,
                selectInstallment = self.mpCardInstallment(),
                rulesForFinanceCost = self.mpCardListInstallments();

            if (self.getMpSiteId() === 'MLA') {
                _.map(rulesForFinanceCost, (keys) => {
                    if (keys.installments === selectInstallment) {
                        self.addTextForInstallment(keys.labels);
                    }
                });
            }

            setFinanceCost.financeCost(selectInstallment, rulesForFinanceCost);
        },

        /**
         * Formatted Currency to Installments
         * @param {Float} amount
         * @return {Boolean}
         */
        FormattedCurrencyToInstallments(amount) {
            if (this.getMpSiteId() === 'MCO') {
                return parseFloat(amount).toFixed(0);
            }
            return amount;
        },

    });
});
