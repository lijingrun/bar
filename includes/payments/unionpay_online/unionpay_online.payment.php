<?php

class Unionpay_onlinePayment extends BasePayment {

    var $_code = 'unionpay_online';

    function get_payform($order_info) {

        header('Content-type:text/html;charset=utf-8');
        include_once dirname(__FILE__) . '/func/common.php';
        include_once dirname(__FILE__) . '/func/SDKConfig.php';
        include_once dirname(__FILE__) . '/func/secureUtil.php';
//        include_once dirname(__FILE__) . '/func/httpClient.php';
        include_once dirname(__FILE__) . '/func/log.class.php';
        $log = new PhpLog(SDK_LOG_FILE_PATH, "PRC", SDK_LOG_LEVEL);
        $log->LogInfo("============处理前台请求开始===============");
        // 初始化日志
//        $channelType = $this->_get_terminal();
//        $pay_amount = $this->_online_pay_discount($order_info['order_amount']*100);
        $pay_amount = $order_info['order_amount']*100;
        $params = array(
            'version' => '5.0.0', //版本号
            'encoding' => 'utf-8', //编码方式
            'certId' => getSignCertId(), //证书ID
            'txnType' => '01', //交易类型	
            'txnSubType' => '01', //交易子类
            'bizType' => '000201', //业务类型
            'frontUrl' => $this->_create_unionpay_return_url($order_info['order_id']), //前台通知地址，控件接入的时候不会起作用
            'backUrl' => $this->_create_unionpay_return_url($order_info['order_id']), //后台通知地址	
            'signMethod' => '01', //签名方法
            'channelType' => '07', //渠道类型，07-PC，08-手机
            'accessType' => '0', //接入类型
            'merId' => '802410053110606', //商户代码，请改自己的测试商户号
            'orderId' => $order_info['order_sn'], //商户订单号，8-40位数字字母
            'txnTime' => date('YmdHis', $order_info['add_time']+28800), //订单发送时间
            'txnAmt' => $pay_amount, //交易金额，单位分
            'currencyCode' => '156', //交易币种
            'defaultPayType' => '0001', //默认支付方式	
            'orderDesc' => '从蓝羽维涅斯商城购买产品', //订单描述，可不上送，上送时控件中会显示该信息
            'reqReserved' => ' 透传信息', //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现
        );
        
        // 签名
        $params = sign($params);
        // 前台请求地址
        $front_uri = SDK_FRONT_TRANS_URL;
        $log->LogInfo("前台请求地址为>" . $front_uri);
        // 构造 自动提交的表单
        $html_form = create_html($params, $front_uri);

        $log->LogInfo("-------前台交易自动提交表单>--begin----");
        $log->LogInfo($html_form);
        $log->LogInfo("-------前台交易自动提交表单>--end-------");
        $log->LogInfo("============处理前台请求 结束===========");
        echo $html_form;exit;
    }
    
    
    //获取终端信息7为pc 8为移动
    function _get_terminal() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $mobile_agents = array("240x320", "acer", "acoon", "acs-", "abacho", "ahong", "airness", "alcatel", "amoi",
            "android", "anywhereyougo.com", "applewebkit/525", "applewebkit/532", "asus", "audio",
            "au-mic", "avantogo", "becker", "benq", "bilbo", "bird", "blackberry", "blazer", "bleu",
            "cdm-", "compal", "coolpad", "danger", "dbtel", "dopod", "elaine", "eric", "etouch", "fly ",
            "fly_", "fly-", "go.web", "goodaccess", "gradiente", "grundig", "haier", "hedy", "hitachi",
            "htc", "huawei", "hutchison", "inno", "ipad", "ipaq", "iphone", "ipod", "jbrowser", "kddi",
            "kgt", "kwc", "lenovo", "lg ", "lg2", "lg3", "lg4", "lg5", "lg7", "lg8", "lg9", "lg-", "lge-", "lge9", "longcos", "maemo",
            "mercator", "meridian", "micromax", "midp", "mini", "mitsu", "mmm", "mmp", "mobi", "mot-",
            "moto", "nec-", "netfront", "newgen", "nexian", "nf-browser", "nintendo", "nitro", "nokia",
            "nook", "novarra", "obigo", "palm", "panasonic", "pantech", "philips", "phone", "pg-",
            "playstation", "pocket", "pt-", "qc-", "qtek", "rover", "sagem", "sama", "samu", "sanyo",
            "samsung", "sch-", "scooter", "sec-", "sendo", "sgh-", "sharp", "siemens", "sie-", "softbank",
            "sony", "spice", "sprint", "spv", "symbian", "tablet", "talkabout", "tcl-", "teleca", "telit",
            "tianyu", "tim-", "toshiba", "tsm", "up.browser", "utec", "utstar", "verykool", "virgin",
            "vk-", "voda", "voxtel", "vx", "wap", "wellco", "wig browser", "wii", "windows ce",
            "wireless", "xda", "xde", "zte");
        $is_mobile = 7;
        foreach ($mobile_agents as $device) {
            if (stristr($user_agent, $device)) {
                $is_mobile = 8;
                break;
            }
        }
        return $is_mobile;
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
