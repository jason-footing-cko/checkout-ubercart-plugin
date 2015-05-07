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

            $scretKey = variable_get('private_key');
            $mode = variable_get('mode');
            $timeout = variable_get('timeout', 60);

            $config['authorization'] = $scretKey;
            $config['mode'] = $mode;
            $config['timeout'] = $timeout;

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

            // Add the shipping address parameters to the request.
            $shipping_array = array(
                'addressLine1'    => $order->delivery_street1,
                'addressLine2'    => $order->delivery_street2,
                'addressPostcode' => $order->delivery_postal_code,
                'addressCountry'  => $order->delivery_country,
                'addressCity'     => $order->delivery_city,
                'recipientName'   => $order->delivery_first_name . ' ' . $order->delivery_last_name,
                'phone'           => array('number' => $order->delivery_phone)
            );

            $config['postedParam'] = array_merge_recursive($config['postedParam'], array(
                'email'           => $order->primary_email,
                'value'           => $amountCents,
                'trackId'         => $order->order_id,
                'currency'        => $currency_code,
                'shippingDetails' => $shipping_array,
                'products'        => $products,
                'card'            => array(
                    'name' => $order->billing_first_name . ' ' . $order->billing_last_name,
                    'billingDetails' => array(
                        'addressLine1'    => $order->billing_street1,
                        'addressLine2'    => $order->billing_street2,
                        'addressPostcode' => $order->billing_postal_code,
                        'addressCountry'  => $order->billing_country,
                        'addressCity'     => $order->billing_city,
                        'phone'           => array('number' => $order->billing_phone)
                    )
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
