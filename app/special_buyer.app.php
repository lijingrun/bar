<?php

//客户查看活动专场控制器
class Special_buyerApp extends MemberbaseApp {

    function index() {
        $special_model = & m('special');
        $goods_spec_model = & m('goodsspec');
        $special_id = $_GET['special_id'];
        $goods_model = & m('goods');
        $special = $special_model->get('special_id =' . $special_id);
        $grade_array = explode(',',$special['sgrade']);
        $grade = $_SESSION['user_info']['sgrade'];
        if (!in_array($grade,$grade_array)) {
            $this->show_warning('对不起，该活动不对您所在等级开放', '请返回', 'index.php');
            return;
        }

        //判断活动的状态
        if ($special['start'] > gmtime()) {
            $s_status = 1; //未开始
            $the_time = $special['start']+28800;
        } elseif ($special['status'] < gmtime() && $special['end'] > gmtime()) {
            $s_status = 2; //未结束
            $the_time = $special['end']+28800;
            $is_start = true;
            $this->assign('is_start', $is_start);
        } else {
            $s_status = 3;
            $the_time = 0;
        }
        $the_time = date("Y/m/d H:i:s",$the_time);
        $this->assign('the_time', $the_time);
        $this->assign('s_status', $s_status);
        //查所有还未开始/还未结束的活动
        $all_specials = $special_model->find(array(
            'conditions' => "end > ".gmtime()." AND is_show = 1",
            'order'      => 'start',
        ));
        foreach($all_specials as $key=>$other_special):
            $all_specials[$key]['start_day'] = date("d日H:i",($other_special['start']+28800));
            if($other_special['start'] < gmtime()){
                $all_specials[$key]['begin'] = 1;
            }else{
                $all_specials[$key]['begin'] = 0;
            }
        endforeach;
        $this->assign('all_specials', $all_specials);
        //查找所有参加活动的商品
        $special_goods_model = & m('special_goods');
        $special_goods = $special_goods_model->find('special_id =' . $special_id);
        //循环得出所有的商品id，然后去重
        $goods_ids_array = array();
        foreach ($special_goods as $val):
            $goods_ids_array[] = $val['goods_id'];
        endforeach;
        $goods_ids = array_unique($goods_ids_array);
        $goods_ids_str = implode(',', $goods_ids);
        //查找goods
        $goods = $goods_model->find(array(
            'conditions' => 'goods_id in (' . $goods_ids_str . ")",
            'fields' => 'goods_name, default_image',
            'order' => 'order_by',
        ));
        //循环产品，将原价和活动价保存进去(取最小的单位价格)
        foreach ($goods as $key => $good):
            $specs = $goods_spec_model->find(array(
                'conditions' => 'goods_id =' . $good['goods_id'] . " AND spec_2 in (" . $special['sgrade'].")",
                'fields' => 'spec_1, original_price, stock',
                'order' => 'price',
            ));
            //循环查看是否库存为零
            $stock = 0;
            $i = 0;
            foreach ($specs as $val):
                if($i == 0){
                    $spec = $val;
                }
                $i++;
                $stock = $stock + $val['stock'];
            endforeach;
            $goods_statistics_model = & m('goodsstatistics');
            $goods_statistics = $goods_statistics_model->get("goods_id =".$good['goods_id']);
            $goods[$key]['goods_statistics'] = $goods_statistics;
            $goods[$key]['stock'] = $stock;
            $goods[$key]['spec'] = $spec;
            $special_goods_prcie = $special_goods_model->get('special_id =' . $special['special_id'] . " AND spec_id =" . $spec['spec_id']);
            $goods[$key]['price'] = $special_goods_prcie['price'];
        endforeach;
//        print_r($goods);exit;
        $this->assign('goods', $goods);
        $this->assign('special', $special);
        $this->display('special_buyer.html');
    }

}
