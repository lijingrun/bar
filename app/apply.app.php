<?php

/* 申请开店 */
class ApplyApp extends MallbaseApp
{

    function index()
    {
        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        /* 判断是否开启了店铺申请 */
        if (!Conf::get('store_allow'))
        {
            $this->show_warning('apply_disabled');
            return;
        }

        /* 只有登录的用户才可申请 */
        if (!$this->visitor->has_login)
        {
            $this->login();
            return;
        }

        /* 已申请过或已有店铺不能再申请 */
        $store_mod =& m('store');
        $store = $store_mod->get($this->visitor->get('user_id'));
        if ($store)
        {
            if ($store['state'])
            {
                $this->show_warning('user_has_store');
                return;
            }
            else
            {
                if ($step != 2)
                {
                    $this->show_warning('user_has_application');
                    return;
                }                
            }
        }
        $sgrade_mod =& m('sgrade');
        
        switch ($step)
        {
            case 1:
                $sgrades = $sgrade_mod->find(array(
                    'order' => 'sort_order',
                ));
                foreach ($sgrades as $key => $sgrade)
                {
                    if (!$sgrade['goods_limit'])
                    {
                        $sgrades[$key]['goods_limit'] = LANG::get('no_limit');
                    }
                    if (!$sgrade['space_limit'])
                    {
                        $sgrades[$key]['space_limit'] = LANG::get('no_limit');
                    }
                    $arr = explode(',', $sgrade['functions']);
                    $subdomain = array();
                    foreach ( $arr as $val)
                    {
                        if (!empty($val))
                        {
                            $subdomain[$val] = 1;
                        }
                    }
                    $sgrades[$key]['functions'] = $subdomain;
                    unset($arr);
                    unset($subdomain);
                }
                $this->assign('domain', ENABLED_SUBDOMAIN);
                $this->assign('sgrades', $sgrades);

                $this->_config_seo('title', Lang::get('title_step1') . ' - ' . Conf::get('site_title'));
                $this->display('apply.step1.html');
                break;
            case 2:
                $sgrade_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                $sgrade = $sgrade_mod->get($sgrade_id);

				// if salsman mode, auto confirm.
				$salesman_mod = & m('salesman');
				if( $_GET['serial'] ) {
					$salesman = $salesman_mod->get( 
						"`serial` = '". $_GET['serial'] ."'"
					);
					if( $salesman ) {
						$sgrade['need_confirm'] = 0;
					}
				}

                if (empty($sgrade))
                {
                    $this->show_message('request_error',
                        'back_step1', 'index.php?app=apply');
                         exit;
                }

                if (!IS_POST)
                {
                    $region_mod =& m('region');
                    $this->assign('site_url', site_url());
                    $this->assign('regions', $region_mod->get_options(0));
                    $this->assign('scategories', $this->_get_scategory_options());

                    /* 导入jQuery的表单验证插件 */
                    $this->import_resource(array('script' => 'mlselection.js,jquery.plugins/jquery.validate.js'));
                    $this->assign('serial',$_GET['serial']);
                    $this->_config_seo('title', Lang::get('title_step2') . ' - ' . Conf::get('site_title'));
                    $this->assign('store', $store);
                    $scategory = $store_mod->getRelatedData('has_scategory', $this->visitor->get('user_id'));
                    if ($scategory)
                    {
                        $scategory = current($scategory);
                    }
                    $this->assign('scategory', $scategory);
                    $this->display('apply.step2.html');
                }
                else
                {
                    $city_id = $_POST['city'];
                    $city_model = & m('city');
                    $city = $city_model->get('id ='.$city_id);
                    if(empty($city['id'])){
                        $this->show_warning('请选择店铺所在城市！');
                        return;
                    }

                    $store_mod  =& m('store');
                    $store_id = $this->visitor->get('user_id');
                    $data = array(
                        'store_id'     => $store_id,
                        'store_name'   => $_POST['store_name'],
                        'owner_name'   => $_POST['owner_name'],
                        'owner_card'   => $_POST['owner_card'],
                        'region_id'    => $_POST['region_id'],
                        'region_name'  => $_POST['region_name'],
                        'address'      => $_POST['address'],
                        'zipcode'      => $_POST['zipcode'],
                        'tel'          => $_POST['tel'],
                        'sgrade'       => $sgrade['grade_id'],
                        'city'         => $city['name'],
                        'city_id'      => $city['id'],
                       //'apply_remark' => $_POST['apply_remark'],
                        'state'        => $sgrade['need_confirm'] ? 0 : 1,
                        'add_time'     => gmtime(),
                    );

                    $image = $this->_upload_image($store_id);
                    if ($this->has_error())
                    {
                        $this->show_warning($this->get_error());

                        return;
                    }
                    
                    /* 判断是否已经申请过 */
                    $state = $this->visitor->get('state');
                    if ($state != '' && $state == STORE_APPLYING)
                    {
                        $store_mod->edit($store_id, array_merge($data, $image));
                    }
                    else
                    {
                        $store_mod->add(array_merge($data, $image));
                    }
                    
                    if ($store_mod->has_error())
                    {
                        $this->show_warning($store_mod->get_error());
                        return;
                    }
                    

                    $cate_id = intval($_POST['cate_id']);
                    $store_mod->unlinkRelation('has_scategory', $store_id);
                    if ($cate_id > 0)
                    {                        
                        $store_mod->createRelation('has_scategory', $store_id, $cate_id);
                    }
                    if ($sgrade['need_confirm'])
                    {
                        $this->show_message('apply_ok',
                            'index', 'index.php');
                    }
                    else
                    {
						// add log to salesman-client
						if( $salesman ) { 
							$salesman_clients_mod = & m('salesman_clients');
							$salesman_clients_mod->add(
								array(
									'salesman_id' => $salesman['id'],
									'user_id' => $this->visitor->get('user_id'),	
								)
							);
                            //营销员注册的，自动提交卖场绑定请求
                            $member_model = & m('member');
                            $member = $member_model->get('user_id ='.$store_id);
                            $protect_apply_model = & m('protect_apply');
                            $new_p_apply = array(
                                'user_id' => $store_id,
                                'province' => $_POST['region_name'],
                                'city' => $city['name'],
                                'status' => 1,
                                'store_id' => 7,
                                'add_time' => gmtime(),
                                'user_name' => $member['user_name'],
                                'address' => $_POST['address'],
                            );
                            $protect_apply_model->add($new_p_apply);
							$ms =& ms();
							$to_ids = 7;
							$msg_content = Lang::get('registerd_by_salesman');
							$msg_content = 
								sprintf( $msg_content, trim($_POST['store_name']), $salesman['name'] );
							$msg_id = $ms->pm->send(
								$this->visitor->get('user_id'), $to_ids, '', $msg_content);
						}

						// automatic generate customer k3 code when it not have one.
						$member_model = & m('member');
						$curr_member = $member_model->get( 'user_id ='. $this->visitor->get('user_id') );
						if( empty($curr_member['k3_code']) ) {

							$province_id = $this->get_province_id_from_regin_id($_POST['region_id']);
							$k3_prefix = $this->generate_k3_code_prefix( $province_id, $_POST['city'] );
							if( 0 != $k3_prefix ) {
								$k3_member = $member_model->getall(
									"SELECT".
									"	`user_name` ".
									"	, REPLACE(`k3_code`, '".$k3_prefix."', '')  as code ".
									"FROM `ecm_member` ".
									"WHERE".
									"	`k3_code` LIKE CONCAT( '".$k3_prefix."', '%' )"
								);
                                $k3_code = array();
                                foreach($k3_member as $val):
                                    $k3_code[] = intval($val['code']);
                                endforeach;
                                if(!empty($k3_code)) {
                                    $k3_curr_max_code = max($k3_code);
                                }else{
                                    $k3_curr_max_code = 1;
                                }
                                $k3_curr_max_code++;
								$K3_MIN_CODE = 500;
								$k3_curr_max_code = $k3_curr_max_code < $K3_MIN_CODE ? $K3_MIN_CODE : $k3_curr_max_code;
								$gened_k3_code = $k3_prefix.$k3_curr_max_code;

								$curr_member['k3_code'] = $gened_k3_code;
								if( isset($curr_member['user_id']) && !empty($curr_member['user_id']) ) {
									$member_model->edit( $curr_member['user_id'], $curr_member ); 
								}
							}
							else {
								// TO-DO: send message.
							}
						}

						// send feed.
                        $this->send_feed('store_created', array(
                            'user_id'   => $this->visitor->get('user_id'),
                            'user_name'   => $this->visitor->get('user_name'),
                            'store_url'   => SITE_URL . '/' . url('app=store&id=' . $store_id),
                            'seller_name'   => $data['store_name'],
                        ));
                        $this->_hook('after_opening', array('user_id' => $store_id));
                        //快速通道注册完之后重新登录一次,刷新权限
                        $this->_do_login($store_id);
                        $this->show_message('store_opened',
                            'index', 'index.php');
                    }
                }
                break;
            default:
                header("Location:index.php?app=apply&step=1");
                break;
        }
    }

    //根据省份查找城市，由于有2个省份表，需要兜个圈查
    function find_city(){
        $region_id = $_POST['region'];
        $region_model = & m('region');
        $province_model = & m('province');
        $city_model = & m('city');
        $region = $region_model->get('region_id ='.$region_id);
		$province = $province_model->get("name like '".mb_substr($region['region_name'],0,2,'utf-8')."%'");
        $citys = $city_model->find('topid = '.$province['id']);
        echo "<option value='0'>请选择城市</option>";
        foreach($citys as $city):
            echo "<option value='".$city['id']."'>".$city['name']."</option>";
        endforeach;
        exit;
    }

	function get_province_id_from_regin_id( $region_id ) {
        $region_model = & m('region');
        $province_model = & m('province');
        $region = $region_model->get('region_id ='.$region_id);
		$province = $province_model->get("name like '".mb_substr($region['region_name'],0,2,'utf-8')."%'");
        return $province['id'];
	}

	function generate_k3_code_prefix( $province_id, $city_id ) {
        $province_model = & m('province');
        $city_model = & m('city');
		$k3_province_id = $province_model->get( 'id = '.$province_id );
		$k3_province_id = $k3_province_id['k3_code']; 
		$k3_city_id = $city_model->get('id = '.$city_id );
		$k3_city_id = $k3_city_id['k3_code'];

		if( empty($k3_province_id) || empty($k3_city_id) )
			return 0;
		else
			return '1.'.$k3_province_id.'.'.$k3_city_id.'.';
	}	

    function check_name()
    {
        $store_name = empty($_GET['store_name']) ? '' : trim($_GET['store_name']);
        $store_id = empty($_GET['store_id']) ? 0 : intval($_GET['store_id']);

        $store_mod =& m('store');
        if (!$store_mod->unique($store_name, $store_id))
        {
            echo ecm_json_encode(false);
            return;
        }
        echo ecm_json_encode(true);
    }

    /* 上传图片 */
    function _upload_image($store_id)
    {
        import('uploader.lib');
        $uploader = new Uploader();
        $uploader->allowed_type(IMAGE_FILE_TYPE);
        $uploader->allowed_size(SIZE_STORE_CERT); // 400KB

        $data = array();
        for ($i = 1; $i <= 3; $i++)
        {
            $file = $_FILES['image_' . $i];
            if ($file['error'] == UPLOAD_ERR_OK)
            {
                if (empty($file))
                {
                    continue;
                }
                $uploader->addFile($file);
                if (!$uploader->file_info())
                {
                    $this->_error($uploader->get_error());
                    return false;
                }

                $uploader->root_dir(ROOT_PATH);
                $dirname   = 'data/files/mall/application';
                $filename  = 'store_' . $store_id . '_' . $i;
                $data['image_' . $i] = $uploader->save($dirname, $filename);
            }
        }
        return $data;
    }

    /* 取得店铺分类 */
    function _get_scategory_options()
    {
        $mod =& m('scategory');
        $scategories = $mod->get_list();
        import('tree.lib');
        $tree = new Tree();
        $tree->setTree($scategories, 'cate_id', 'parent_id', 'cate_name');
        return $tree->getOptions();
    }
}

?>
