<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/3/14
 * Time: 9:04
 */

class Yeepay_onekeyPayment extends BasePayment
{

    function get_payform($order_info)
    {
        //如果订单未有优惠，就设置线上支付优惠
        if($order_info['discount'] == 0 && $order_info['buyer_id'] == 27077) {
            $realy_pay = $this->calculation($order_info['order_id']);
        }
        import('yeepay/yeepayMPay');
        // 商户编号
        $merchantaccount = '10013227109';
// 商户私钥
        $merchantPrivateKey = 'MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAIkKyO+L62my2ZKMTbv+fww6bVWlUWSAVy3/XIM6B9TcqMOOlrzemiUXKD+JuJb7s8yVQfvncfnyxnB0fYgZOGwWRsHRmfMzA2IERMHmPkxu1A64ZHdmN2hNO1fwd9PmKlN1nCgr1c91bnNRwWZ0jKgM2CuDUG5BqRzLPX2fY31hAgMBAAECgYASuB5qWjp13bBKjE+x9jl0eialJEfR6pX9+nuwkSSwttN2ouuEMQPtPRSKWU2VkhwlPd4dgqfW9IqWodLj3E4Qgj729jZEXeyn5lBXnCTjuV+k91ZhOEsz8Ues4dHHpX1zoLne3LxU4orJqs3YY1X3vJ5YKjk4MnCWBEsdkUmAAQJBAMwG542zUDQx978TCV65vZJXxYUZIgKD14JMHfkfyvGcoWmrTIBRIH2bX/6ouf+HoFcJq7zb74ZvJUGH+HCgpuECQQCr86LA5EZzPFDKkEO5uEPfXP27xxXT52BkkXsf4B1WUt5AoXwU5H5/9JXLrNQ69SgfU70SI+WTZaZpa1f86iaBAkBQdzylhx3PqBFUm3ZrlIeuis1Mw+/E3CiHq+t6UE6i4apLWZLPXK+aukeu0O6iV+Qlz5ua3YbnFzizUqPqD4IhAkBT75b337aAE+ZAKxHUO51uEB+PpQwDp4NHNDjNA4Jum/7/v5QpQqx5W3QvuwrSSM+wExlNHJa5T7pe5VZLECWBAkBqt/nNOa992dUl1c6TkY3WF6bIuyd7wu1X9K+sWV+KnIbHtRBJepFbpmwANtttzRRX9NAmcIlGAUvVcXegb2vb';

// 商户公钥
        $merchantPublicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCJCsjvi+tpstmSjE27/n8MOm1VpVFkgFct/1yDOgfU3KjDjpa83polFyg/ibiW+7PMlUH753H58sZwdH2IGThsFkbB0ZnzMwNiBETB5j5MbtQOuGR3ZjdoTTtX8HfT5ipTdZwoK9XPdW5zUcFmdIyoDNgrg1BuQakcyz19n2N9YQIDAQAB';
// 易宝公钥
        $yeepayPublicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCCuQAdaXu2il0WpXwhXC/AYreTXr1cKnFRNyGUrS+I7buOaMkl7DPTG2Nn5kB8TCHP2YeiainK+jJWdZpohsJskHDkQZNTvpvvmT4DTPKqdqlE8hwPiotmwwEGREGxWOvSvj0XhXeOYAD7m4WIKWAHPR+pImSAEpgJ2O2XA5eA7wIDAQAB';
        $yeepay = new yeepayMPay($merchantaccount,$merchantPublicKey,$merchantPrivateKey,$yeepayPublicKey);
//        echo $yeepayPublicKey;exit;
        $order_id = $order_info['order_sn'];//网页支付的订单在订单有效期内可以进行多次支付请求，但是需要注意的是每次请求的业务参数都要一致，交易时间也要保持一致。否则会报错“订单与已存在的订单信息不符”
        //易宝奇葩逻辑，每次第一次的订单必须是当前时间，然后以后的请求也必须是这个时间，否则第一次点击进入没有付款之后需要重新下单，奇葩！
//        echo $order_info['pay_time'];exit;
//        $transtime = time();//交易时间，是每次支付请求的时间，注意此参数在进行多次支付的时候要保持一致。
        $transtime = strtotime(date("Y-m-d H:i:s",$order_info['add_time']));
        $product_catalog ='7';//商品类编码是我们业管根据商户业务本身的特性进行配置的业务参数。
        $identity_id = $order_info['buyer_id'];//用户身份标识，是生成绑卡关系的因素之一，在正式环境此值不能固定为一个，要一个用户有唯一对应一个用户标识，以防出现盗刷的风险且一个支付身份标识只能绑定5张银行卡
        $identity_type = 0;     //支付身份标识类型码
        $user_ip = $_SERVER['REMOTE_ADDR']; //此参数不是固定的商户服务器ＩＰ，而是用户每次支付时使用的网络终端IP，否则的话会有不友好提示：“检测到您的IP地址发生变化，请注意支付安全”。
        $user_ua = 'NokiaN70/3.0544.5.1 Series60/2.8 Profile/MIDP-2.0 Configuration/CLDC-1.1';//用户ua
        $callbackurl ='http://126wallpaper.com/index.php?app=paynotify&act=yeepay_onekey';//商户后台系统回调地址，前后台的回调结果一样
        $fcallbackurl ='http://126wallpaper.com/index.php?app=paynotify&act=yeepay_onekey';//商户前台系统回调地址，前后台的回调结果一样
        $product_name = '蓝羽维涅斯商城-蓝羽维涅斯商城商品';//出于风控考虑，请按下面的格式传递值：应用-商品名称，如“诛仙-3 阶成品天琊”
        $product_desc = '蓝羽维涅斯商城商品';//商品描述
        $terminaltype = 3;
        $terminalid = $order_info['buyer_name'];//其他支付身份信息
        if(empty($realy_pay)){
//            $pay_amount = $this->_online_pay_discount($order_info['order_amount']*100);
            $pay_amount = $order_info['order_amount']*100;
            $amount = $pay_amount;//订单金额单位为分，支付时最低金额为2分，因为测试和生产环境的商户都有手续费（如2%），易宝支付收取手续费如果不满1分钱将按照1分钱收取。
        }else{
            $amount = $realy_pay*100;
        }
//        $cardno ='6226388002295420';
//        $idcardtype='01';
//        $idcard = '';
//        $owner = '张三';
        $url = $yeepay->webPay($order_id,$transtime,$amount,$cardno,$idcardtype,$idcard,$owner,$product_catalog,$identity_id,$identity_type,$user_ip,$user_ua,$callbackurl,$fcallbackurl,$currency=156,$product_name,$product_desc,$terminaltype,$terminalid,$orderexp_date=14400);
//        $arr = explode("&",$url);
//        $encrypt = explode("=",$arr[1]);
//        $data = explode("=",$arr[2]);
        echo "<html><head></head><script language=\"javascript\" type=\"text/javascript\">window.location.href='".$url."';</script><body>正在跳转到支付界面...</body></html>";
        exit;
    }

    function create_str( $length = 8 ) {
// 密码字符集，可任意添加你需要的字符
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ( $i = 0; $i < $length; $i++ )
        {
// 这里提供两种字符获取方式
// 第一种是使用 substr 截取$chars中的任意一位字符；
// 第二种是取字符数组 $chars 的任意元素
// $password .= substr($chars, mt_rand(0, strlen($chars) – 1), 1);
            $str .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
        return $str;
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