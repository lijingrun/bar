<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/3/3
 * Time: 15:10
 */




class Yeepay_pc_onlinePayment extends BasePayment
{

    function get_payform($order_info)
    {
        /*
 * @Description 易宝支付产品通用支付接口范例
 * @V3.0
 * @Author xin.li
 */

        include 'yeepayCommon.php';

#	商家设置用户购买商品的支付信息.
##易宝支付平台统一使用GBK/GB2312编码方式,参数如用到中文，请注意转码

#	商户订单号,选填.
##若不为""，提交的订单号必须在自身账户交易中唯一;为""时，易宝支付会自动生成随机的商户订单号.
        $p2_Order					= $order_info['order_sn'];

#	支付金额,必填.
##单位:元，精确到分.
//        $pay_amount = $this->_online_pay_discount($order_info['order_amount']*100);
        $pay_amount = $order_info['order_amount'];
        $p3_Amt						= $pay_amount;

#	交易币种,固定值"CNY".
        $p4_Cur						= "CNY";

#	商品名称
##用于支付时显示在易宝支付网关左侧的订单产品信息.
        $p5_Pid						= '126wallpaper';

#	商品种类
        $p6_Pcat					= 'lanyu_product_type';

#	商品描述
        $p7_Pdesc					= 'lanyu_product_desc';

#	商户接收支付成功数据的地址,支付成功后易宝支付会向该地址发送两次成功通知.
        $p8_Url						= 'http://126wallpaper.com/index.php?app=paynotify&act=yeepay_pc_online';

#	送货地址
        $p9_SAF						= 0;

#	商户扩展信息
##商户可以任意填写1K 的字符串,支付成功时将原样返回.
//        $pa_MP = $order_info['buyer_name'];
        $pa_MP	= iconv("UTF-8","GB2312//IGNORE",$order_info['buyer_name']);
#	支付通道编码
##默认为""，到易宝支付网关.若不需显示易宝支付的页面，直接跳转到各银行、神州行支付、骏网一卡通等支付页面，该字段可依照附录:银行列表设置参数值.
        $pd_FrpId					= '';

#	订单有效期
##默认为"7": 7天;
        $pm_Period	= "7";



#	订单有效期单位
##默认为"day": 天;
        $pn_Unit	= "day";

        //原本的类获取不到该值，只能重新赋值全局变量
        global  $p0_Cmd;
        $p0_Cmd = 'Buy';
#	应答机制
##默认为"1": 需要应答机制;
        $pr_NeedResponse	= "1";

#调用签名函数生成签名串
        $hmac = getReqHmacString($p2_Order,$p3_Amt,$p4_Cur,$p5_Pid,$p6_Pcat,$p7_Pdesc,$p8_Url,$p9_SAF,$pa_MP,$pd_FrpId,$pm_Period,$pn_Unit,$pr_NeedResponse);

        echo "<html>
<head>
<title>To YeePay Page</title>
<meta http-equiv=\"Content-Type\" content=\"text/html; charset=gb2312\"/>
</head>
<body onLoad=\"document.yeepay.submit();\">
<form name='yeepay' action='".$reqURL_onLine."' method='post'>
<input type='hidden' name='p0_Cmd'					value='". $p0_Cmd."'>
<input type='hidden' name='p1_MerId'				value='".$p1_MerId."'>
<input type='hidden' name='p2_Order'				value='".$p2_Order."'>
<input type='hidden' name='p3_Amt'					value='".$p3_Amt."'>
<input type='hidden' name='p4_Cur'					value='".$p4_Cur."'>
<input type='hidden' name='p5_Pid'					value='".$p5_Pid."'>
<input type='hidden' name='p6_Pcat'					value='".$p6_Pcat."'>
<input type='hidden' name='p7_Pdesc'				value='".$p7_Pdesc."'>
<input type='hidden' name='p8_Url'					value='".$p8_Url."'>
<input type='hidden' name='p9_SAF'					value='".$p9_SAF."'>
<input type='hidden' name='pa_MP'						value='".$pa_MP."'>
<input type='hidden' name='pd_FrpId'				value='".$pd_FrpId."'>
<input type='hidden' name='pm_Period'				value='".$pm_Period."'>
<input type='hidden' name='pn_Unit'				value='".$pn_Unit."'>
<input type='hidden' name='pr_NeedResponse'	value='".$pr_NeedResponse."'>
<input type='hidden' name='hmac'						value='".$hmac."'>
</form>
</body>
</html>";
exit;

    }

    function _online_pay_discount($order_amount){
        $grade = $_SESSION['user_info']['sgrade'];
        switch($grade){
            case 5: $discount = round($order_amount*0.99);
                break;
            case 2 : $discount = round($order_amount*0.985);
                break;
            case 3 : $discount = round($order_amount*0.98);
                break;
        }
        return $discount;
    }

}