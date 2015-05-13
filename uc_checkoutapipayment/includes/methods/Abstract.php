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
        $billing_country = uc_get_country_data(array('country_id' => $order->billing_country));
        $delivery_country = uc_get_country_data(array('country_id' => $order->delivery_country));

        if ($data['txn_type'] == 'auth_capture') {
            $config = array_merge_recursive($this->_captureConfig(), $config);
        }
        else {
            $config = array_merge_recursive($this->_authorizeConfig(), $config);
        }
        foreach ($order->products as $product) {

            // Add the line item to the return array.
            $products[] = array(
                'productName' => $product->title,
                'price'       => uc_currency_format($product->price, $sign = FALSE, $thou = FALSE, $dec = '.'),
                'quantity'    => $product->qty,
                'sku'         => $product->model
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
