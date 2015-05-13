<?php

class methods_creditcard extends methods_Abstract
{

    public function submitFormCharge($order, $amount, $data)
    {

        $config = parent::submitFormCharge($order, $amount, $data); 
        $config['postedParam']['paymentToken'] = $_POST['paymentToken'];
        return $this->_placeorder($config, $order);
    }

    public function getExtraInit($order = null)
    {
        $scriptConfig = array();
        
        if ($order) {
            
            $paymentToken = $this->generatePaymentToken($order);
            $config = array();

            $config['debug'] = false;
            $config['publicKey'] = variable_get('public_key');
            $config['mode'] = variable_get('mode');
            $config['email'] = $order->primary_email;
            $config['name'] = $order->billing_first_name . ' ' . $order->billing_last_name;
            $config['amount'] = getInstance($order->payment_method)->formatAmountToCents($order->order_total);
            $config['currency'] = strtolower($order->currency);
            $config['paymentToken'] = $paymentToken['token'];
            $config['renderMode'] = 2;
            $config['widgetSelector'] = '.widget-container';

            $scriptConfig = $config;

        }
        return $scriptConfig;
    }

    public function generatePaymentToken($order)
    {
        if (isset($order)) {
            
            $orderId = $order->order_id;
            $amountCents = $this->formatAmountToCents($order->order_total);
            $currency_code = strtolower($order->currency);
            $shipping_array = array();
            $scretKey = variable_get('private_key');
            $mode = variable_get('mode');
            $timeout = variable_get('timeout', 60);

            $config['authorization'] = $scretKey;
            $config['mode'] = $mode;
            $config['timeout'] = $timeout;
            $billing_country = uc_get_country_data(array('country_id' => $order->billing_country));
            $delivery_country = uc_get_country_data(array('country_id' => $order->delivery_country));

            $transaction_type = variable_get('payment_action');

            if ($transaction_type == 'authorize') {
                $config = array_merge($this->_authorizeConfig(), $config);
                
            }   else {
                $config = array_merge($this->_captureConfig(), $config);
            }

            $products = array();

            foreach ($order->products as $item) {

                $products[] = array(
                    'name'     => $item->title,
                    'sku'      => $item->model,
                    'price'    => uc_currency_format($item->price, $sign = FALSE, $thou = FALSE, $dec = '.'),
                    'quantity' => $item->qty,
                );
            }

            if (uc_order_is_shippable($order) && !empty($order->delivery_first_name)) {
                // Add the shipping address parameters to the request.
              $del_phone_length = strlen($order->delivery_phone);


                $shipping_array = array(
                    'addressLine1'    => $order->delivery_street1,
                    'addressLine2'    => $order->delivery_street2,
                    'postcode'        => $order->delivery_postal_code,
                    'country'         => $delivery_country[0]['country_iso_code_2'],
                    'city'            => $order->delivery_city,  
                );
                if ($del_phone_length > 7){
                  $del_phone_array = array(
                      'phone'  => array('number' => $order->delivery_phone)
                  );
                  $shipping_array = array_merge_recursive($shipping_array, $del_phone_array);  
                }        
            }

            $bil_phone_length = strlen($order->billing_phone);
            $billingDetailsConfig = array(
                'addressLine1'    => $order->billing_street1,
                'addressLine2'    => $order->billing_street2,
                'postcode'        => $order->billing_postal_code,
                'country'         => $billing_country[0]['country_iso_code_2'],
                'city'            => $order->billing_city,
            );

            if ($bil_phone_length > 7){
                  $bil_phone_array = array(
                      'phone'  => array('number' => $order->billing_phone)
                  );
                  $billingDetailsConfig = array_merge_recursive($billingDetailsConfig, $bil_phone_array);  
            }        

            $config['postedParam'] = array_merge_recursive($config['postedParam'],array(
                'email'           => $order->primary_email,
                'value'           => $amountCents,
                'trackId'         => $order->order_id,
                'currency'        => $currency_code,
                'shippingDetails' => !empty($shipping_array) ? $shipping_array : $billingDetailsConfig,
                'products'        => $products,
                'card'            => array(
                    'name'           => $order->billing_first_name . ' ' . $order->billing_last_name,
                    'billingDetails' => $billingDetailsConfig
                )
            ));

            $Api = CheckoutApi_Api::getApi(array('mode' => $mode));

            $paymentTokenCharge = $Api->getPaymentToken($config);
            
            $paymentTokenArray = array(
                'message' => '',
                'success' => '',
                'eventId' => '',
                'token' => '',
            );

            if ($paymentTokenCharge->isValid()) {
                $paymentTokenArray['token'] = $paymentTokenCharge->getId();
                $paymentTokenArray['success'] = true;
                
            }   else {

                $paymentTokenArray['message'] = $paymentTokenCharge->getExceptionState()->getErrorMessage();
                $paymentTokenArray['success'] = false;
                $paymentTokenArray['eventId'] = $paymentTokenCharge->getEventId();
            }
        }
        return $paymentTokenArray;
    }

    protected function _createCharge($config)
    {
        $config = array();

        $scretKey = variable_get('private_key');
        $mode = variable_get('mode');
        $timeout = variable_get('timeout', 60);

        $config['authorization'] = $scretKey;
        $config['timeout'] = $timeout;
        $config['paymentToken'] = $_POST['paymentToken'];
        
        $Api = CheckoutApi_Api::getApi(array('mode' => $mode));
        
        return $Api->verifyChargePaymentToken($config);
    }

    protected function _captureConfig()
    {
        $to_return['postedParam'] = array(
            'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE,
            'autoCapTime' => variable_get('autocaptime', 0)
        );

        return $to_return;
    }

    protected function _authorizeConfig()
    {
        $to_return['postedParam'] = array(
            'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH,
            'autoCapTime' => 0
        );
        return $to_return;
    }

}
