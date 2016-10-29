<?php

//物流管理界面
class LogisticsApp extends BackendApp {

    function index() {
        $order_model = & m('order');
        $page_size = 15;
        $page = $_GET['page'];
        if (empty($page)) {
            $page = 1;
        }
        $limit = ($page - 1) * $page_size . ',' . $page_size;
        $count = $_GET['count'];
        $redeem_orders_model = & m('redeem_orders');
        //订单状态 20为待发货 30为已发货
        $status = $_GET['status'];
        //订单类型 goods为商品订单 redeem为积分订单
        $order_type = $_GET['order_type'];
        //订单id号
        $order_id = $_GET['order_id'];
        //购买者
        $buyer_name = $_GET['buyer_name'];
        //查询条件
        $conditions = "1 = 1";
        //默认待发货
        if (empty($status)) {
            $status = 20;
        }

        //默认商品订单
        if (empty($order_type)) {
            $order_type = 'goods';
        }
        $conditions .= ' AND status =' . $status;

        if (!empty($buyer_name)) {
            $conditions .= " AND buyer_name like '%" . $buyer_name . "%'";
        }

        //如果是待发货的订单，直接就是查3个字段，如果是已发货，就需要查所有字段了
        if ($status == 20) {
            if ($order_type == 'goods') {
                $fields = 'add_time,buyer_name,order_sn';
            } else {
                $fields = 'created,buyer_name,order_id';
            }
        } else {
            $fields = '*';
        }
        if ($status == 20 || !empty($order_id) || !empty($buyer_name)) {
            if ($order_type == 'goods') {
                if (!empty($order_id)) {
                    $conditions .= ' AND order_sn =' . $order_id;
                }
                if (empty($count)) {
                    $all_orders = $order_model->find(array(
                        'conditions' => $conditions,
                        'fields' => 'order_id',
                    ));
                    $count = count($all_orders);
                }
                $pages = ceil($count / $page_size); //总页数
                //查商品订单
                $goods_orders = $order_model->find(array(
                    'conditions' => $conditions,
                    'fields' => $fields,
                    'limit' => $limit,
                    'order' => 'add_time desc',
                ));
            } else {
                if (!empty($order_id)) {
                    $conditions .= ' AND order_id =' . $order_id;
                }
                if (empty($count)) {
                    $all_orders = $goods_orders = $redeem_orders_model->find(array(
                        'conditions' => $conditions,
                        'fields' => "",
                    ));
                    $count = count($all_orders);
                }
                $pages = ceil($count / $page_size); //总页数
                //积分订单
                $goods_orders = $redeem_orders_model->find(array(
                    'conditions' => $conditions,
                    'fields' => $fields,
                    'limit' => $limit,
                    'order' => 'created desc'
                ));
            }
        }
        if ($page == 1) {
            $pev_page = 1;
        } else {
            $pev_page = $page - 1;
        }

        $next_page = $page + 1;
        $this->assign('pages', $pages);
        $this->assign('count', $count);
        $this->assign('count', $count);
        $this->assign('next_page', $next_page);
        $this->assign('pev_page', $pev_page);
        $this->assign('page', $page);
        $this->assign('buyer_name', $buyer_name);
        $this->assign('order_id', $order_id);
        $this->assign('status', $status);
        $this->assign('order_type', $order_type);
        $this->assign('goods_orders', $goods_orders);
        $this->assign('order_type', $order_type);
        $this->display('loginstics.html');
    }

    //ajax发货（商品订单）
    function out() {
        $model_order = & m('order');
        $order_info = $model_order->get('order_sn =' . $_POST['id']);
        $order_id = $order_info['order_id'];
        $remark = 'LY物流管理'; //初始化remark
        $edit_data = array(
            'status' => ORDER_SHIPPED,
            'invoice_no' => $_POST['invoice_no'],
            'invoice_origin_phone' => $_POST['invoice_origin_phone'],
            'invoice_contact' => $_POST['invoice_contact'],
            'invoice_phone' => $_POST['invoice_phone'],
            'invoice_company' => $_POST['invoice_company'],
            'invoice_status' => $_POST['invoice_status'],
//            'invoice_forecast_time' => $_POST['invoice_forecast_time'],   已取消
            'get_phone' => $_POST['get_phone'],
            'freight' => $_POST['freight'],
            'invoice_change_time' => $_POST['invoice_change_time'],
            'deliver_price' => $_POST['deliver_price'],
        );
        $is_edit = true;
        if (empty($order_info['invoice_no'])) {
            /* 不是修改发货单号 */
            $edit_data['ship_time'] = gmtime();
            $is_edit = false;
        }
        $model_order->edit(intval($order_id), $edit_data);
        if ($model_order->has_error()) {
            $this->pop_warning($model_order->get_error());

            return;
        }

        #TODO 发邮件通知
        /* 记录订单操作日志 */
        $order_log = & m('orderlog');
        $order_log->add(array(
            'order_id' => $order_id,
            'operator' => addslashes($this->visitor->get('user_name')),
            'order_status' => order_status($order_info['status']),
            'changed_status' => order_status(ORDER_SHIPPED),
            'remark' => $remark,
            'log_time' => gmtime(),
        ));


        /* 发送给买家订单已发货通知 */
        $model_member = & m('member');
        $buyer_info = $model_member->get($order_info['buyer_id']);
        $order_info['invoice_no'] = $edit_data['invoice_no'];
        $device_model = & m('devices');
        $device = $device_model->get("user_id =".$order_info['buyer_id']);
        if(!empty($device['serial_id']) && $device['device_type'] == 'mqtt'){
            $mqtt = new Mqtt();
            $mqtt->send($device['serial_id'],'您的订单'.$order_info['order_sn'].'已发货，请注意查收');
        }else if(!empty($device['serial_id']) && $device['device_type'] == 'apns'){
            $apns = new Apns();
            $apns->send($device['serial_id'],'您的订单'.$order_info['order_sn'].'已发货，请注意查收');
        }
//        $device_model = & m('devices');
//        $device = $device_model->get("device_type = 'mqtt' AND user_id =".$order_info['buyer_id']);
//        if(!empty($device['serial_id'])){
//            $mqtt = new Mqtt();
//            $mqtt->send($device['serial_id'],'您的订单'.$order_info['order_sn'].'已发货，请注意查收');
//        }
//        $mail = get_mail('tobuyer_shipped_notify', array('order' => $order_info));
//        $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));
        if(!empty($buyer_info['openid'])){
            $template_id = 'faW7xSu6ZkmqTI4UOquVjlE80RozJFkglcDzjFMr-aQ';
            $send_weixin = $this->_send_message_to_weixin($buyer_info['openid'],$template_id,$order_info['order_sn'],$_POST['invoice_company'],$_POST['invoice_no']);
            if($send_weixin == 41006){ //access_token 超时
                $this->_get_access_token();
                $this->_send_message_to_weixin($buyer_info['openid'],$template_id,$order_id,$_POST['invoice_company'],$_POST['invoice_no']);
            }
        }

        $new_data = array(
            'status' => Lang::get('order_shipped'),
            'actions' => array(
                'cancel',
                'edit_invoice_no'
            ), //可以取消可以发货
        );
        if ($order_info['payment_code'] == 'cod') {
            $new_data['actions'][] = 'finish';
        }

        echo 111;
        exit;
    }

    //积分商品发货ajax
    function out_redeem() {
        $redeem_orders_model = & m('redeem_orders');
        $order_info = $redeem_orders_model->get($_POST['id']);
        $order_id = $order_info['order_id'];
        //获取信息
        $edit_data = array(
            'status' => ORDER_SHIPPED,
            'logistics_num' => $_POST['logistics_num'],
            'out_phone' => $_POST['out_phone'],
            'out_man' => $_POST['out_man'],
            'logistics_name' => $_POST['logistics_name'],
            'logistics_phone' => $_POST['logistics_phone'],
            'invoice_status' => $_POST['invoice_status'],
            'get_time' => $_POST['get_time'],
            'out_man_k3' => "LY施工管理",
            'out_time' => strtotime($_POST['invoice_change_time']),
        );
        $redeem_orders_model->edit($order_id, $edit_data);
        if ($redeem_orders_model->has_error()) {
            $this->show_warning($redeem_orders_model->get_error());
            return;
        }

        /* 发送给买家订单已发货通知 */
//            $model_member =& m('member');
//            $buyer_info   = $model_member->get($order_info['buyer_id']);
//            $order_info['invoice_no'] = $edit_data['logistics_num'];
//            $mail = get_mail('tobuyer_shipped_notify', array('order' => $order_info));
//            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));
//        

        echo 111;
        exit;
    }

    //线下订单物流模块
    function underline() {
        $underline_model = & m('underline_logistics');
        $order_id = $_GET['order_id'];
        $buyer_name = $_GET['buyer_name'];
        if (!IS_POST) {
            $conditions = 'status=1';
            $page_size = 15;
            $page = $_GET['page'];
            if (empty($page)) {
                $page = 1;
            }
            $limit = ($page - 1) * $page_size . ',' . $page_size;
            if (!empty($order_id)) {
                $conditions .= " AND order_id =" . $order_id;
            }
            if (!empty($buyer_name)) {
                $conditions .= " AND buyer_name like '%" . $buyer_name . "%'";
            }
            $order_list = $underline_model->find(array(
                'conditions' => $conditions,
                'order' => 'addtime desc',
                'limit' => $limit,
            ));
            if ($page <= 1) {
                $pev_page = 1;
            } else {
                $pev_page = $page - 1;
            }
            $next_page = $page + 1;
            $this->assign('page', $page);
            $this->assign('pev_page', $pev_page);
            $this->assign('next_page', $next_page);
            $this->assign('order_list', $order_list);
            $this->assign('buyer_name', $buyer_name);
            $this->assign('order_id', $order_id);
            $this->display('underline_logistics.html');
        } else {
            $underline = array(
                'buyer_name' => $_POST['buyer_name'],
//                'order_id' => $_POST['order_id'],
                'logistics_id' => $_POST['logistics_id'],
                'phone' => $_POST['phone'],
                'people' => $_POST['people'],
                'l_name' => $_POST['l_name'],
                'l_phone' => $_POST['l_phone'],
                'l_status' => $_POST['l_status'],
//                'get_time' => $_POST['get_time'],//取消预达时间
                'get_phone' => $_POST['get_phone'],
                'num' => $_POST['num'],
                'deliver_price' => $_POST['deliver_price'],
                'freight' => $_POST['freight'],
                'addtime' => strtotime($_POST['addtime']),
            );
            $underline_model->add($underline);
            $this->show_message('发货成功！', '返回', 'index.php?app=logistics&act=underline');
        }
    }

    //修改线下订单货运信息
    function under_edit() {
        $underline_model = & m('underline_logistics');
        $id = $_POST['id'];
        $underline = array(
            'buyer_name' => $_POST['buyer_name'],
//            'order_id' => $_POST['order_id'],
            'logistics_id' => $_POST['logistics_id'],
            'phone' => $_POST['phone'],
            'people' => $_POST['people'],
            'l_name' => $_POST['l_name'],
            'l_phone' => $_POST['l_phone'],
            'l_status' => $_POST['l_status'],
//            'get_time' => $_POST['get_time'],
            'get_phone' => $_POST['get_phone'],
            'num' => $_POST['num'],
            'deliver_price' => $_POST['deliver_price'],
            'freight' => $_POST['freight'],
        );
        $underline_model->edit($id, $underline);
        echo 111;
        exit;
    }

    function supervision() {

        $buyer_name = $_GET['buyer_name']; //买家名
        $tow_days_ago = time() - 172800;  //2天前
        $region_id = $_GET['region_id'];  //省份
        $pagesize = 50;
        $page = $_GET['page'];
        if (empty($page)) {
            $page = 1;
        }
        if ($page == 1) {
            $prev_page = 1;
        } else {
            $prev_page = $page - 1;
        }

        $limit = ($page - 1) . "," . ($pagesize * $page);
        /**
         * 如果选择了省份，就查省份下面所有订单，然后变成字符串
         */
        if (!empty($region_id)) {
            $orderextm_model = & m('orderextm');
            $region_orders = $orderextm_model->find(array(
                'conditions' => "region_id = " . $region_id,
                'fields' => 'order_id'
            ));
            $region_ids = array();
            foreach ($region_orders as $val):
                $region_ids[] = $val['order_id'];
            endforeach;
            $region_id_sting = implode(',', $region_ids);
        }
        $conditions = "status = 20 AND seller_id = 7 AND pay_time != 0 AND pay_time <= " . $tow_days_ago;
        //客户名不为空
        if (!empty($buyer_name)) {
            $conditions .= " AND buyer_name like '%" . $buyer_name . "%'";
        }
        //地区不为空
        if (!empty($region_id_sting)) {
            $conditions .= " AND order_id in (" . $region_id_sting . ")";
        }
        $orders_model = & m('order');
        $orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => 'pay_time,buyer_name,order_id,order_sn',
            'order' => 'pay_time',
            'limit' => $limit,
        ));
        $all_orders = $orders_model->find(array(
            'conditions' => $conditions,
            'fields' => "order_id",
        ));
        $order_count = count($all_orders);
        $all_pages = ceil($order_count / $pagesize);
        if ($page >= $all_pages) {
            $next_page = $all_pages;
        } else {
            $next_page = $page + 1;
        }
        $this->assign('next_page', $next_page);
        $this->assign("prev_page", $prev_page);
        $this->assign('all_pages', $all_pages);
        $this->assign('page', $page);
        $this->assign('buyer_name', $buyer_name);
        $this->assign('region_id', $region_id);
        $this->assign('orders', $orders);
        $this->assign('order_count', $order_count);
        $region_model = & m('region');
        $region = $region_model->findAll();
        $this->assign('region', $region);
        $this->display("supervision.html");
    }

    //导出未发货的数据
    function output_supervision_data() {
        $order_model = & m('order');
        $tow_days = time() - 172800;
        $conditions = "seller_id = 7 AND status = 20 AND pay_time <=" . $tow_days;
        $orders = $order_model->find(array(
            'conditions' => $conditions,
            'fields' => 'pay_time,buyer_name,order_id,order_sn',
            'order' => 'pay_time',
        ));
        //导入各种excel工具
        import('PHPExcel');
        import('PHPExcel/Writer/Excel5');
        import('PHPExcel/Writer/IWriter');
        import('PHPExcel/IOFactory');

        $excelobj = new PHPExcel();

        //表头
        $excelobj->getActiveSheet()->setCellValue('A1', '订单号');
        $excelobj->getActiveSheet()->setCellValue('B1', '购买用户');
        $excelobj->getActiveSheet()->setCellValue('C1', '付款日期');
        $i = 2;
        //出数据
        foreach ($orders as $order):
            $excelobj->getActiveSheet()->setCellValue('A' . $i, $order['order_sn']);
            $excelobj->getActiveSheet()->setCellValue('B' . $i, $order['buyer_name']);
            $excelobj->getActiveSheet()->setCellValue('C' . $i, date('Y-m-d h:i', $order['pay_time']));
            $i++;
        endforeach;
        //写入excel
        $objWriter = PHPExcel_IOFactory::createWriter($excelobj, 'Excel5');
        header("Content-type:text/csv");
        header("Content-Type:application/force-download");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:inline;filename="未发货订单列表.xls"');
        header("Content-Transfer-Encoding: binary");
        header("Expires:Mon,26 Jul 1997 05:00:00 GMT");
        header("Last-Modified:" . gmdate("D, d M Y H:i:s") . "GMT");
        header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
        header("Pragma:no-cache");
        $objWriter->save('php://output');
        exit;
    }

    //发送微信信息
    function _send_message_to_weixin($openid,$template_id,$order_id,$company,$v_no)
    {
        $access_token_model = & m('access_token');
        //先获取服务器上面的access_token;
        $effective_time = time()-7200;  //有效时间2小时
        $access_token_record = $access_token_model->get("create_time > ".$effective_time);
        //如果access_token失效，则重新获取
        if (empty($access_token_record)) {


            $access_token = $this->_get_access_token();


        }else{
            $access_token = $access_token_record['access_token'];
        }
        $url = 'http://open.weixin.qq.com/connect/oauth2/authorize?appid=wx7ccf7c8a377ea11b&redirect_uri=http%3A%2F%2Fwww.126wallpaper.com&response_type=code&scope=snsapi_base&state=123#wechat_redirect';
        $touser = $openid;
        $template_id = $template_id;
        $data = array('first'=>array(value=>'您的订单'.$order_id."已经发货，请及时查收！",'topcolor'=> '#0F12D'),
            'delivername'=>array(value=>$company ,'topcolor'=> '#0F12D'),
            'ordername'=>array(value=>$v_no ,'topcolor'=> '#0F12D'),
            'remark' => array(value=>date('Y-m-d',time()),'topcolor'=> '#0F12D'),
        );
        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'url' => $url,
            'data' => $data
        );
        $json_template = json_encode($template);
        $url = "http://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $access_token;
        $dataRes = $this->request_post($url, urldecode($json_template));
        return $dataRes['errcode'];
//        if ($dataRes['errcode'] == 0) {
//            return true;
//        } else {
//            return false;
//        }


    }

    /**
     * 发送post请求
     * @param string $url
     * @param string $param
     * @return bool|mixed
     */
    function request_post($url = '', $param = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }
        $postUrl = $url;
        $curlPost = $param;
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl); //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0); //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1); //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
//        echo 123;exit;
        $data = curl_exec($ch); //运行curl
        $data =curl_multi_getcontent($ch);
        curl_close($ch);

        return $data;
    }

    //获取access_token
    function _get_access_token(){
        $appid = "wx7ccf7c8a377ea11b";
        $secret = "4cf617291ca6d73b022c13d085dc6da1";
        $token_access_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
        $res = file_get_contents($token_access_url); //获取文件内容或获取网络请求的内容
        $result = json_decode($res, true); //接受一个 JSON 格式的字符串并且把它转换为 PHP 变量
        $access_token = $result['access_token'];
        $access_token_model = & m('access_token');
        $new_access_token = array(
            'access_token' => $access_token,
            'create_time' => time(),
        );
        $access_token_model->edit(1, $new_access_token);
        return $result['access_token'];
    }
}
