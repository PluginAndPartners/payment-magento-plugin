/**
 * Copyright © MercadoPago. All rights reserved.
 *
 * @author      Bruno Elisei <brunoelisei@o2ti.com>
 * @license     See LICENSE for license details.
 */

define(
    [
        'MercadoPago_PaymentMagento/js/view/checkout/cart/summary/twocc_data'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            /**
             * @override
             *
             * @returns {boolean}
             */
            isDisplayed() {
                return this.getPureValue() !== 0;
            }
        });
    }
);