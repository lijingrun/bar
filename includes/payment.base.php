<?php

!defined('ROOT_PATH') && exit('Forbidden');

/**
 *    支付方式基础类
 *
 *    @author    Garbin
 *    @usage    none
 */
class BasePayment extends Object
{
    /* 外部处理网关 */
    var $_gateway   = '';
    /* 支付方式唯一标识 */
    var $_code      = '';


    function __construct($payment_info = array())
    {
        $this->BasePayment($payment_info);
    }
    function BasePayment($payment_info = array())
    {
        $this->_info   = $payment_info;
        $this->_config = unserialize($payment_info['config']);
    }

    /**
     *    获取支付表单
     *
     *    @author    Garbin
     *    @param     array $order_info
     *    @return    array
     */
    function get_payform()
    {
        return $this->_create_payform('POST');
    }

    /**
     *    获取规范的支付表单数据
     *
     *    @author    Garbin
     *    @param     string $method
     *    @param     array  $params
     *    @return    void
     */
    function _create_payform($method = '', $params = array())
    {
        return array(
            'online'    =>  $this->_info['is_online'],
            'desc'      =>  $this->_info['payment_desc'],
            'method'    =>  $method,
            'gateway'   =>  $this->_gateway,
            'params'    =>  $params,
        );
    }

    /**
     *    获取通知地址
     *
     *    @author    Garbin
     *    @param     int $store_id
     *    @param     int $order_id
     *    @return    string
     */
    function _create_notify_url($order_id)
    {
        return  "http://126wallpaper.com/index.php?app=paynotify&act=notify&order_id={$order_id}";
    }

    /**
     *    获取返回地址
     *
     *    @author    Garbin
     *    @param     int $store_id
     *    @param     int $order_id
     *    @return    string
     */
    function _create_return_url($order_id)
    {
        return  "http://126wallpaper.com/index.php?app=paynotify&order_id={$order_id}";
    }

    /**
     *    获取外部交易号
     *
     *    @author    Garbin
     *    @param     array $order_info
     *    @return    string
     */
    function _get_trade_sn($order_info)
    {
        $out_trade_sn = $order_info['out_trade_sn'];
        if (!$out_trade_sn)
        {
            $out_trade_sn = $this->_config['pcode'] . $order_info['order_sn'];

            /* 将此数据写入订单中 */
            $model_order =& m('order');
            $model_order->edit(intval($order_info['order_id']), array('out_trade_sn' => $out_trade_sn));
        }

        return $out_trade_sn;
    }

    /**
     *    获取商品简介
     *
     *    @author    Garbin
     *    @param     array $order_info
     *    @return    string
     */
    function _get_subject($order_info)
    {
        return 'ECMall Order:' . $order_info['order_sn'];
    }

    /**
     *    获取通知信息
     *
     *    @author    Garbin
     *    @return    array
     */
    function _get_notify()
    {
        /* 如果有POST的数据，则认为POST的数据是通知内容 */
        if (!empty($_POST))
        {
            return $_POST;
        }

        /* 否则就认为是GET的 */
        return $_GET;
    }

    /**
     *    验证支付结果
     *
     *    @author    Garbin
     *    @return    void
     */
    function verify_notify()
    {
        #TODO
    }

    /**
     *    将验证结果反馈给网关
     *
     *    @author    Garbin
     *    @param     bool   $result
     *    @return    void
     */
    function verify_result($result)
    {
        if ($result)
        {
            echo 'success';
        }
        else
        {
            echo 'fail';
        }
    }
    
        /**
     * 针对中行网银获取返回信息接收地址
     * 
     * 改地址用于接收中行网银返回信息并做判断和跳转
     */
    function _create_BOC_return_url($order_id)
    {
        return SITE_URL . "/index.php?app=paynotify&act=BOCreturn`&order_id={$order_id}";
    }
    
    //支付宝手机页面支付返回url
    function _create_alimobile_return_url($order_id){
        return "http://126wallpaper.com/index.php?app=paynotify&act=alimobilr_return&order_id={$order_id}";
    }
    
    function _create_unionpay_return_url($order_id){
        return SITE_URL . "http://126wallpaper.com/index.php?app=paynotify&act=notify_unionpay&order_id={$order_id}";
    }

    //线上支付查看对应的商品减免价格
    function calculation($order_id){
        $total_price = 0;
        $realy_price = 0;
        $order_goods_model = & m('ordergoods');
        $order_model = & m('order');
        //查订单里面所有商品
        $order_goods = $order_goods_model->find("order_id =".$order_id." AND price > 0");
        $spec_model = & m('goodsspec');
        //查商品对应的规格要求，如果是线上付款折扣优惠商品，乘以折扣价，否则按原价
        foreach($order_goods as $goods):
            $spec = $spec_model->get("spec_id =".$goods['spec_id']);
            if($spec['discount'] == 1){
                if($goods['quantity'] < 5){
                    $coefficient = 0.96;
                }elseif($goods['quantity'] >= 5 && $goods['quantity'] < 10){
                    $coefficient = 0.94;
                }elseif($goods['quantity'] >= 10 && $goods['quantity'] < 20){
                    $coefficient = 0.92;
                }elseif($goods['quantity'] >= 20 && $goods['quantity'] < 30){
                    $coefficient = 0.90;
                }elseif($goods['quantity'] >= 30 && $goods['quantity'] < 80){
                    $coefficient = 0.88;
                }elseif($goods['quantity'] >= 80){
                    $coefficient = 0.86;
                }
                $price = $spec['price']*$coefficient;
            }else{
                $price = $spec['price'];
            }
            $realy_price += $price*$goods['quantity'];
            $total_price += $spec['price']*$goods['quantity'];
        endforeach;
        $discount = $total_price-$realy_price;
        echo $discount;
        $order = $order_model->get("order_id =".$order_id);
        $order['discount'] = $discount;
        $order['order_amount'] = $realy_price;
        $order_model->edit($order_id,$order);
        return $realy_price;
    }


}

?>