<?php

class Order_ruleApp extends StoreadminbaseApp {

    function index() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('member_center'), 'index.php?app=order_rule', LANG::get('订单规则'), 'index.php?app=order_rule', LANG::get('规则列表'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'order_rule';
        $this->_curitem('order_rule_manage');
        $this->_curmenu($type);

        $order_rule_store_model = & m('order_rule_store');
//        $order_rule_model = & m('order_rule');
        $order_rule_products_model = m('order_rule_products');
        $rules = $order_rule_store_model->find(array(
            'conditions' => "store_id =" . $_SESSION['user_info']['store_id'],
            'order' => 'add_time desc',
//            'limit' => 20,
        ));
        foreach ($rules as $key => $rule):
            if($rule['end_time'] < time()){
                $rules[$key]['end'] = true;
            }
            $order_rule_products = $order_rule_products_model->find('sub_rule_id ='.$rule['id']);
            //$goods = $goods_model->get('goods_id =' . $rule['goods_id']);
            //$rules[$key]['goods'] = $goods;
//            $rule = $order_rule_model->get('id =' . $rule['order_rule_id']);
//            $rules[$key]['rule'] = $rule;
            $rules[$key]['goods'] = $order_rule_products;
        endforeach;

        $this->assign('rules', $rules);
        $this->display('order_rule_list.html');
    }


    function add() {

        if (!IS_POST) {
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=order_rule', LANG::get('订单规则'), 'index.php?app=order_rule', LANG::get('新增订单规则'));

            /* 当前用户中心菜单 */
			$type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'order_rule';
            $this->_curitem('order_rule_manage');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);

			$order_rule_model = & m('order_rule');
			$rules = $order_rule_model->findAll();
			$this->assign('rules', $rules);
            
            $this->display('order_rule_add.html');
        } else {
			$order_rule_store_model = & m('order_rule_store');
			$spec_id = $_POST['spec'];
            $name = $_POST['name'];
			$products = $_POST['products'];
			$enabled = $_POST['enabled'];
			$order_rule_id = $_POST['rule_id'];
			$arguments = $_POST['arguments'];
			$display = $_POST['display'];
			$user = $_POST['user'];
			$priority = $_POST['priority']; 
            $start_time = strtotime($_POST['start_time']);
            $end_time = strtotime($_POST['end_time']);
            $goods_model = & m('goods');
            if (empty($name) || empty($start_time) || empty($end_time) ) {
                $this->show_warning('请填写所有需要填写的数据', '', 'index.php?app=order_rule&act=add');
                return;
            } else {
                $time = gmtime();
                $new_rule = array(
                    'name' => $name,
					'user_id' => $user,
					'order_rule_id' => $order_rule_id,
                    'start_time' => $start_time-28800,
                    'end_time' => $end_time-28800,
                    'store_id' => $_SESSION['user_info']['store_id'],
					'arguments' => $arguments,
					'display' => $display,
					'enabled' => $enabled,
					'priority' => $priority,
                );

				if (!$order_rule_id = $order_rule_store_model->add($new_rule) )
				{
					$this->show_warning($order_rule_store_model->get_error());
					return;
				}

                $spec_model = & m('goodsspec');
				$rule_products = & m('order_rule_products');
				$products = explode( ',', $products );
				foreach( $products as $product ) {
					$spec = $spec_model->get('spec_id ='.$product);
                    $goods = $goods_model->get('goods_id ='.$spec['goods_id']);
					$sr_product = array( 
						'sub_rule_id' => $order_rule_id,
						'goods_id' => $spec['goods_id'],
						'spec_id' => $spec['spec_id'],
						'spec_name' => $spec['spec_1'],
                        'goods_name' => $goods['goods_name'],
					);
					$rule_products->add($sr_product);
				}

                $this->show_message('操作成功！', '保存成功', 'index.php?app=order_rule');
            }
        }
    }

    function edit() {

        if (!IS_POST) {
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=order_rule', LANG::get('订单规则'), 'index.php?app=order_rule', LANG::get('修改订单规则'));

            /* 当前用户中心菜单 */
			$type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'order_rule';
            $this->_curitem('order_rule_manage');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);

			$order_rule_model = & m('order_rule');
			$rules = $order_rule_model->findAll();
			$this->assign('rules', $rules);

			$order_rule_store_model = & m('order_rule_store');
			$rules_store = current(	$order_rule_store_model->find( 'id =' . $_GET['rule_id'] ) );
			$this->assign('rules_store', $rules_store);

			$order_rule_products_model = & m('order_rule_products');
			$rules_products = $order_rule_products_model->find( 
				array(
					'conditions' => 'sub_rule_id =' . $_GET['rule_id'],
					'join' => 'belongs_to_goods',	
				)
			);
			$this->assign('rules_products', $rules_products);

            $this->display('order_rule_edit.html');
		} 
		else {
			$order_rule_store_model = & m('order_rule_store');
			$goods_model = & m('goods');
			$id = $_POST['id'];
			$spec_id = $_POST['spec'];
            $name = $_POST['name'];
			$products = $_POST['products'];
			$enabled = $_POST['enable'];
			$order_rule_id = $_POST['rule_id'];
			$arguments = $_POST['arguments'];
			$display = $_POST['display'];
			$user = $_POST['user'];
			$priority = $_POST['priority']; 
            $start_time = strtotime($_POST['start_time']);
            $end_time = strtotime($_POST['end_time']);
            if (empty($name) || empty($start_time) || empty($end_time) ) {
                $this->show_warning('请填写所有需要填写的数据', '', 'index.php?app=order_rule&act=add');
                return;
            } else {
                $time = gmtime();
                $new_rule = array(
                    'name' => $name,
					'user_id' => $user,
					'order_rule_id' => $order_rule_id,
                    'start_time' => $start_time-28800,
                    'end_time' => $end_time-28800,
                    'store_id' => $_SESSION['user_info']['store_id'],
					'arguments' => $arguments,
					'display' => $display,
					'enabled' => $enabled,
					'priority' => $priority,
                );

				if (!$order_rule_id = $order_rule_store_model->edit( $id, $new_rule ) )
				{
//					$this->show_warning($order_rule_store_model->get_error());
//					return;
				}

                $spec_model = & m('goodsspec');
				$rule_products = & m('order_rule_products');
				$rule_products->drop( 'sub_rule_id = '. $id );
				$products = explode( ',', $products );
				foreach( $products as $product ) {
					$spec = $spec_model->get('spec_id ='.$product);
                    $goods = $goods_model->get('goods_id ='.$spec['goods_id']);
					$sr_product = array( 
						'sub_rule_id' => $id,
						'goods_id' => $spec['goods_id'],
						'spec_id' => $spec['spec_id'],
						'spec_name' => $spec['spec_1'],
                        'goods_name' => $goods['goods_name'],
					);
					$rule_products->add($sr_product);
				}

                $this->show_message('操作成功！', '保存成功', 'index.php?app=order_rule');
            }
        }
    }

	function delete() {
		$order_rule_store_model = & m('order_rule_store');
		$rule_id = $_GET['rule_id'];
		if($rule_id) {
			$drop_count = $order_rule_store_model->drop( 'id = '. intval($rule_id) );
			if($drop_count) {
				$this->show_message('删除成功！', '删除成功', 'index.php?app=order_rule');
				return;
			}
		}

		$this->show_message('没有操作', '没有操作', 'index.php?app=order_rule');
	}

    function get_user() {
        $user_name = $_POST['user_name'];
        $user_model = & m('member');
		if($user_name)
			$users = $user_model->find("user_name like '%$user_name%'");
        $count = count($users);
        if ($count == 0) {
            echo "<div id='user_list'>该用户不存在！</div>";
            exit;
        }
        echo "<div id='user_list'>";
        echo "<select id='user' name='user'>";
        echo "<option value='0'>请选择用户</option>";
        foreach ($users as $user):
            echo "<option value='".$user['user_id']."'>".$user['user_name']."</option>";
        endforeach;
        echo "</select>";
        echo "</div>";
        exit;
	}

    function get_spec(){
        $goods_id = $_POST['goods_id'];
        if(empty($goods_id)){
            echo 222;
            exit;
        }
        $spec_model = & m('goodsspec');
        $spec = $spec_model->find('goods_id ='.$goods_id);

        foreach($spec as $val):
			echo "<input type='radio' style='padding-top:5px;' name='spec' value='".$val['spec_id']."' />";
			echo "<span style='color: silver; font-size: 10px;'>[#".$val['spec_id']."] </span><span>".$val['spec_1'];
            switch ($val['spec_2']):
                case 5 : echo '(VIP会员)';
                    break;
                case 2 : echo '(金卡会员)';
                    break;
                case 3 : echo '(铂金会员)';
                    break;
                case 4 : echo '(钻石会员)';
                    break;
                case 1 : echo '(普通账号)';
                    break;
            endswitch;
            echo "</span>".", <span style='color:red' >原价: ".$val['price']."</span>";
            echo "<br />";
        endforeach;
        exit;
    }
    
    //取消活动的激活
    function cancle(){
        $rule_id = $_GET['rule_id'];
        if(empty($rule_id)){
            $this->show_warning('错误提示！','该优惠政策不存在!','index.php');
            return;
        }
        $rule_model = & m('order_rule_store');
        $rule = $rule_model->get('id ='.$rule_id." AND store_id =".$_SESSION['user_info']['store_id']);
        if(empty($rule['id'])){
            $this->show_warning('错误提示！','该优惠政策不存在!','index.php');
            return;
        }
        $rule_model->edit($rule_id,'enabled = 0');
        $this->show_message('操作成功！','政策已取消激活','index.php?app=order_rule');
    }
    
    //激活活动
    function activation(){
        $rule_id = $_GET['rule_id'];
        if(empty($rule_id)){
            $this->show_warning('错误提示！','该优惠政策不存在!','index.php');
            return;
        }
        $rule_model = & m('order_rule_store');
        $rule = $rule_model->get('id ='.$rule_id." AND store_id =".$_SESSION['user_info']['store_id']);
        if(empty($rule['id'])){
            $this->show_warning('错误提示！','该优惠政策不存在!','index.php');
            return;
        }
        $rule_model->edit($rule_id,'enabled = 1');
        $this->show_message('操作成功！','政策已激活','index.php?app=order_rule');
    }

}
