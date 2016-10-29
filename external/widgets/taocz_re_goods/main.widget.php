<?php

/**
 * 轮播图片挂件
 *
 * @return  array   $image_list
 */
class Taocz_re_goodsWidget extends BaseWidget
{
    var $_name = 'taocz_re_goods';
	var $_ttl  = 1440;
	
    function _get_data()
    {
	$cache_server =& cache_server();
        $key = $this->_get_cache_id();
        $data = $cache_server->get($key);
        //先获取登录账号的角色id
        $sgrade = $_SESSION['user_info']['sgrade'];
        //如果无登录，则默认为默认分组
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        
        //各种会员都会显示经销商价
        if ($_SESSION['user_info']['sgrade'] == 5 || $_SESSION['user_info']['sgrade'] == 2 || $_SESSION['user_info']['sgrade'] == 3 || $_SESSION['user_info']['sgrade'] == 4) {
            $is_jingxiaoshang = true;
            $this->assign('is_jingxiaoshang', $is_jingxiaoshang);
        }
        if($data === false)
        {
			$num=$this->options['num']?$this->options['num']:12;
			$recom_mod =& m('recommend');
			$goods_list= $recom_mod->get_recommended_goods($this->options['img_recom_id'],$num, true, $this->options['img_cate_id']);
                        if($goods_list)
			{
				foreach($goods_list as $key=>$val){
                                        $sgrades_arr = $val['display_sgrade'];
//                                        echo $sgrades_arr;
                                        $sgrades_arr = explode(',', $sgrades_arr);
//                                        print_r($sgrades_arr);
                                        if(!in_array($sgrade, $sgrades_arr)){
                                            unset($goods_list[$key]);
                                            continue;
                                        }
					if($val['stock'])
					{
						$goods_list[$key]['stock_rate']=((1-$val['sales']/$val['stock'])*100).'%';
					}else{
						$goods_list[$key]['stock_rate']=0;
					}
				}
			}
                        //获取登录账号的权限.如果未登录，就按消费者来看
                        $sgrade = $_SESSION['user_info']['sgrade'];
                        if(empty($sgrade)){
                            $sgrade = 5;
                        }
                        //循环商品，如果客户所在等级下面的所有规格数量都为0，就显示已售罄图片
                        $spec_model = & m('goodsspec');
                        foreach($goods_list as $key=>$goods){
                            $specs = $spec_model->find('goods_id ='.$goods['goods_id']." AND spec_2 =".$sgrade);
//                            print_r($specs);
                            $num = 0;
                            foreach($specs as $val):
                                $num += $val['stock'];
                            endforeach;
                            $goods_list[$key]['stock'] = $num;
                        }
//                        print_r($goods_list);exit;
			$data = array(
				'model_id'			=> mt_rand(),
				'model_name'	 	=> $this->options['model_name'],
				'goods_list'	 	=> $goods_list,
			);
        	$cache_server->set($key, $data,$this->_ttl);
        }
        return $data;
    }

    function parse_config($input)
    {
        return $input;
    }
	function get_config_datasrc()
    {
         // 取得推荐类型
        $this->assign('recommends', $this->_get_recommends());
        // 取得一级商品分类
        $this->assign('gcategories', $this->_get_gcategory_options(2));
    }
}

?>
