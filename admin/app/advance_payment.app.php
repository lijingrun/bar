<?php
/**
 * Created by PhpStorm.
 * User: lijingrun
 * Date: 2016/1/26
 * Time: 11:03
 * 后台预付款管理模块，主要操作客户的预付款
 */

class Advance_paymentApp extends BackendApp
{
    function index() {
        $user_name = $_GET['field_value'];
        $conditions = "1=1";
        if(!empty($user_name)){
            $conditions .= " AND user_name like '%".$user_name."%'";
        }
        $member_model = & m('member');
        $page = $this->_get_page();
        $users = $member_model->find(array(
            'conditions' => $conditions,
            'fields' => 'user_name, advance, reg_time, last_login',
            'limit' => $page['limit'],
            'order' => "advance desc",
        ));
        $this->assign('users', $users);
        $all_members = $member_model->find($conditions);
        $item_count = count($all_members);
        $page['item_count'] = $item_count;
        $this->_format_page($page);
        $this->assign('filtered', $conditions ? 1 : 0); //是否有查询条件
        $this->assign('page_info', $page);
        $this->display('advance_payment_index.html');
    }

    //查看充值内容
    function recharge(){
        $order_model = & m('order');
        $order_no = $_GET['order_no'];
        $orders = $order_model->find("seller_id = 25242 AND status = 20");
        $page_size = 20;
        $page = $_GET['page'];
        if(empty($page)){
            $page = 1;
        }
        $conditions = "seller_id = 25242 AND status = 40";
        if(!empty($order_no)){
            $conditions .= " AND order_sn =".$order_no;
        }
        $limit = ($page-1)*$page_size.",".$page_size;
        $h_orders = $order_model->find(array(
            'conditions' => $conditions,
            'limit' => $limit,
            'order' => 'add_time desc',
        ));
        if($page == 1){
            $pev_page = 1;
        }else{
            $pev_page = $page - 1;
        }
        $next_page = $page + 1;
        $this->assign('page',$page);
        $this->assign('pev_page',$pev_page);
        $this->assign('next_page',$next_page);
        $this->assign('h_orders',$h_orders);
        $this->assign('orders', $orders);
        $this->display('advance_recharge.html');
    }

    //确认收到款项
    function get_advance(){
        $order_model = & m('order');
        $order_sn = $_GET['order_id'];
        $order = $order_model->get('order_sn ='.$order_sn);
        $admin_id = $_SESSION['admin_info']['user_id'];
        if(empty($admin_id)){
            $this->show_warning('你还未登录，不能操作！');
            return;
        }
        //将对应的余额添加到用户账号上面
        $member_model = & m('member');
        $member = $member_model->get('user_id ='.$order['buyer_id']);
        $advance_before = $member['advance'];
        //查所有的商品原价，将原价总和加到余额里面
        $order_goods_model = & m('ordergoods');
        $order_goods = $order_goods_model->find('order_id ='.$order['order_id']);
        $goods_spec_model = & m('goodsspec');
        //充值总额
        $advance_amount = 0;
        //查找订单里面所有的商品价格，如果存在原价，则是原价*数量，没有，就是现价*数量
        foreach($order_goods as $key=>$order_good):
            $goods_spec = $goods_spec_model->get('spec_id ='.$order_good['spec_id']);
            if($goods_spec['original_price'] > 0){
                $advance_amount += ($goods_spec['original_price']*$order_good['quantity']);
            }else{
                $advance_amount += ($order_good['price']*$order_good['quantity']);
            }
        endforeach;
        $member['advance'] += $advance_amount;
        $advance_after = $member['advance'];
        $member_model->edit($member['user_id'],$member);
        //操作记录
        $new_log = array(
            'admin_id' => $admin_id,
            'order_sn' => $order_sn,
            'create_time' => gmtime(),
            'user_id' => $order['buyer_id'],
            'before' => $advance_before,
            'after' => $advance_after,
            'ip' => real_ip(),
            'amount' => $advance_amount,  //这个是充值金额，不是实际金额
        );
        $advance_log_model = & m('advance_log');
        $advance_log_model->add($new_log);
        //微信推送信息到客户账号
        if(!empty($member['openid'])) {
            $give_amount = $advance_amount - $order['order_amount'];
            $this->_send_message_to_weixin($member['openid'], 'HwekIe50M4k_MIZRXTsVcykfQSWaC1v8yyNu4Zu6Mb0', $order['order_amount'], $give_amount, $member['advance']);
        }
        //将订单变成已发货状态
        $order_model->edit($order['order_id'],"status = 40");
        $this->show_message('操作成功！','已经充值完毕','index.php?app=advance_payment&act=recharge');
        return;
    }

    //查看客户充值记录
    function rechange_log(){
        $user_id = $_GET['user_id'];
        if(empty($user_id)){
            $this->show_warning('对不起','该客户不存在！','index.php?app=advance_payment');
            return;
        }
        $member_model = & m('member');
        $member = $member_model->get('user_id ='.$user_id);
        $advance_log_model = & m('advance_log');
        $logs = $advance_log_model->find("user_id =".$user_id);
        $this->assign('member',$member);
        $this->assign('logs', $logs);
        $this->display('advance_user_log.html');
    }

    //查看客户的余额支付订单记录
    function advance_orders(){
        $user_id = $_GET['user_id'];
        if(empty($user_id)){
            $this->show_warning('对不起','该客户不存在！','index.php?app=advance_payment');
            return;
        }
        $order_model = & m('order');
        $orders = $order_model->find('buyer_id ='.$user_id." AND payment_code = 'advance'");
        $this->assign('orders',$orders);
        $this->display('advance_orders.html');
    }


    //查看已经通过平台的余额支付支付了的客户订单，需要后台做操作发货
    function consumption(){
        $order_model = & m('order');
        $orders = $order_model->find("status = 20 AND payment_code = 'advance' AND out_trade_sn = ''");
        $this->assign('orders', $orders);
        $this->display('advance_consumption.html');
    }

    //确认已经扣除了对应余额
    function consumption_sure(){
        $order_sn = $_POST['order_sn'];
        $order_model = & m('order');
        $order = $order_model->get("order_sn =".$order_sn);
        //将操作时间作为外部订单号，区别未确认的订单
        $order_model->edit($order['order_id'],'out_trade_sn ='.gmtime());
        //后台确认后才进入K3下推订单
        $member_model = & m('member');
        $member = $member_model->get('user_id ='.$order['buyer_id']);
        if(($order['seller_id'] == 7 || $order['seller_id'] == 24480) && !empty($member['k3_code'])){
            if($order['seller_id'] == 7){
                $this->_order_in_k3($member['k3_code'],$member['user_name'],$order,'余额支付');
            }else{
                $order_member = $member_model->get('user_id ='.$order['seller_id']);
                $this->_order_in_k3($order_member['k3_code'],$order_member['user_name'],$order,'余额支付');
            }
        }
        echo 111;
        exit;
    }

    //取消订单，将余额返还给客户
    public function cancle_advance(){
        $order_sn = $_POST['order_sn'];
        $order_model = & m('order');
        $order = $order_model->get("order_sn =".$order_sn);
        $buyer_id = $order['buyer_id'];
        $member_model = & m('member');
        $buyer = $member_model->get('user_id ='.$buyer_id);
        $advance_log = & m('advance_log');
        //先保存操作记录
//        print_r($_SERVER);exit;
        $new_log = array(
            'admin_id' => $_SESSION['admin_info']['user_id'],
            'order_sn' => $order_sn,
            'create_time' => time(),
            'ip' => $_SERVER['SERVICE_ADDR'],
            'user_id' => $buyer_id,
            'before' => $buyer['advance'],
            'after' => $buyer['advance']+$order['order_amount'],
            'amount' => $order['order_amount']
        );
        $advance_log->add($new_log);
        $buyer['advance'] += $order['order_amount'];
        $member_model->edit($buyer_id, $buyer);
        $order['status'] = 0;
        $order_model->edit($order['order_id'], $order);
        //后台确认后才进入K3下推订单
        $order_act_model = & m('order_act');
        $new_act = array(
            'Order_Id' => $order_sn,
            'Act_state' => 11,
        );
        $order_act_model->add($new_act);
        echo 111;
        exit;
    }

    //发送微信信息(充值到账模板)
    function _send_message_to_weixin($openid,$template_id,$order_amount,$give_amount,$advance)
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
        $data = array('first'=>array(value=>'充值金额到账通知','topcolor'=> '#0F12D'),
            'keyword1'=>array(value=>$order_amount ,'topcolor'=> '#0F12D'),
            'keyword2'=>array(value=>$give_amount ,'topcolor'=> '#0F12D'),
            'keyword3'=>array(value=>'蓝羽维涅斯商城线上充值' ,'topcolor'=> '#0F12D'),
            'keyword4'=>array(value=>$advance ,'topcolor'=> '#0F12D'),
            'remark' => array(value=>'如有疑问，请联系商城客服人员','topcolor'=> '#0F12D'),
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

    //查看有优惠的订单
    public function discount(){
        $start_time = strtotime($_GET['start_time']);
        $end_time = strtotime($_GET['end_time']);
        if(empty($start_time)){
            $start_time = 0;
        }
        if(empty($end_time)){
            $end_time = gmtime();
        }
        $order_model = & m('order');
        $conditions = "discount > 0 AND status >= 20 AND pay_time >=".$start_time." AND pay_time <".$end_time;
        $orders = $order_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_sn, buyer_name, discount, pay_time',
        ));
        $this->import_resource(array('script' => 'inline_edit.js,jquery.ui/jquery.ui.js,jquery.ui/i18n/' . i18n_code() . '.js',
            'style'=> 'jquery.ui/themes/ui-lightness/jquery.ui.css'));
        $this->assign('start_time',$_GET['start_time']);
        $this->assign('end_time',$_GET['end_time']);
        $this->assign('orders',$orders);
        $this->display('discount_list.html');
    }

	public function export_discount_csv() {
        $start_time = strtotime($_GET['start_time']);
        $end_time = strtotime($_GET['end_time']);
        if(empty($start_time)){
            $start_time = 0;
        }
        if(empty($end_time)){
            $end_time = gmtime();
        }
        $order_model = & m('order');
        $conditions = "discount > 0 AND status >= 20 AND pay_time >=".$start_time." AND pay_time <".$end_time;
        $orders = $order_model->find(array(
            'conditions' => $conditions,
            'fields' => 'order_sn, buyer_name, discount, pay_time',
        ));

		if( $orders ) {
			$str = "订单号,会员名称,优惠金额,付款时间\n";   
			$str = iconv('utf-8','gb2312',$str);   
			foreach( $orders as $order ) {
				$buyer_name = iconv('utf-8','gb2312',$order['buyer_name']);
				$pay_time = date('Y-m-d H:i:s', $order['pay_time'] );
				$str .= $order['order_sn'].','.$buyer_name.','.$order['discount'].','.$pay_time."\n";
			}
			$filename = 'discounts.'.date('Ymd').'.csv';
			$this->export_csv($filename,$str);
		}
		else {
			echo '没有符合条件的优惠券(<a href="javascript: history.back();">返回</a>)';	
		}
	}

	function export_csv($filename,$data)   
	{   
		header("Content-type:text/csv");   
		header("Content-Disposition:attachment;filename=".$filename);   
		header('Cache-Control:must-revalidate,post-check=0,pre-check=0');   
		header('Expires:0');   
		header('Pragma:public');   
		echo $data;   
	}  
}
