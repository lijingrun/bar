<?php

class Fortune_wheelApp extends StoreadminbaseApp {

    function index() {
        /* 当前位置 */
        $this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('首页'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';

		$fortune_wheel_model = & m('fortune_wheel');
        $this->_curitem('fortune_wheel_manage');
        $this->_curmenu($type);

		$fortune_wheels = $fortune_wheel_model->findAll();
		$this->assign('fortune_wheels', $fortune_wheels);

        $this->display('fortune_wheel.index.html');
    }


    function add() {

		$fortune_wheel_model = & m('fortune_wheel');

        if (!IS_POST) {
            /* 当前位置 */
			$this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('新建'));

            /* 当前用户中心菜单 */
			$type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';
            $this->_curitem('fortune_wheel');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);

			$fortune_wheels = $fortune_wheel_model->findAll();
			$this->assign('fortune_wheels', $fortune_wheels);
            
			$this->display('fortune_wheel.form.html');
        } else {
            $name = $_POST['name'];
			$available = $_POST['available'];
			$info = $_POST['info'];
            $start_time = strtotime($_POST['start_time']);
            $end_time = strtotime($_POST['end_time']);
            $activity_start_time = strtotime($_POST['activity_start_time']);
            $activity_end_time = strtotime($_POST['activity_end_time']);

            if (empty($name) || empty($start_time) || empty($end_time) || empty($activity_start_time) || empty($activity_end_time)) {
                $this->show_warning('请填写所有需要填写的数据', '', 'index.php?app=fortune_wheel&act=add');
                return;
            } else {
                $time = gmtime();
                $new_fw = array(
                    'name' => $name,
                    'start_time' => $start_time-28800,
                    'end_time' => $end_time-28800,
                    'activity_start_time' => $activity_start_time-28800,
                    'activity_end_time' => $activity_end_time-28800,
                    'store_id' => $_SESSION['user_info']['store_id'],
					'info' => $info,
					'available' => $available,
                );

				if (!$fw_id = $fortune_wheel_model->add($new_fw) )
				{
					$this->show_warning($fortune_wheel_model->get_error());
					return;
				}

                $this->show_message('操作成功！', '保存成功', 'index.php?app=fortune_wheel');
            }
        }
    }

    function edit() {
		$fw_id = $_GET['fw_id'];
		$fw_model = & m('fortune_wheel');
		$fw = $fw_model->get('id ='.$fw_id);
		if(!IS_POST){
			/* 当前位置 */
			$this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('新建'));

			/* 当前用户中心菜单 */
			$type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';
			$this->_curitem('fortune_wheel');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
			$this->_curmenu($type);
			$start_time = date("Y-m-d",$fw['start_time']+28800);
			$end_time = date("Y-m-d",$fw['end_time']+28800);
			$activity_end_time = date("Y-m-d",$fw['activity_end_time']+28800);
			$activity_start_time = date("Y-m-d",$fw['activity_start_time']+28800);
			$this->assign('fw',$fw);
			$this->assign('s_time',$start_time);
			$this->assign('end_time',$end_time);
			$this->assign('activity_end_time',$activity_end_time);
			$this->assign('activity_start_time',$activity_start_time);
			$this->display('fortune_wheel.form.html');
		}else{
			$fw_id = $_POST['fw_id'];
			$fw = $fw_model->get('id ='.$fw_id);
			$available = 1;
			if(empty($_POST['available'])){
				$available = 0;
			}
			$fw['name'] = $_POST['name'];
			$fw['start_time'] = strtotime($_POST['start_time'])-28800;
			$fw['end_time'] = strtotime($_POST['end_time'])-28800;
			$fw['activity_start_time'] = strtotime($_POST['activity_start_time'])-28800;
			$fw['activity_end_time'] = strtotime($_POST['activity_end_time'])-28800;
			$fw['info'] = $_POST['info'];
			$fw['available'] = $available;
			$fw_model->edit($fw_id,$fw);
			$this->show_message('修改成功！','操作完成','index.php?app=fortune_wheel');
		}
	}
	
	function prize_list() {
        $this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('奖品列表'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';
			
		$fw_prize_model = & m('fortune_wheel_prize');
        $this->_curitem('fortune_wheel_manage');
        $this->_curmenu($type);

		if( isset($_GET['fw_id']) ) {
			$fw_prizes = $fw_prize_model->find(
				array(
					'conditions' => "fw_id =". $_GET['fw_id'],
				)
			);
			$this->assign('fw_id', $_GET['fw_id']);
		} else {
			$fw_prizes = $fw_prize_model->findAll();
		}

		$this->assign('fw_prizes', $fw_prizes);

        $this->display('fortune_wheel.prize.html');		
	}

	function prize_form() {

		$fw_prize_model = & m('fortune_wheel_prize');

        if (!IS_POST) {
            /* 当前位置 */
			$this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('新建奖品'));

            /* 当前用户中心菜单 */
			$type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';
            $this->_curitem('fortune_wheel');    //默认选择项，填写frontent.besc里面菜单列表对应的name项
            $this->_curmenu($type);

			$fw_prizes = $fw_prize_model->findAll();
			$this->assign('fw_prizes', $fw_prizes);
			$this->assign('fw_id', $_GET['fw_id']);
            
			$this->display('fortune_wheel.prize.form.html');
        } else {
            $fw_id = $_POST['fw_id'];
			$name = $_POST['name'];
			$product_name = $_POST['product_name'];
			$product_quantity = $_POST['product_quantity'];
			$product_remain = $product_quantity;
			$relate_url = $_POST['relate_url'];
			$level = $_POST['level'];
			$rate = $_POST['rate'];
			$info = $_POST['info'];

            if (empty($name)) {
                $this->show_warning('请填写所有需要填写的数据', '', 'index.php?app=fortune_wheel&act=prize_form&fw_id='.$fw_id);
                return;
            } else {
                $time = gmtime();
                $new_fwp = array(
                    'fw_id' => $fw_id,
                    'name' => $name,
                    'product_name' => $product_name,
                    'product_quantity' => $product_quantity,
                    'product_remain' => $product_remain,
                    'relate_url' => $relate_url,
                    'level' => $level,
					'info' => $info,
					'rate' => $rate,
                );

				if (!$fwp_id = $fw_prize_model->add($new_fwp) )
				{
					$this->show_warning($fw_prize_model->get_error());
					return;
				}

                $this->show_message('操作成功！', '保存成功', 'index.php?app=fortune_wheel&act=prize_list&fw_id='.$fw_id);
            }
        }

	}

	function log() {
        $this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('抽奖列表'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';
			
		$fw_log_model = & m('fortune_wheel_log');
        $this->_curitem('fortune_wheel_manage');
        $this->_curmenu($type);
		//查所有活动
		$fw_model = & m('fortune_wheel');
		$fws = $fw_model->find('store_id ='.$_SESSION['user_info']['store_id']." AND available = 1");
		$this->assign('fws',$fws);
		if( isset($_GET['fw_id']) ) {
			$fw_logs = $fw_log_model->find(
				array(
					'conditions' => "fw_id =". $_GET['fw_id']." AND status = 'win'",
				)
			);
			$this->assign('fw_id', $_GET['fw_id']);
		} else {
			$fw_logs = $fw_log_model->find(array(
				'conditions' => "status = 'win'",
			));
		}
		$prize_model = & m('fortune_wheel_prize');
		$sign_member_model = & m('fortune_wheel_sign_member');
		foreach($fw_logs as $key=>$log):
			$fw_logs[$key]['prize'] = $prize_model->get("id = ".$log['prize_id']);
			$fw_logs[$key]['sign'] = $sign_member_model->get("fw_id =".$log['fw_id']." AND user_id=".$log['user_id']);
		endforeach;
		$this->assign('fw_logs', $fw_logs);

        $this->display('fortune_wheel.log.html');		
	}

	function prize_edit(){
		$this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('用户列表'));

		/* 当前用户中心菜单 */
		$type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';

		$fw_member_model = & m('fortune_wheel_sign_member');
		$this->_curitem('fortune_wheel_manage');
		$this->_curmenu($type);
		$prize_id = $_GET['fwp_id'];
		$prize_model = & m('fortune_wheel_prize');
		$prize = $prize_model->get("id =".$prize_id);
		if(!IS_POST){
			$this->assign('prize',$prize);
			$this->assign('fw_id',$prize['fw_id']);
			$this->display('fortune_wheel.prize.form.html');
		}else{
			$prize_id = $_POST['prize_id'];
			$prize = $prize_model->get("id =".$prize_id);
			$prize['name'] = $_POST['name'];
			$prize['product_name'] = $_POST['product_name'];
			$prize['product_quantity'] = $_POST['product_quantity'];
			$prize['relate_url'] = $_POST['relate_url'];
			$prize['level'] = $_POST['level'];
			$prize['rate'] = $_POST['rate'];
			$prize['info'] = $_POST['info'];
			$prize_model->edit($prize_id,$prize);
			$this->show_message('修改完成','操作成功','index.php?app=fortune_wheel&act=prize_list');

		}
	}

	function sign() {
        $this->_curlocal(LANG::get('抽奖游戏'), 'index.php?app=fortune_wheel', LANG::get('幸运大转盘'), 'index.php?app=fortune_wheel', LANG::get('用户列表'));

        /* 当前用户中心菜单 */
        $type = (isset($_GET['type']) && $_GET['type'] != '') ? trim($_GET['type']) : 'fortune_wheel';
			
		$fw_member_model = & m('fortune_wheel_sign_member');
        $this->_curitem('fortune_wheel_manage');
        $this->_curmenu($type);
		$page = $_GET['page'];
		$page_size = 20;
		if(empty($page)){
			$page = 1;
		}
		$start_page = $page_size*($page-1);
		$limit = $start_page.",".$page_size;
		if( !empty($_GET['fw_id']) ) {
			$fw_members = $fw_member_model->find(
				array(
					'conditions' => "fw_id =". $_GET['fw_id'],
					'limit'      => $limit,
				)
			);
			$this->assign('fw_id', $_GET['fw_id']);
		} else {
			$fw_members = $fw_member_model->find(array(
				'limit'      => $limit,
			));;
		}
		$member_model = & m('member');
		$fw_model = & m("fortune_wheel");
		foreach($fw_members as $key=>$fw_member):
			$fw_members[$key]['member'] = $member_model->get("user_id =".$fw_member['user_id']);
			$fw_members[$key]['fw'] = $fw_model->get("id =".$fw_member['fw_id']);
		endforeach;
		if($page == 1){
			$pev_page = 1;
		}else{
			$pev_page = $page -1;
		}
		$all_members = $fw_member_model->findAll();
		$count = count($all_members);
		$all_fws = $fw_model->findAll();
		$this->assign('all_fws', $all_fws);
		$this->assign('count', $count);
		$next_page = $page +1;
		$this->assign('page',$page);
		$this->assign('pev_page',$pev_page);
		$this->assign('next_page', $next_page);
		$this->assign('fw_signs', $fw_members);
		$this->assign('fw_id', $_GET['fw_id']);
        $this->display('fortune_wheel.sign.html');		
	}

	function prize_delete() {
		$fw_prize_model = & m('fortune_wheel_prize');
        $fwp_id = $_GET['fwp_id'];

		if($fwp_id) {
			$drop_count = $fw_prize_model->drop( 'id = '. intval($fwp_id) );
			if($drop_count) {
				$this->show_message('删除成功！', '删除成功', 'index.php?app=fortune_wheel');
				return;
			}
		}

		$this->show_message('没有操作', '没有操作', 'index.php?app=fortune_wheel');
	}

    //取消活动的激活
    function cancle(){
        $fw_id = $_GET['fw_id'];
        if(empty($fw_id)){
            $this->show_warning('错误提示！','该抽奖不存在!','index.php');
            return;
        }
        $fortune_wheel_model = & m('fortune_wheel');
        $fortune_wheel = $fortune_wheel_model->get('id ='.$fw_id." AND store_id =".$_SESSION['user_info']['store_id']);
        if(empty($fortune_wheel['id'])){
            $this->show_warning('错误提示！','该抽奖不存在!','index.php');
            return;
        }
        $fortune_wheel_model->edit($fw_id, 'available = 0');

        $this->show_message('操作成功！','抽奖已激活','index.php?app=fortune_wheel');
    }
    
    //激活活动
    function activation(){
        $fw_id = $_GET['fw_id'];
        if(empty($fw_id)){
            $this->show_warning('错误提示！','该抽奖不存在!','index.php');
            return;
        }
        $fortune_wheel_model = & m('fortune_wheel');
        $fortune_wheel = $fortune_wheel_model->get('id ='.$fw_id." AND store_id =".$_SESSION['user_info']['store_id']);
        if(empty($fortune_wheel['id'])){
            $this->show_warning('错误提示！','该抽奖不存在!','index.php');
            return;
        }
        $fortune_wheel_model->edit($fw_id, 'available = 1');

        $this->show_message('操作成功！','抽奖已激活','index.php?app=fortune_wheel');
    }


	function _get_member_submenu() {
        $array = array(
            array(
                'name' => '大转盘列表',
                'url' => 'index.php?app=fortune_wheel',
            ),
            array(
                'name' => '创建/修改大转盘',
                'url' => 'index.php?app=fortune_wheel&amp;act=add',
			),        
			array(
                'name' => '奖品列表',
                'url' => 'index.php?app=fortune_wheel&amp;act=prize_list',
			),  
			array(
                'name' => '创建/修改奖品',
                'url' => 'index.php?app=fortune_wheel&amp;act=prize_form',
			),  
			array(
                'name' => '抽奖列表',
                'url' => 'index.php?app=fortune_wheel&amp;act=log',
			),    
			array(
                'name' => '参加用户',
                'url' => 'index.php?app=fortune_wheel&amp;act=sign',
			),    
	
		);
        return $array;
    }

}
