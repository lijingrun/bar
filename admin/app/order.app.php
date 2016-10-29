<?php

/**
 *    合作伙伴控制器
 *
 *    @author    Garbin
 *    @usage    none
 */
class OrderApp extends BackendApp
{
    /**
     *    管理
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function index()
    {

        $search_options = array(
            'seller_name'   => Lang::get('store_name'),
            'buyer_name'   => Lang::get('buyer_name'),
            'payment_name'   => Lang::get('payment_name'),
            'order_sn'   => Lang::get('order_sn'),
			'sgrade' => Lang::get('store_grade'),
        );
        /* 默认搜索的字段是店铺名 */
        $field = 'seller_name';
        array_key_exists($_GET['field'], $search_options) && $field = $_GET['field'];
        $conditions = $this->_get_query_conditions(array(array(
                'field' => $field,       //按用户名,店铺名,支付方式名称进行搜索
                'equal' => 'LIKE',
                'name'  => 'search_name',
            ),array(
                'field' => 'status',
                'equal' => '=',
                'type'  => 'numeric',
            ),array(
                'field' => 'order_alias.add_time',
                'name'  => 'add_time_from',
                'equal' => '>=',
                'handler'=> 'gmstr2time',
            ),array(
                'field' => 'order_alias.add_time',
                'name'  => 'add_time_to',
                'equal' => '<=',
                'handler'   => 'gmstr2time_end',
            ),array(
                'field' => 'order_amount',
                'name'  => 'order_amount_from',
                'equal' => '>=',
                'type'  => 'numeric',
            ),array(
                'field' => 'order_amount',
                'name'  => 'order_amount_to',
                'equal' => '<=',
                'type'  => 'numeric',
            ),
        ));
        $model_order =& m('order');
        $page   =   $this->_get_page(10);    //获取分页信息
        //更新排序
        if (isset($_GET['sort']) && isset($_GET['order']))
        {
            $sort  = strtolower(trim($_GET['sort']));
            $order = strtolower(trim($_GET['order']));
            if (!in_array($order,array('asc','desc')))
            {
             $sort  = 'order_alias.add_time';
             $order = 'desc';
            }
        }
        else
        {
            $sort  = 'order_alias.add_time';
            $order = 'desc';
        }
        $add_find_condition = ''; //另外的判断条件
        
        //判断如果是墙纸粘贴管理员，则只显示墙纸的订单那
        if($_SESSION['admin_info'][user_id] == 10223){
            $add_find_condition .= ' AND seller_id != 7 AND seller_id != 13';
        }
        $orders = $model_order->find(array(
			// Occur clash between order.add_time and store.add_time...
			'fields'		=> '*, order_alias.add_time',

            'conditions'    => '1=1 ' . $conditions . $add_find_condition,
		    'join'			=> 'belongs_to_store',
			'limit'         => $page['limit'],  //获取当前页的数据
            'order'         => "$sort $order",
            'count'         => true             //允许统计
        )); //找出所有商城的合作伙伴
        $page['item_count'] = $model_order->getCount();   //获取统计的数据
        $this->_format_page($page);
        $this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('order_status_list', array(
            ORDER_PENDING => Lang::get('order_pending'),
            ORDER_SUBMITTED => Lang::get('order_submitted'),
            ORDER_ACCEPTED => Lang::get('order_accepted'),
            ORDER_SHIPPED => Lang::get('order_shipped'),
            ORDER_FINISHED => Lang::get('order_finished'),
            ORDER_CANCELED => Lang::get('order_canceled'),
        ));
        $this->assign('search_options', $search_options);
        $this->assign('page_info', $page);          //将分页信息传递给视图，用于形成分页条
        $this->assign('orders', $orders);
        $this->import_resource(array('script' => 'inline_edit.js,jquery.ui/jquery.ui.js,jquery.ui/i18n/' . i18n_code() . '.js',
                                      'style'=> 'jquery.ui/themes/ui-lightness/jquery.ui.css'));
		
        $this->display('order.index.html');

    }

    //导出订单信息
    function export_order_csv(){
        $start_time = strtotime($_GET['start_time']);
        $end_time = strtotime($_GET['end_time'])+86400;
        if(empty($start_time)){
            $start_time = 0;
        }
        if(empty($end_time)){
            $end_time = gmtime();
        }
        //查询这时间段内已付款的订单
        $order_model = & m('order');
        $orders = $order_model->find(array(
            'conditions' => "status >= 20 AND add_time>=".$start_time." AND add_time <".$end_time,
            'fields'     => 'buyer_id, buyer_name, payment_name, order_amount, order_sn, pay_time, add_time',
        ));
        foreach($orders as $key=>$order):
            $member_model = & m('member');
            $member = $member_model->get("user_id =".$order['buyer_id']);
            $orders[$key]['k3_code'] = $member['k3_code'];
        endforeach;
        if( $orders ) {
            $str = "订单号,会员名称,会员编码,下单时间,付款时间,订单金额,付款方式\n";
            $str = iconv('utf-8','gb2312',$str);
            foreach( $orders as $order ) {
                $buyer_name = iconv('utf-8','gb2312',$order['buyer_name']);
                $pay_name = iconv('utf-8','gb2312',$order['payment_name']);
                if(!empty($order['pay_time'])) {
                    $pay_time = date('Y-m-d H:i:s', $order['pay_time']);
                }else{
                    $pay_time = '';
                }
                $add_time = date('Y-m-d H:i:s', $order['add_time']);
                $str .= $order['order_sn'].','.$buyer_name.','.$order['k3_code'].','.$add_time.','.$pay_time.','.$order['order_amount'].','.$pay_name."\n";
            }
            $filename = 'orders.'.date('Ymd').'.csv';
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

    /**
     *    查看
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function view()
    {
        $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }
        //获取订单产品信息，从而获取评价
        $order_goods_model = & m('ordergoods');
        $order_goods = $order_goods_model->find('order_id ='.$order_id);
        $order_goods = current($order_goods);
        $this->assign('order_goods', $order_goods);
        
        /* 获取订单信息 */
        $model_order =& m('order');
        $order_info = $model_order->get(array(
            'conditions'    => $order_id,
            'join'          => 'has_orderextm',
            'include'       => array(
                'has_ordergoods',   //取出订单商品
            ),
        ));

        if (!$order_info)
        {
            $this->show_warning('no_such_order');
            return;
        }
        $order_type =& ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        $order_info['group_id'] = 0;
        if ($order_info['extension'] == 'groupbuy')
        {
            $groupbuy_mod =& m('groupbuy');
            $groupbuy = $groupbuy_mod->get(array(
                'fields' => 'groupbuy.group_id',
                'join' => 'be_join',
                'conditions' => "order_id = {$order_info['order_id']} ",
                )
            );
            $order_info['group_id'] = $groupbuy['group_id'];
        }
        foreach ($order_detail['data']['goods_list'] as $key => $goods)
        {
            if (substr($goods['goods_image'], 0, 7) != 'http://')
            {
                $order_detail['data']['goods_list'][$key]['goods_image'] = SITE_URL . '/' . $goods['goods_image'];
            }
        }

        //查是否存在金币抵扣现金
        $coin_log_model = & m("coin_log");
        $coin_log = $coin_log_model->get("order_id =".$order_id);
        $this->assign('coin_log',$coin_log);
        //操作记录
        $order_log_model = & m('orderlog');
        $order_log = $order_log_model->find("order_id =".$order_id);
        $this->assign('order_log',$order_log);


        $this->assign('order', $order_info);
        $this->assign($order_detail['data']);
        $this->display('order.view.html');
    }
    
    //自动确认订单
    function automatic(){
        //先查找所有已发货，未确认，给钱超过15日的所有订单
        $order_model = & m('order');
        $days_ago = time()-1296000;//15日前
        $orders = $order_model->find('status = 30 AND pay_time <'.$days_ago);
//        print_r($orders);exit;
        //遍历查找出来的订单，然后将订单状态变成已签收(40)+已评价(evealuation_status = 1),将分给客户，记录积分记录，满分评价(order_goods evealuation = 3) 
        $member_model = & m('member');
        $point_model = & m('redeem_points');
        $order_log_model = & m('orderlog');
        $order_goods_model = & m('ordergoods');
        foreach($orders as $order):
            $member = $member_model->get('user_id ='.$order['buyer_id']);  //找到购买者的账号
            $new_order_log = array(
                'order_id' => $order['order_id'],
                'operator' => '系统',
                'order_status' => '卖家已发货',
                'changed_status' => '交易完成',
                'remark' => '系统自动确认订单',
                'log_time' => time(),
            );
            $order_log_model->add($new_order_log);  //订单状态记录
            $order_goods = $order_goods_model->find('order_id ='.$order['order_id']);  //订单商品
            //遍历评价3分
            foreach($order_goods as $goods):
                $new_goods = $goods;
                $new_goods['evaluation'] = 3;
                $new_goods['is_valid'] = 1;
                $order_goods_model->edit($goods['rec_id'],$new_goods);
            endforeach;
            //如果订单有分数，就加分
            if(!empty($order['total_point'])){
                $new_point = array(
                    'user_id' => $order['buyer_id'],
                    'user_name' => $order['buyer_name'],
                    'created' => time(),
                    'status' => 2,
                    'operator' => '系统',
                    'event' => '系统自动确认订单'.$order['order_sn'].'获得积分',
                    'point' => $order['total_point'],
                );
                $point_model->add($new_point);
                $new_member = $member;
                $new_member['point'] = $member['point'] + $order['total_point'];
                LyLog::log( sprintf( "Calculate result: %s.", $order['total_point'] ), 'autoPoint' );
                LyLog::log("total_point :".$order['total_point'],'autoPoint');
                LyLog::log("order_sn :".$order['order_sn'],'autoPoint');
                $result = $member_model->edit($member['user_id'],$new_member);
                LyLog::log( sprintf( "Finish storing into database, affected lines: %s.", $result ), 'autoPoint' );
            }
            //将订单状态改变而且评价状态改变
            $new_order = $order;
            $new_order['status'] = 40;
            $new_order['evaluation_status'] = 1;
            $order_model->edit($order['order_id'],$new_order);
        endforeach;
        
        
        $this->show_message('操作成功！','正在返回，如果不想等待，直接点击返回','index.php?app=order');
//        exit;
    }
    
    //换排序临时方法
    function test(){
//        $the_date = &m ('goods_pvs');
//        $goods_pvs = $the_date->find(array(
//            'conditions' => "pvs like '%;14:%'",
//        ));
////        print_r($goods_pvs);
//        foreach($goods_pvs as $value):
//            $id = $value['goods_id'];
//            $pvs = $value['pvs'];
//            $a = str_replace('14:', '17:', $pvs);
//            $a = "'".$a."'";
////            echo $a;
//            $the_date->edit($id,'pvs ='.$a);
//            
//        endforeach;
////        print_r($goods_pvs);
//        $this->show_message('转化成功！');
//        return;
////        exit;
    }
}
?>
