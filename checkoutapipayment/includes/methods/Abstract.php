<?php

abstract class methods_Abstract
{

    public function submitFormCharge($order, $amount, $data)
    {
        $config = array();
        $shipping_array = array();
        $products = array();
        $amountCents = $this->formatAmountToCents($amount);
        $config['authorization'] = variable_get('private_key');
        $config['mode'] = variable_get('mode');
        $currency_code = strtolower($order->currency);
       
        
        foreach ($order->products as $product) {

            // Add the line item to the return array.
            $products[] = array(
                'productName' => $product->title,
                'price'       => uc_currency_format($product->price, $sign = FALSE, $thou = FALSE, $dec = '.'),
                'quantity'    => $product->qty,
                'sku'         => $product->model
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

        $config['postedParam'] = array(
            'email'           => $order->primary_email,
            'value'           => $amountCents,
            'trackId'         => $order->order_id,
            'currency'        => $currency_code,
            'shippingDetails' => $shipping_array,
            'products'        => $products,
            'card'            => array(
                'name'           => $order->billing_first_name . ' ' . $order->billing_last_name,
                'billingDetails' => array(
                    'addressLine1'    => $order->billing_street1,
                    'addressLine2'    => $order->billing_street2,
                    'addressPostcode' => $order->billing_postal_code,
                    'addressCountry'  => $order->billing_country,
                    'addressCity'     => $order->billing_city,
                    'phone'           => array('number' => $order->billing_phone)
                )
            )
        );

        if ($data['txn_type'] == 'auth_capture') {
            $config = array_merge($this->_captureConfig(), $config);
        }
        else {
            $config = array_merge($this->_authorizeConfig(), $config);
        }
        return $config;
    }

    protected function _placeorder($config, $order)
    {
        global $user;
        //building charge
        $respondCharge = $this->_createCharge($config);
        
        $responsemessage = '';

        if ($respondCharge->isValid()) {
            $responsemessage = $respondCharge->getResponseMessage();
            if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {
                
                $result = array(
                    'success' => TRUE,
                    'comment' => $responsemessage,
                    'message' => $responsemessage,
                    'uid'     => $user->uid,
                );
                
            } else {

                $result = array(
                    'success' => false,
                    'comment' => $responsemessage,
                    'message' => $responsemessage,
                    'uid'     => $user->uid,
                );
            }

            $comment = t('Gateway Message: @msg', array('@msg' => $responsemessage . ' with chargeId ' . $respondCharge->getId()));
              
        } else {
            $result = array(
                'success' => false,
                'comment' => $respondCharge->getEventId(),
                'message' => t('Please try again , and error has occured. ('.$respondCharge->getMessage().')'),
                'uid'     => $user->uid,
            );
            $comment = t('Gateway Message: @msg', array('@msg' => $respondCharge->getMessage()));
        }
        
        // Save the comment to the order.
        uc_order_comment_save($order->order_id, $user->uid, $comment, 'admin');
        
        return $result;
    }

    protected function _createCharge($config)
    {
        $Api = CheckoutApi_Api::getApi(array('mode' => variable_get('mode')));
        return $Api->createCharge($config);
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

    public function getExtraInit($order = null)
    {
        return null;
    }

    public function formatAmountToCents($value)
    {
        // strip out commas
        $convertedValue = preg_replace("/\,/i", "", $value);
        // strip out all but numbers, dash, and dot
        $convertedValue = preg_replace("/([^0-9\.\-])/i", "", $convertedValue);
        // convert to a float explicitly
        $convertedValue = (float) $convertedValue;
        return round($convertedValue, 2) * 100;
    }

}
