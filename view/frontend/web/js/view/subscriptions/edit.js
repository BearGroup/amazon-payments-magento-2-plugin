/*jshint jquery:true*/
define([
    'jquery',
    'Magento_Ui/js/modal/confirm'
], function($, confirmation) {
    "use strict";

    return function (widget) {
        $.widget('mage.subscriptionsEdit', widget, {
            _create: function() {
                var self = this;
                this.options.isAmazonPaySubscription = this._isAmazonPaySelected();

                this.element.find('.action.save.primary')
                    .on('click', function (event) {
                        self._handleSubscriptionSave.call(self, event);
                    });

                return this._super();
            },

            _isAmazonPaySelected: function () {
                return this.element.find(this.options.paymentSelector)
                    .find(':selected')
                    .data('method') === 'amazon_payment_v2';
            },

            _handleSubscriptionSave: function (event) {
                var self = this;
                if (this.options.isAmazonPaySubscription && !this._isAmazonPaySelected()) {
                    event.preventDefault();

                    confirmation({
                        title: $.mage.__('Deleting Amazon Pay Token'),
                        content: $.mage.__('If you switch to another stored payment method, the Amazon Pay token previously associated with the subscription will be made inactive. Is this OK?'),
                        actions: {
                            confirm: function () {
                                self.element.find('.action.save.primary').unbind('click').click();
                            }
                        },
                        buttons: [{
                            text: $.mage.__('No'),
                            class: 'action-secondary action-dismiss',
                            click: function (event) {
                                this.closeModal(event);
                            }
                        }, {
                            text: $.mage.__('Yes'),
                            class: 'action-primary action-accept',
                            click: function (event) {
                                this.closeModal(event, true);
                            }
                        }]
                    });
                }
            }
        });
    
        return $.mage.subscriptionsEdit;
    }

});
