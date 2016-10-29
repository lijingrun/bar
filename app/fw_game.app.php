<?php

class Fw_gameApp extends MallbaseApp {

	var $err_state = array(
		'ERROR_SUCCESS' =>          array('err_code' =>  0, 'err_msg' => 'Success.',),

		'ERROR_NOT_LOGIN' =>        array('err_code' =>  1, 'err_msg' => '您还未登录.', ),
		'ERROR_NOT_STORE' =>        array('err_code' =>  2, 'err_msg' => 'Your account do not have a store.',),
		'ERROR_NOT_VIP' =>          array('err_code' =>  3, 'err_msg' => "你不是vip会员，不能参加该活动",),
		'ERROR_NOT_SIGN_OR_VAILD' =>array('err_code' =>  4, 'err_msg' => "You have not checked in or not a vaild user.",),

		'ERROR_NO_FORTUNE_WHEEL' => array('err_code' => 10, 'err_msg' => '不在活动期间',),
		'ERROR_NOT_IN_RANGE' =>     array('err_code' => 11, 'err_msg' => '活动还未开始',),

		'ERROR_WRITE_MEMBER_FAIL' =>array('err_code' => 20, 'err_msg' => 'Write member failure.',),
		'ERROR_CHECK_IN_FAIL' =>    array('err_code' => 21, 'err_msg' => 'Check in failure.',),
		'ERROR_WRITE_LOG' =>        array('err_code' => 22, 'err_msg' => 'Fail to write fw logs',),

		'ERROR_ALREADY_CHECKED_IN'=>array('err_code' => 30, 'err_msg' => '您今天已经签到了',),

		'ERROR_NO_RAFFLE_REMAINS' =>array('err_code' => 40, 'err_msg' => '您的抽奖机会已经用完了',),

		'ERROR_NO_FW_ID' =>         array('err_code' => 50, 'err_msg' => '抽奖活动不存在',),
	);

    function index() {
		$fw_id = $_GET['id'];
		//先检查客户是否报名了，如果没有报名，就跳转到报名页面
		$member_sign_model = & m('fortune_wheel_sign_member');
		if(empty($_SESSION['user_info']['user_id'])){
			$this->show_message('本活动需要登录','请先登录','index.php?app=member&act=login');
			return false;
		}
		$member_sign = $member_sign_model->get("fw_id =".$fw_id." AND user_id =".$_SESSION['user_info']['user_id']);
		if(empty($member_sign)){
			$this->show_message('本活动需要报名','请先进行报名','index.php?app=fw_game&act=sign_up&id='.$fw_id);
			return;
		}
		$fortune_wheel_model = & m('fortune_wheel');
		$fortune_wheel = $fortune_wheel_model->get('id ='.$fw_id);
		$start_year = date("Y",$fortune_wheel['start_time']);
		$start_day = date("m月d日",$fortune_wheel['start_time']+28800);
		//查签到了的日期
		$fortune_wheel_sign_model = & m('fortune_wheel_sign_log');
		$sign_time = 0;
		if(!empty($_SESSION['user_info']['user_id'])) {
			$fortune_wheel_signs = $fortune_wheel_sign_model->find('fw_id =' . $fw_id . " AND user_id = " . $_SESSION['user_info']['user_id']);
			$dataArray = "[";
			//今个月：
			$now_month = date("m",gmtime());
			foreach ($fortune_wheel_signs as $val):
				$date_a = strtotime($val['sign_date']);
				if(date("m", $date_a) == $now_month) {
					$dataArray .= (date("d", $date_a) - 1) . ",";
				}
				$sign_time++;

			endforeach;
			$dataArray .= ']';
		}else{
			$dataArray = "[]";
		}
		//查奖品
		$prize_model = & m('fortune_wheel_prize');
		$prizes = $prize_model->find(array(
			'conditions' => 'fw_id ='.$fw_id,
			'order' => 'id',
		));
		//轮盘上面的奖品显示，一个奖品显示一次，然后每个奖品之间隔着不中奖
		$restaraunts = '[';
		$colors = '[';
		foreach($prizes as $prize):
			$restaraunts .= "'".$prize['name']."','谢谢参与',";
			$colors .= "'#FFF4D6','#FFFFFF',";
		endforeach;
		$restaraunts .= "]";
		$colors .= "]";

		//查抽奖次数
		$prize_time_model = & m('fortune_wheel_sign_member');
		$prize_times = $prize_time_model->get("fw_id =".$fw_id." AND user_id =".$_SESSION['user_info']['user_id']);
		//中奖情况
		$prize_log_model = & m('fortune_wheel_log');
		$prize_log = $prize_log_model->find("fw_id =".$fw_id." AND prize_id != 0");
		$member_model = & m('member');
		foreach($prize_log as $key=>$val):
			$prize_log[$key]['member'] = $member_model->get('user_id ='.$val['user_id']);
			$prize_log[$key]['prize'] = $prize_model->get("id =".$val['prize_id']);
		endforeach;
		//我的中奖情况
		$fortune_wheel_log = & m('fortune_wheel_log');
		$my_fortune = $fortune_wheel_log->get("fw_id = ".$fw_id." AND status = 'win' AND user_id = ".$_SESSION['user_info']['user_id']);
		$this->assign('my_fortune', $my_fortune);
		$this->assign('restaraunts' , $restaraunts);
		$this->assign('colors' , $colors);
		$this->assign('sign_time',$sign_time);
		$this->assign('prize_log', $prize_log);
		$this->assign('prize_times',$prize_times);
		$this->assign('prizes',$prizes);
		$this->assign('dateArray',$dataArray);
		$this->assign('start_day',$start_day);
		$this->assign('start_year',$start_year);
		$this->assign('fortune_wheel',$fortune_wheel);
		$this->display('fw_game.index.html');
    }

	//根据省份id获取城市
	public function find_city(){
		$province_id = $_POST['province_id'];
		$city_model = & m('city');
		$citys = $city_model->find("topid =".$province_id);
		echo "<select class='select' name='city' id='city'>";
		foreach($citys as $city):
			echo "<option value='".$city['id']."'>".$city['name']."</option>";
		endforeach;
		echo "</select>";
		exit;
	}

	function random_float($min = 0, $max = 1) {
		return $min + mt_rand() / mt_getrandmax() * ( $max - $min );
	}

	function find_game(){
		$fw_model = & m('fortune_wheel');
		$fw = $fw_model->get("activity_start_time <".gmtime()." AND activity_end_time >".gmtime());
		if(empty($fw['id'])){
			$this->show_message('暂时没有大转盘活动','index.php');
			return;
		}else{
			$this->show_message('正在转入，请稍后','','/index.php?app=fw_game&id='.$fw['id']);
			return;
		}
	}

	function get_checked_date() {
		// init
		$fw_id = $_GET['fw_id'];
		$fw_member_log_model = & m('fortune_wheel_sign_log'); 
		$user_id = $_SESSION['user_info']['user_id'];

		if( !empty($fw_id) && !empty($user_id) ) {
			$fw_member_logs = $fw_member_log_model->find( "user_id =". $user_id . " and fw_id =". $fw_id );
		}

		$dates = array();
		foreach ($fw_member_logs as $logs) {
			array_push($dates, strtotime( $logs['sign_date'] ));
		}

		echo json_encode($dates);
	}


	//查看自己中奖情况
	function check_wheel_log(){
		$user_id = $_SESSION['user_info']['user_id'];
		$fw_id = $_GET['fw_id'];
		$fortune_wheel_log_model = & m("fortune_wheel_log");
		$my_logs = $fortune_wheel_log_model->find("status = 'win' AND user_id =".$user_id." AND fw_id =".$fw_id);
		$prize_model = & m("fortune_wheel_prize");
		foreach($my_logs as $key=>$my_log):
			$my_logs[$key]['prize'] = $prize_model->get("id =".$my_log['prize_id']);
		endforeach;
		$this->assign('my_logs', $my_logs);
		$sign_modle = & m("fortune_wheel_sign_member");
		$my_sign = $sign_modle->get("fw_id =".$fw_id." AND user_id =".$user_id);
		$this->assign('my_sign', $my_sign);
		$this->display('fw_my_prize.html');
	}

	//报名
	function sign_up(){
		$province_model = & m('province');
		$sign_up_model = & m('fortune_wheel_sign_member');
		if(!IS_POST){
			$provinces = $province_model->findAll();
			$fw_id = $_GET['id'];
			$fw_model = & m('fortune_wheel');
			$fw = $fw_model->get('id ='.$fw_id);
			$fw_member = $sign_up_model->get("fw_id =".$fw_id." AND user_id =".$_SESSION['user_info']['user_id']);
			if(!empty($fw_member)){
				$this->show_message('您已经报名了','不需要重复报名','index.php?app=member');
				return;
			}
			$this->assign('provinces', $provinces);
			$this->assign('fw',$fw);
			$this->display('fw_sign_up.html');
		}else{
			$fw_id = $_POST['fw_id'];
			$fw_member = $sign_up_model->get("fw_id =".$fw_id." AND user_id =".$_SESSION['user_info']['user_id']);
			if(!empty($fw_member)){
				$this->show_message('您已经报名了','不需要重复报名','index.php?app=member');
				return;
			}
			$member_model = & m('member');
			$province_id = $_POST['province'];
			$city_id = $_POST['city'];
			$province = $province_model->get("id =".$province_id);
			$city_model = & m('city');
			$city = $city_model->get("id =".$city_id);
			$member = $member_model->get('user_id ='.$_SESSION['user_info']['user_id']);
			$member['birthday'] = $_POST['birthday'];
			$member['real_name'] = $_POST['name'];
			$member['phone_tel'] = $_POST['phone'];
			$member_model->edit($member['user_id'],$member);
			$new_sign = array(
				'fw_id' => $fw_id,
				'user_id' => $_SESSION['user_info']['user_id'],
				'remain_times' => 1,
				'addition_rate' => 1,
				'address' => $province['name'].$city['name'].$_POST['address'],
				'phone' => $_POST['phone'],
				'name' => $_POST['name'],
			);
			$sign_up_model->add($new_sign);
			$this->show_message('报名成功！','请进行签到','index.php?app=fw_game&id='.$fw_id);
			return;
		}
	}

	function check_in() {
		// models
        $member_model = & m('member');
		$store_model = & m('store');
		$fortune_wheel_model = & m('fortune_wheel'); 
		$fw_member_model = & m('fortune_wheel_sign_member'); 
		$fw_member_log_model = & m('fortune_wheel_sign_log'); 

		// init
		$fw_id = $_GET['fw_id'];
	
		$now = gmtime();
		$beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
		$endToday = mktime(0, 0, 0, date('m'), date('d')+1, date('Y'))-1;

		// get users
        $user_id = $_SESSION['user_info']['user_id'];
		if( empty($user_id) ) {
			echo json_encode($this->err_state['ERROR_NOT_LOGIN']);
			return false;
		}

        $member = $member_model->get('user_id =' . $user_id);
		$store = $store_model->get('store_id =' . $user_id);
		if( empty($store) ) {
			echo json_encode($this->err_state['ERROR_NOT_STORE']);
			return false;
		}
		else {
			if($store['sgrade'] == 1 || empty($store['sgrade'])) {
				echo json_encode($this->err_state['ERROR_NOT_VIP']);
				return false;
			}	
		}
		
		if( isset($fw_id) && !empty($fw_id) ) {
			$fw = $fortune_wheel_model->get( 'id=' . $fw_id . ' and available = 1' );
			if( empty($fw) ) {
				echo json_encode($this->err_state['ERROR_NO_FORTUNE_WHEEL']);
				return false;
			} else {
				if( $fw['activity_start_time'] > $now || $fw['activity_end_time'] < $now ) {
					echo json_encode($this->err_state['ERROR_NOT_IN_RANGE']);
					return false;
				}
			}

			$fw_members = $fw_member_model->find(
				array(
					'conditions' => "user_id =". $member['user_id']. " and fw_id =". $fw_id,
				)
			);
			if( empty($fw_members) ) {
				$time = gmtime();
				$new_fwm = array(
					'fw_id' => $fw_id,
					'user_id' => $member['user_id'],
					'remain_times' => 1,
					'addition_rate' => 1.0,
				);
				if (!$fwm_id = $fw_member_model->add($new_fwm) ) {
					echo json_encode($this->err_state['ERROR_WRITE_MEMBER_FAIL']);
					return false;
				}
			}

			$fw_member_logs = $fw_member_log_model->find(
				array(
					'conditions' => "user_id =". $member['user_id'] . " and fw_id =". $fw_id. 
						" and unix_timestamp(sign_date) >= ". $beginToday . " and unix_timestamp(sign_date) < ". $endToday,
				)
			);
			if( empty( $fw_member_logs ) ) {
				$new_fwml = array(
					'fw_id' => $fw_id,
					'user_id' => $member['user_id'],
				);
				if (!$fwml_id = $fw_member_log_model->add($new_fwml) ) {
					echo json_encode($this->err_state['ERROR_CHECK_IN_FAIL']);
					return false;
				}
				else {
					// TO-DO: here to add fw_member,
					// change the addition rate.
					//查所有的签到记录，可以整除就加次数
					$sign_log_model = & m('fortune_wheel_sign_log');
					$sign_log = $sign_log_model->find("fw_id =".$fw_id." AND user_id =".$user_id);
					$sign = count($sign_log)/10;
					if(is_int($sign)){
						$sign_member = $fw_member_model->get("fw_id =".$fw_id." AND user_id =".$user_id);
						$sign_member['remain_times'] += 1;
						$fw_member_model->edit($sign_member['id'],$sign_member);
					}
					echo 'change the addition rate';
				}
			}
			else {
				echo json_encode($this->err_state['ERROR_ALREADY_CHECKED_IN']);
				return false;
			}
		} 
		else {
			echo json_encode($this->err_state['ERROR_NO_FW_ID']);
			return false;
		}

		echo json_encode($this->err_state['ERROR_SUCCESS']);
		return true;
	}

	function raffle() {
		// models
        $member_model = & m('member');
		$store_model = & m('store');
		$fortune_wheel_model = & m('fortune_wheel'); 
		$fw_log_model = & m('fortune_wheel_log'); 
		$fw_member_model = & m('fortune_wheel_sign_member'); 
		$fw_member_log_model = & m('fortune_wheel_sign_log'); 
		$fw_prize_model = & m('fortune_wheel_prize');

		// time
		$now = gmtime();
		$beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
		$endToday = mktime(0, 0, 0, date('m'), date('d')+1, date('Y'))-1;

		// init
		$fw_id = $_GET['fw_id'];
		if( !isset($fw_id) || empty($fw_id) ) {
			echo json_encode($this->err_state['ERROR_NO_FW_ID']);
			return false;
		}

		// get users
        $user_id = $_SESSION['user_info']['user_id'];
		if( empty($user_id) ) {
			echo json_encode($this->err_state['ERROR_NOT_LOGIN']);
			return false;
		}
		$fw_member = $fw_member_model->get( 'user_id = ' . $user_id . ' and fw_id =' . $fw_id );
		if( empty($fw_member) ) {
			echo json_encode($this->err_state['ERROR_NOT_SIGN_OR_VAILD']);
			return false;
		}
		else {
			if( $fw_member['remain_times'] == 0 ) {
				echo json_encode($this->err_state['ERROR_NO_RAFFLE_REMAINS']);
				return false;
			}
		}

		// fortune wheel status
		$fw = $fortune_wheel_model->get( 'id=' . $fw_id . ' and available = 1' );
		if( empty($fw) ) {
			echo json_encode($this->err_state['ERROR_NO_FORTUNE_WHEEL']);
			return false;
		}
		else {
			if( $fw['start_time'] > $now || $fw['end_time'] < $now ) {
				echo json_encode($this->err_state['ERROR_NOT_IN_RANGE']);
				return false;
			}
		}

		// all clear, do it.
		// generate user lucky point
		$MAX_RATE = 10;
		$addition_rate = $fw_member['addition_rate'] > $MAX_RATE ? $MAX_RATE : $fw_member['addition_rate'];
		$lucky_point = $this->random_float() / $addition_rate;

		$fw_prize = $fw_prize_model->get( array(
			'conditions' => "fw_id =". $fw_id . ' and product_remain > 0 and rate > '. $lucky_point,
		));
		// decrease remain times.
		$fw_member_model->edit( $fw_member['id'], 'remain_times = remain_times - 1' );

		// generate raffle result
		$raffle_result = $this->err_state['ERROR_SUCCESS']; 

		if( empty($fw_prize) ) {
			$raffle_status = 'lose';
			$prize_id = null;
		}
		else {
			$raffle_status = 'win';
			$prize_id = $fw_prize['id'];
			//中奖的话计算中奖的奖品是奖品列表中的第几个（order id asc），传出item给前台指向
			$prizes = $fw_prize_model->find(array(
				'conditions' => 'fw_id ='.$fw_id,
				'order' => 'id',
			));
			//做个循环，看中奖的奖品是id排序的第几个
			$i = 1;
			foreach($prizes as $val):
				if($fw_prize['id'] == $val['id']){
					break;
				}else{
					$i++;
				}
			endforeach;
			$item = 2*$i - 1; //item = (2n-1);
			// decreate prize quantity.
			$fw_prize_model->edit( $prize_id, 'product_remain = product_remain - 1' );

			$raffle_result['item'] = $item;
			$raffle_result['status'] = $raffle_status;
			$raffle_result['name'] = $fw_prize['name'];
			$raffle_result['product_name'] = $fw_prize['product_name'];
			$raffle_result['relate_url'] = $fw_prize['relate_url'];
			$raffle_result['level'] = $fw_prize['level'];
			$raffle_result['info'] = $fw_prize['info'];
		}

		$raffle_result['status'] = $raffle_status;
		$raffle_result['time'] = time();

		// write logs.
		$fw_log_rec = array(
			'print_rate' => $lucky_point,
			'user_id' => $user_id,
			'fw_id' => $fw_id,
			'prize_id' => $prize_id,
			'status' => $raffle_status,
		);
		if (!$fw_log_id = $fw_log_model->add($fw_log_rec) ) {
			echo json_encode($this->err_state['ERROR_WRITE_LOG']);
			return false;
		}

		echo json_encode($raffle_result);

		return true;
	}
}

?>
