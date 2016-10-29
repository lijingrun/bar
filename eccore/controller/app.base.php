<?php

/**
 *    控制器基础类
 *
 *    @author    Garbin
 *    @usage    none
 */
class BaseApp extends Object
{
    /* 建立到视图的链接 */
    var $_view = null;

    function __construct()
    {
        $this->BaseApp();
    }

    function BaseApp()
    {
        /* 初始化Session */
        $this->_init_session();
    }

    /**
     *    运行指定的动作
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function do_action($action)
    {
        if ($action && $action{0} != '_' && method_exists($this, $action))
        {
            $this->_curr_action  = $action;
            $this->_run_action();            //运行动作
        }
        else
        {
            exit('missing_action');
        }
    }
    function index()
    {
        echo 'Hello! ECMall';
    }

    /**
     *    给视图传递变量
     *
     *    @author    Garbin
     *    @param     string $k
     *    @param     mixed  $v
     *    @return    void
     */
    function assign($k, $v = null)
    {
        $this->_init_view();
        if (is_array($k))
        {
            $args  = func_get_args();
            foreach ($args as $arg)     //遍历参数
            {
                foreach ($arg as $key => $value)    //遍历数据并传给视图
                {
                    $this->_view->assign($key, $value);
                }
            }
        }
        else
        {
            $this->_view->assign($k, $v);
        }
    }

    /**
     *    显示视图
     *
     *    @author    Garbin
     *    @param     string $n
     *    @return    void
     */
    function display($n)
    {
        $this->_init_view();
        $this->_view->display($n);
    }
	/**
     *  获取输出页面内容
     * 调用内置的模板引擎fetch方法，
     * @access protected
     * @param string $n 指定要调用的模板文件
     * 默认为空 由系统自动定位模板文件
     * @return string
     */
    function fetch($n = '') {
        $this->_init_view();
        return $this->_view->fetch($n);
    }
    /**
     *    初始化视图连接
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _init_view()
    {
        if ($this->_view === null)
        {
            $this->_view =& v();
            $this->_config_view();  //配置
        }
    }

    /**
     *    配置视图
     *
     *    @author    Garbin
     *    @return    void
     */
    function _config_view()
    {
        # code...
    }

    /**
     *    运行动作
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _run_action()
    {
        $action = $this->_curr_action;
        $this->$action();
    }

    /**
     *    初始化Session
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _init_session()
    {
        import('session.lib');
        $db =& db();
        $this->_session =& new SessionProcessor($db, '`ecm_sessions`', '`ecm_sessions_data`', 'ECM_ID');
        define('SESS_ID', $this->_session->get_session_id());
        $this->_session->my_session_start();
    }

    /**
     *    获取程序运行时间
     *
     *    @author:    Garbin
     *    @param:     int $precision
     *    @return:    float
     */
    function _get_run_time($precision = 5)
    {
        return round(ecm_microtime() - START_TIME, $precision);
    }

    /**
     *  控制器结束运行后执行
     *
     *  @author Garbin
     *  @return void
     */
    function destruct()
    {
    }

    /**
     * 从csv文件导入
     *
     * @param string $filename 文件名
     * @param bool $header 是否有标题行，如果有标题行，从第二行开始读数据
     * @param string $from_charset 源编码
     * @param string $to_charset 目标编码
     * @param string $delimiter 分隔符
     * @return array
     */
    function import_from_csv($filename, $header = true, $from_charset = '', $to_charset = '', $delimiter= ',')
    {
        if ($from_charset && $to_charset && $from_charset != $to_charset)
        {
            $need_convert = true;
            import('iconv.lib');
            $iconv = new Chinese(ROOT_PATH . '/');
        }
        else
        {
            $need_convert = false;
        }

        $data = array();
        setlocale (LC_ALL, array ('zh_CN.gbk', 'zh_CN.gb2312', 'zh_CN.gb18030')); // 解决linux系统fgetcsv解析GBK文件时可能产生乱码的bug
        $handle = fopen($filename, "r");
        while (($row = fgetcsv($handle, 100000, $delimiter)) !== FALSE) {
            if ($need_convert)
            {
                foreach ($row as $key => $col)
                {
                    $row[$key] = $iconv->Convert($from_charset, $to_charset, $col);
                }
            }
            $data[] = $row;
        }
        fclose($handle);

        if ($header && $data)
        {
            array_shift($data);
        }

        return addslashes_deep($data);
    }

    /**
     * 导出csv文件
     *
     * @param array $data 数据（如果需要，列标题也包含在这里）
     * @param string $filename 文件名（不含扩展名）
     * @param string $to_charset 目标编码
     */
    function export_to_csv($data, $filename, $to_charset = '')
    {
        if ($to_charset && $to_charset != 'utf-8')
        {
            $need_convert = true;
            import('iconv.lib');
            $iconv = new Chinese(ROOT_PATH . '/');
        }
        else
        {
            $need_convert = false;
        }

        header("Content-type: application/unknown");
        header("Content-Disposition: attachment; filename={$filename}.csv");
        foreach ($data as $row)
        {
            foreach ($row as $key => $col)
            {

                if ($need_convert)
                {
                    $col = $iconv->Convert('utf-8', $to_charset, $col);
                }
                $row[$key] = $this->_replace_special_char($col);

            }
            echo join(',', $row) . "\r\n";
        }
    }

    /**
     * 替换影响csv文件的字符
     *
     * @param $str string 处理字符串
     */
    function _replace_special_char($str, $replace = true)
    {
        $str = str_replace("\r\n", "", $str);
        $str = str_replace("\t", "    ", $str);
        $str = str_replace("\n", "", $str);
        if ($replace == true)
        {
            $str = '"' . str_replace('"', '""', $str) . '"';
        }
        return $str;
    }

    /**
     * 订单数据入K3对应的数据表
     * $k3_code 客户k3编码
     * $user_name 账号名称
     * $k3_order 订单信息
     * $k3_pay_status 付款状态（线上汇款、余额支付、线下支付、未支付）
     */
    function _order_in_k3($k3_code,$user_name,$k3_order,$k3_pay_status){
        $anson_model = & m('anson');
        if(empty($k3_code)){
            $k3_code = 'underfind';
        }
        //根据订单状态来运行记录，如果是新建订单，就新建，如果是其他的，直接修改数据表里面的状态
        switch($k3_order['status']){
            //新建，只有蓝羽的会新建，因为存在线下汇款，其他公司的只有付款了才新建
            case 11 :
                //先清除旧的信息
                $anson_model->drop('fFBillNO ='.$k3_order['order_sn']);
                $ansons = $this->addanson_by_data($k3_code,$user_name,$k3_order,$k3_pay_status,'Y');
                foreach($ansons as $anson):
                    $anson_model->add($anson);
                endforeach;
                break;
            //汇款，如果工单之前有的，就直接修改，无，就新建
            case 20 :
                //先清除旧的信息
                $anson_model->drop('fFBillNO ='.$k3_order['order_sn']);
                $ansons = $this->addanson_by_data($k3_code,$user_name,$k3_order,$k3_pay_status,'Y');
                LyLog::log('<-- 入K3记录 -->开始插入'.$k3_order['order_sn']);
                foreach($ansons as $anson):
                    $anson_model->add($anson);
                    $i = 1;
                    LyLog::log('插入'.$i);
                    $i++;
                endforeach;
                LyLog::log('插入结束'.$k3_order['order_sn']);
                break;
            //取消
            case 0 :
                //先清除旧的信息
                $anson_model->drop('fFBillNO ='.$k3_order['order_sn']);
                $ansons = $this->addanson_by_data($k3_code,$user_name,$k3_order,$k3_pay_status,'N');
                foreach($ansons as $anson):
                    $anson_model->add($anson);
                endforeach;
                break;
        }
        return true;
    }

    /**
     * 根据订单信息生成一个anson的二维数组
     * $k3_code 需要入账的账号对应k3编码
     * $name 需要入账对应的客户名称
     * $order 订单信息
     */
    function addanson_by_data($k3_code,$name,$k3_order,$k3_order_status,$f_status){
        $ansons = array();
        $order_goods_model = & m('ordergoods');
        $order_extm_model = & m('orderextm');
        //先查订单收货信息
        $K3_order_extm = $order_extm_model->get('order_id ='.$k3_order['order_id']);
        $k3_address = $K3_order_extm['region_name'].$K3_order_extm['address'].$K3_order_extm['consignee']."(".$K3_order_extm['phone_tel'].")";
        //查找订单包含产品
        $k3_order_goods = $order_goods_model->find('order_id ='.$k3_order['order_id']);
        $spec_goods_model = & m('goodsspec');
        //循环订单商品，录入到二维数组
        foreach($k3_order_goods as $k3_order_good):
            //先查物料代码
            $spec = $spec_goods_model->get('spec_id ='.$k3_order_good['spec_id']);
            $spec_price = 0;
            //如果是线上充值，价钱就获取本来价钱的原价,否则就是产品价钱了
            if($k3_order_status == '线上充值'){
                $spec_price = $spec['original_price'];
            }else{
                $spec_price = $k3_order_good['price'];
            }
            $payment_code = '1002.01.036'; //默认328支付
            switch($k3_order['payment_code']){
                case 'yeepay_onekey': $payment_code = '1002.01.030';
                    break;
                case 'yeepay_pc_online' : $payment_code = '1002.01.030';
                    break;
                case 'unionpay_online'; $payment_code = '1002.01.036';
                    break;
            }
            switch($k3_order['k3_state']){
                case 12 : $phone_type = 'App下单';
                    break;
                case 13 : $phone_type = '微信下单';
                    break;
            }
            $new_anson = array(
                'fFdate' => date("Y-m-d",$k3_order['add_time']),
                'fFCustnumber' => $k3_code,
                'fFCustName' => $name,
                'fFBillNO' => $k3_order['order_sn'],
                'fFAdress' => $k3_address,
                'fStatus' => $k3_order_status, //订单支付状态，根据实际情况填写线上汇款、余额支付、线下支付或者未支付
                'fFAmontline' => $k3_order['order_amount'],
                'fFitemnumber' => $spec['sku'],
                'fFitemName' => $k3_order_good['goods_name'],
                'fFQty' => $k3_order_good['quantity'],
                'fFPrice' => $spec_price,
                'fFAmont' => $spec_price*$k3_order_good['quantity'],
                'fCheckstatus' => $f_status,
                'fCheckdata' => date('Y-m-d',time()),
                'fSendstatus' => 'N',
                'fComment' => $k3_order['postscript'],
                'fPayment' => $payment_code,
                'fPhoneType' => $phone_type,
            );
            $ansons[] = $new_anson;
        endforeach;
        return $ansons;
    }

}

?>