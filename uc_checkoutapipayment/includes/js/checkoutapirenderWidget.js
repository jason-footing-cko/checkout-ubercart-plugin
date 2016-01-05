(function ($) {
    $(function () {
        var head = document.getElementsByTagName("head")[0];
            scriptJs = document.createElement('script');

            if(Drupal.settings.uc_checkoutapipayment.mode == 'live') {
              scriptJs.src= "https://cdn.checkout.com/js/checkout.js";
            }
            else {
              scriptJs.src= "https://sandbox.checkout.com/js/v1/checkout.js";
            }
            scriptJs.type = 'text/javascript';
            scriptJs.async = true;
            head.appendChild(scriptJs);
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
                widgetContainerSelector: '.widget-container',
                forceMobileRedirect: true,
                styling: {
                    themeColor: '',
                    buttonColor: '',
                    logoUrl: '',
                    iconColor: '',
                },
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
                            if(!CheckoutIntegration.isMobile()){
                                CheckoutIntegration.open();
                                $(this).attr("disabled", 'disabled');
                            }
                            else {
                                $('#redirectUrl').val(CheckoutIntegration.getRedirectionUrl());
                                $('#checkoutapi-payment-review-form').trigger('submit');
                            }
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

            $('#edit-panes-payment-payment-method-checkoutapipayment-credit').once('checkoutapi').change(function(){
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