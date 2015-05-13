(function ($) {
    $(function () {
        var head = document.getElementsByTagName("head")[0],
                scriptJs = document.getElementById('checkoutApijs');

        if (!scriptJs) {
            scriptJs = document.createElement('script');

            if(Drupal.settings.uc_checkoutapipayment.mode == 'live') {
              scriptJs.src= "https://www.checkout.com/cdn/js/checkout.js";
            }
            else {
              scriptJs.src= "//sandbox.checkout.com/js/v1/checkout.js";
            }
            scriptJs.id = 'checkoutApijs';
            scriptJs.type = 'text/javascript';
            var interVal = setInterval(function () {
                if (CheckoutIntegration) {
                    $('head').append($('.widget-container link'));
                    clearInterval(interVal);
                }

            }, 1000);
            head.appendChild(scriptJs);
        }
    });

    Drupal.behaviors.uc_checkoutapipayment = {
        attach: function (context, settings) {

            var reload = false;
            window.CKOConfig = {
                debug: false,
                renderMode: 2,
                publicKey: Drupal.settings.uc_checkoutapipayment.publicKey,
                customerEmail: Drupal.settings.uc_checkoutapipayment.email,
                namespace: 'CheckoutIntegration',
                customerName: Drupal.settings.uc_checkoutapipayment.name,
                value: Drupal.settings.uc_checkoutapipayment.amount,
                currency: Drupal.settings.uc_checkoutapipayment.currency,
                paymentToken: Drupal.settings.uc_checkoutapipayment.paymentToken,
                paymentMode: 'card',
                widgetContainerSelector: '.widget-container',
                cardCharged: function (event) {
                    $('#checkoutapi-payment-review-form').trigger('submit');
                },
                widgetRendered: function (event) {

                    $('.page-cart-checkout-review #cko-widget').hide();
                },
                ready: function () {
                    $('#checkoutapi-payment-review-form input.form-submit').click(function (event) {
                        event.preventDefault();
                        if (CheckoutIntegration) {
                            CheckoutIntegration.open();
                            $(this).attr("disabled", 'disabled');
                        }
                    });
                },
                lightboxDeactivated: function (event) {
                    if (reload) {
                        window.location.reload();
                    }
                    $('#checkoutapi-payment-review-form input.form-submit').removeAttr("disabled");
                },
                paymentTokenExpired: function(event){
                    reload = true;
                }, 
            }

            $('#edit-panes-payment-payment-method-checkoutapipayment-credit').unbind('click.CheckoutApi');
            $('#edit-panes-payment-payment-method-checkoutapipayment-credit').bind('click.CheckoutApi', function () {

                var interVal2 = setInterval(function () {
                    if ($('.widget-container').length) {
                        CheckoutIntegration.render(window.CKOConfig);
                        clearInterval(interVal2);
                    }
                }, 500);
            })
        }
    }

})(jQuery);