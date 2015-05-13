<?php

class methods_creditcardpci extends methods_Abstract
{

    public function submitFormCharge($order, $amount, $data)
    {
        $config = parent::submitFormCharge($order, $amount, $data);
        if (isset($order->payment_details['cc_cvv']) && !empty($order->payment_details['cc_cvv'])) {
            $config['postedParam']['card']['number'] = $order->payment_details['cc_number'];
            $config['postedParam']['card']['expiryMonth'] = $order->payment_details['cc_exp_month'];
            $config['postedParam']['card']['expiryYear'] = $order->payment_details['cc_exp_year'];
            $config['postedParam']['card']['cvv'] = $order->payment_details['cc_cvv'];
        }

        return $this->_placeorder($config,$order);
    }

}
