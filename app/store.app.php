<?php

class StoreApp extends StorebaseApp {

    function index() {
        /* 店铺信息 */
        $_GET['act'] = 'index';
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
//        echo $_SESSION['user_info']['sgrade'];exit
        if ($_SESSION['user_info']['sgrade'] == 5) {
            $is_jingxiaoshang = true;
            $this->assign('is_jingxiaoshang', $is_jingxiaoshang);
        }
        $this->set_store($id);
        $store = $this->get_store_data();
        $this->assign('store', $store);

//        if ($store['pic_slides_wap']) {
//            $pic_slides_wap_arr = json_decode($store['pic_slides_wap'], true);
//            foreach ($pic_slides_wap_arr as $key => $slides) {
//                $pic_slides_wap[$key]['image_url'] = $slides['url'];
//                $pic_slides_wap[$key]['image_link'] = $slides['link'];
//            }
//            $this->assign('goods_images', $pic_slides_wap);
//        }
        $lunbo_img_model = & m('lunbo_img');
        $lunbo = $lunbo_img_model->find('store_id =' . $id . " AND status = 1");
        $this->assign('lunbo', $lunbo);
        //查是否有以量定价
        $nums_model = & m('groupbuy_nums');
        $nums = $nums_model->get('status = 1 AND store_id = '.$store['store_id'].' AND start_time <=' . gmtime() . " AND end_time >= " . gmtime());
//        print_r($nums);exit;
        $this->assign('nums', $nums);
        
        /* 获取oem资料 */
        $oem_model = & m('oem');
        $oem = $oem_model->get('store_id =' . $id);
        $this->assign('oem', $oem);
        
        /*
         * 获取店铺有无保护品牌，如果有，就有保护，无就无保护
         */
        $user_brand_model = & m('user_brand');
        $user_brand = $user_brand_model->get('store_id ='.$id);
        $brand_protect = false;
        if(!empty($user_brand['id'])){
            $brand_protect = true;
        }
        $this->assign('band_protect', $brand_protect);
        
        /* 取得友情链接 */
//        $this->assign('partners', $this->_get_partners($id));

        /* 取得推荐商品 */
        $this->assign('recommended_goods', $this->_get_recommended_goods($id));

        $this->assign('new_groupbuy', $this->_get_new_groupbuy($id));
        $this->assign('groupbuy_list', $this->_get_new_groupbuy($id));

        /* 取得最新商品 */
        $this->assign('new_goods', $this->_get_new_goods($id));
        /* 取得热卖商品 */
//        $this->assign('hot_sale_goods', $this->_get_hot_sale_goods($id));

        /* 当前位置 */
        $this->_curlocal(LANG::get('all_stores'), 'index.php?app=search&amp;act=store', $store['store_name']);

        $this->_config_seo('title', $store['store_name'] . ' - ' . Conf::get('site_title'));
        /* 配置seo信息 */
        $this->_config_seo($this->_get_seo_info($store));
        $this->assign('id', $id);

        $this->display('store.index.html');
    }

    function search() {
        /* 店铺信息 */
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        //显示经销商的数组
        $sgrade = array(1, 2, 3, 4, 5, 6, 7, 8, 9);
        if (in_array($_SESSION['user_info']['sgrade'], $sgrade)) {
            $is_jingxiaoshang = true;
            $this->assign('is_jingxiaoshang', $is_jingxiaoshang);
        }
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $this->set_store($id);
        $store = $this->get_store_data();
        $this->assign('store', $store);
        $c_type = $_GET['type'];
        /* 搜索到的商品 */
        $this->_assign_searched_goods($id);

        /* 当前位置 */
        $this->_curlocal(LANG::get('all_stores'), 'index.php?app=search&amp;act=store', $store['store_name'], 'index.php?app=store&amp;id=' . $store['store_id'], LANG::get('goods_list')
        );
        $cate_id = $_GET['cate_id'];
        $keyword = $_GET['keyword'];
        $order = $_GET['order'];
        $brand = $_GET['brand'];
        $this->assign('c_type',$c_type);
        $this->assign('order', $order);
        $this->assign('cate_id', $cate_id);
        $this->assign('keyword', $keyword);
        $this->assign('store_id', $id);
        $this->assign('brand',$brand);
        $this->_config_seo('title', Lang::get('goods_list') . ' - ' . $store['store_name']);
        $this->display('store.search.html');
    }

    //页面点击加载更多ajax
    function addmoreajax() {
        $store_id = $_GET['store_id'];
        $pages = $_GET['page'];
        $order = $_GET['order'];
        $sub_rule_id = $_GET['sub_rule_id'];
        if(empty($sub_rule_id)){
            $sub_rule_id = 0;
        }
//        $this->assign('sub_rule_id',$sub_rule_id);
        $rule_products_model = & m('order_rule_products');
        $rule_goods_ids = $rule_products_model->find('sub_rule_id ='.$sub_rule_id);
        $rule_id_array = array();
        foreach($rule_goods_ids as $val):
            $rule_id_array[] = $val['goods_id'];
        endforeach;
        $rule_goods_ids_str = implode(',',$rule_id_array);
        $goods_mod = & bm('goods', array('_store_id' => $store_id));
        $search_name = LANG::get('all_goods');

        $conditions = $this->_get_query_conditions(array(
            array(
                'field' => 'goods_name',
                'name' => 'keyword',
                'equal' => 'like',
            ),
        ));
        //如果搜索内容有，就增加商品介绍模糊搜索
        if (!empty($conditions)) {
            $conditions = substr($conditions, 5);
            $conditions = " AND (" . $conditions . " OR g.description like '%{$_GET['keyword']}%') ";
        }
//        echo $conditions;exit;
        if ($conditions) {
            $search_name = sprintf(LANG::get('goods_include'), $_GET['keyword']);
            $sgcate_id = 0;
        } else {
            $sgcate_id = empty($_GET['cate_id']) ? 0 : intval($_GET['cate_id']);
        }

        if ($sgcate_id > 0) {
            $gcategory_mod = & bm('gcategory', array('_store_id' => $id));
            $sgcate = $gcategory_mod->get_info($sgcate_id);
            $search_name = $sgcate['cate_name'];

            $sgcate_ids = $gcategory_mod->get_descendant_ids($sgcate_id);
        } else {
            $sgcate_ids = array();
        }

        /* 排序方式 */
        $orders = array(
            'add_time desc' => LANG::get('add_time_desc'),
            'price asc' => LANG::get('price_asc'),
            'price desc' => LANG::get('price_desc'),
            'views desc' => LANG::get('views_desc'),
            'sales desc' => LANG::get('sales_desc'),
            'recommended desc' => LANG::get('recommended_desc'),
        );


        if (!empty($_GET['order'])) {
            switch ($_GET['order']) {
                case 'sales desc':
                    $sort = 4;
                    break;
                case 'add_time desc':
                    $sort = 1;
                    break;
                case 'price desc':
                    $sort = 2;
                    break;
                case 'views desc':
                    $sort = 3;
                    break;
                case 'recommended desc':
                    $sort = 0;
                    $conditions = $conditions . ' AND g.recommended = 1 ';
                default:
                    $sort = 0;
                    break;
            }
        }
        if ($id == 7 || $id == 13) {
            //强制修改排序信息--针对蓝羽和维涅斯，先按照手动输入排序，再按照原来的方式排序
            if ($_GET['order'] == 'add_time desc') {
                $order = 'order_by';   //新品按照order_by排序
            } else if ($_GET['order'] == 'price desc') {
                $order = 'price_distributor desc';  //价格按照经销商价格低到高
            } else if ($_GET['order'] == 'sales desc') {
                $order = 'sales_order, sales desc'; //按照销量排序
            } else if ($_GET['order'] == 'views desc') {
                $order = 'views_order, views desc';  //人气排序
            } else {
                $order = empty($_GET['order']) || !isset($orders[$_GET['order']]) ? 'add_time desc' : $_GET['order'];
            }
        } else {
            $order = empty($_GET['order']) || !isset($orders[$_GET['order']]) ? 'add_time desc' : $_GET['order'];
        }
        
        $page = $this->_get_page(16);
        if(!empty($rule_goods_ids_str)) {
            $goods_list = $goods_mod->get_list(array(
                'conditions' => 'closed = 0 AND if_show = 1 AND cate_id != 742 ' . $conditions . " AND g.goods_id in (" . $rule_goods_ids_str . ")",
                'count' => true,
                'fields' => 'g.price_distributor,  ',
                'order' => $order,
                'limit' => $page['limit'],
            ), $sgcate_ids);
        }else{
            $goods_list = $goods_mod->get_list(array(
                'conditions' => 'closed = 0 AND if_show = 1 AND cate_id != 742 ' . $conditions ,
                'count' => true,
                'fields' => 'g.price_distributor,  ',
                'order' => $order,
                'limit' => $page['limit'],
            ), $sgcate_ids);
        }

        foreach ($goods_list as $key => $goods) {
            empty($goods['default_image']) && $goods_list[$key]['default_image'] = Conf::get('default_goods_image');
        }
        $brand = $_GET['brand'];
        if($brand == 'check'){
            $user_price_model = & m('user_price');
            $user_brand_model = & m('user_brand');
            $user_id = $_SESSION['user_info']['user_id'];
            //查所在卖场
            $my_price = $user_price_model->find('user_id ='.$user_id);
            $price_ids = array();
            foreach($my_price as $val):
                $price_ids[] = $val['price_id'];
            endforeach;
            $price_ids_str = implode(',', $price_ids);
            //查同一卖场下面所有客户
            $price_users = $user_price_model->find('price_id in ('.$price_ids_str.") AND store_id =".$store_id);
            $brand_users = array();
            foreach($price_users as $val):
                $brand_users[] = $val['user_id'];
            endforeach;
            $brand_users_str = implode(',', $brand_users);
            $brands = $user_brand_model->find('user_id in ('.$brand_users_str." ) AND store_id =".$store_id);
            $can_not_brands = '';
            foreach($brands as $val):
                $can_not_brands .= "'".$val['brand_name']."',";
            endforeach;
            $can_not_brands_str = substr($can_not_brands, 0 , -1);
            $can_not_goods = $goods_mod->find(array(
                'conditions'  => 'brand in ('.$can_not_brands_str.")",
                'fields'     => 'goods_name',
            ));
            foreach ($can_not_goods as $key => $val ):
                unset($goods_list[$key]);
            endforeach;
        }
        if (empty($goods_list)) {
            echo 000;
        } else {
            foreach ($goods_list as $good):
                echo "<li><div class='jpImgBox'>";
                echo "<a href='index.php?app=goods&id=".$good['goods_id']."' >";
                echo "<img src='".$good['default_image']."' />";
                echo "</a></div>";
                echo "<p class='jpName'>".$good['goods_name']."</p>";
                echo "<p class='jpPrice'>";
                    if($_SESSION['user_info']['sgrade'] == 5 || $_SESSION['user_info']['sgrade'] == 2 || $_SESSION['user_info']['sgrade'] == 3 || $_SESSION['user_info']['sgrade'] == 4 || $_SESSION['user_info']['sgrade'] == 6){
                        echo "￥".$good['price_distributor'];
                    }else{
                        echo "￥".$good['price'];
                    }
                    if($good['recommended']){
                        echo "<span style='color:red;'>[促销]</span>";
                    }
                echo "</p>";
                echo "</li>";
            endforeach;
        }
        exit;
    }

    function groupbuy() {
        /* 店铺信息 */
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $this->set_store($id);
        $store = $this->get_store_data();
        $this->assign('store', $store);

        /* 搜索团购 */
        empty($_GET['state']) && $_GET['state'] = 'on';
        $conditions = '1=1';
        if ($_GET['state'] == 'on') {
            $conditions .= ' AND gb.state =' . GROUP_ON . ' AND gb.end_time>' . gmtime();
            $search_name = array(
                array(
                    'text' => Lang::get('group_on')
                ),
                array(
                    'text' => Lang::get('all_groupbuy'),
                    'url' => url('app=store&act=groupbuy&state=all&id=' . $id)
                ),
            );
        } else if ($_GET['state'] == 'all') {
            $conditions .= ' AND gb.state ' . db_create_in(array(GROUP_ON, GROUP_END, GROUP_FINISHED));
            $search_name = array(
                array(
                    'text' => Lang::get('all_groupbuy')
                ),
                array(
                    'text' => Lang::get('group_on'),
                    'url' => url('app=store&act=groupbuy&state=on&id=' . $id)
                ),
            );
        }

        $page = $this->_get_page(16);
        $groupbuy_mod = &m('groupbuy');
        $groupbuy_list = $groupbuy_mod->find(array(
            'fields' => 'goods.default_image, gb.group_name, gb.group_id, gb.spec_price, gb.end_time, gb.state',
            'join' => 'belong_goods',
            'conditions' => $conditions . ' AND gb.store_id=' . $id,
            'order' => 'group_id DESC',
            'limit' => $page['limit'],
            'count' => true
        ));
        $page['item_count'] = $groupbuy_mod->getCount();
        $this->_format_page($page);
        $this->assign('page_info', $page);
        if (empty($groupbuy_list)) {
            $groupbuy_list = array();
        }
        foreach ($groupbuy_list as $key => $_g) {
            empty($groupbuy_list[$key]['default_image']) && $groupbuy_list[$key]['default_image'] = Conf::get('default_goods_image');
            $tmp = current(unserialize($_g['spec_price']));
            $groupbuy_list[$key]['price'] = $tmp['price'];

            if ($_g['end_time'] < gmtime()) {
                $groupbuy_list[$key]['group_state'] = group_state($_g['state']);
            } else {
                $groupbuy_list[$key]['lefttime'] = lefttime($_g['end_time']);
            }
        }
        /* 当前位置 */
        $this->_curlocal(LANG::get('all_stores'), 'index.php?app=search&amp;act=store', $store['store_name'], 'index.php?app=store&amp;id=' . $store['store_id'], LANG::get('groupbuy_list')
        );

        $this->assign('groupbuy_list', $groupbuy_list);
        $this->assign('search_name', $search_name);
        $this->_config_seo('title', $search_name[0]['text'] . ' - ' . $store['store_name']);
        $this->display('store.groupbuy.html');
    }

    function article_index() {
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }

        /* 店铺信息 */
        $this->set_store($id);
        $store = $this->get_store_data();
        $this->assign('store', $store);

        $article_mod = & m('article');
        $articles = $article_mod->find(
                array(
                    'conditions' => 'store_id=' . $id,
                )
        );
        $this->assign('articles', $articles);
        $this->display('store.article_index.html');
    }

    function article() {
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $article = $this->_get_article($id);
        if (!$article) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $this->assign('article', $article);

        /* 店铺信息 */
        $this->set_store($article['store_id']);
        $store = $this->get_store_data();
        $this->assign('store', $store);

        /* 当前位置 */
        $this->_curlocal(LANG::get('all_stores'), 'index.php?app=search&amp;act=store', $store['store_name'], 'index.php?app=store&amp;id=' . $store['store_id'], $article['title']
        );

        $this->_config_seo('title', $article['title'] . ' - ' . $store['store_name']);
        $this->display('store.article.html');
    }

    /* 关于我们 */

    function about() {

        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $this->set_store($id);
        $store = $this->get_store_data();
        $this->assign('store', $store);

        /* 当前位置 */
        $this->_curlocal(LANG::get('all_stores'), 'index.php?app=search&amp;act=store', $store['store_name']);

        $this->_config_seo('title', $store['store_name'] . ' - ' . Conf::get('site_title'));
        /* 配置seo信息 */
        $this->_config_seo($this->_get_seo_info($store));
        $this->display('store.about.html');
    }

    /* 信用评价 */

    function credit() {
        /* 店铺信息 */
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $this->set_store($id);
        $store = $this->get_store_data();
        $this->assign('store', $store);
        /* 取得评价过的商品 */
        if (!empty($_GET['eval']) && in_array($_GET['eval'], array(1, 2, 3))) {
            $conditions = "AND evaluation = '{$_GET['eval']}'";
        } else {
            $conditions = "";
            $_GET['eval'] = '';
        }
        $page = $this->_get_page(10);
        $order_goods_mod = & m('ordergoods');
        $goods_list = $order_goods_mod->find(array(
            'conditions' => "seller_id = '$id' AND evaluation_status = 1 AND is_valid = 1 " . $conditions,
            'join' => 'belongs_to_order',
            'fields' => 'buyer_id, buyer_name, anonymous, evaluation_time, goods_id, goods_name, specification, price, quantity, goods_image, evaluation, comment',
            'order' => 'evaluation_time desc',
            'limit' => $page['limit'],
            'count' => true,
        ));
        $this->assign('goods_list', $goods_list);

        $page['item_count'] = $order_goods_mod->getCount();
        $this->_format_page($page);
        $this->assign('page_info', $page);

        /* 按时间统计 */
        $stats = array();
        for ($i = 0; $i <= 3; $i++) {
            $stats[$i]['in_a_week'] = 0;
            $stats[$i]['in_a_month'] = 0;
            $stats[$i]['in_six_month'] = 0;
            $stats[$i]['six_month_before'] = 0;
            $stats[$i]['total'] = 0;
        }

        $goods_list = $order_goods_mod->find(array(
            'conditions' => "seller_id = '$id' AND evaluation_status = 1 AND is_valid = 1 ",
            'join' => 'belongs_to_order',
            'fields' => 'evaluation_time, evaluation',
        ));
        foreach ($goods_list as $goods) {
            $eval = $goods['evaluation'];
            $stats[$eval]['total'] ++;
            $stats[0]['total'] ++;

            $days = (gmtime() - $goods['evaluation_time']) / (24 * 3600);
            if ($days <= 7) {
                $stats[$eval]['in_a_week'] ++;
                $stats[0]['in_a_week'] ++;
            }
            if ($days <= 30) {
                $stats[$eval]['in_a_month'] ++;
                $stats[0]['in_a_month'] ++;
            }
            if ($days <= 180) {
                $stats[$eval]['in_six_month'] ++;
                $stats[0]['in_six_month'] ++;
            }
            if ($days > 180) {
                $stats[$eval]['six_month_before'] ++;
                $stats[0]['six_month_before'] ++;
            }
        }
        $this->assign('stats', $stats);

        /* 当前位置 */
        $this->_curlocal(LANG::get('all_stores'), 'index.php?app=search&amp;act=store', $store['store_name'], 'index.php?app=store&amp;id=' . $store['store_id'], LANG::get('credit_evaluation')
        );

        $this->_config_seo('title', Lang::get('credit_evaluation') . ' - ' . $store['store_name']);
        $this->display('store.credit.html');
    }

    /* 取得友情链接 */

    function _get_partners($id) {
        $partner_mod = & m('partner');
        return $partner_mod->find(array(
                    'conditions' => "store_id = '$id'",
                    'order' => 'sort_order',
        ));
    }

    /* 取得推荐商品 */

    function _get_recommended_goods($id, $num = 30) {
        //先获取登录账号的角色id
        $sgrade = $_SESSION['user_info']['sgrade'];
        //如果无登录，则默认为默认分组
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        $goods_mod = & bm('goods', array('_store_id' => $id));
        $goods_list = $goods_mod->find(array(
            'conditions' => "closed = 0 AND if_show = 1 AND recommended = 1 AND display_sgrade like '%,$sgrade,%'",
            'fields' => 'goods_name, default_image, price, price_distributor',
            'limit' => $num,
            'order' => 'order_by desc',
        ));
        foreach ($goods_list as $key => $goods) {
            empty($goods['default_image']) && $goods_list[$key]['default_image'] = Conf::get('default_goods_image');
            /*
             * 下面是将本角色对应的价格放到数组里面
             */
            //规格对应价格表
            $goodsspec_model = & m('goodsspec');
            //查找这个产品对应角色价钱，并保存到数组中(查找最低价格)
            $goodsspec = $goodsspec_model->get('goods_id =' . $goods['goods_id'] . ' AND spec_2 =' . $sgrade . ' ORDER BY price');
            $goods_list[$key]['spec_price'] = $goodsspec['price'];
        }

        //获取登录账号的权限.如果未登录，就按消费者来看
        $sgrade = $_SESSION['user_info']['sgrade'];
        if (empty($sgrade)) {
            $sgrade = 5;
        }
        //针对促销的商品，如果客户所在等级下面的所有规格数量都为0，就显示已售罄图片
        $spec_model = & m('goodsspec');
        foreach ($goods_list as $key => $goods) {
            $specs = $spec_model->find('goods_id =' . $goods['goods_id'] . " AND spec_2 =" . $sgrade);
            $num = 0;
            foreach ($specs as $val):
                $num += $val['stock'];
            endforeach;
            $goods_list[$key]['stock'] = $num;
        }
//        print_r($goods_list);exit;
        return $goods_list;
    }

    //团购
    function _get_new_groupbuy($id, $num = 12) {
        $model_groupbuy = & m('groupbuy');
        $groupbuy_list = $model_groupbuy->find(array(
            'fields' => 'goods.default_image, this.group_name, this.group_id, this.spec_price, this.end_time',
            'join' => 'belong_goods',
            'conditions' => $model_groupbuy->getRealFields('this.state=' . GROUP_ON . ' AND this.store_id=' . $id . ' AND end_time>' . gmtime()),
            'order' => 'group_id DESC',
            'limit' => $num
        ));
        if (empty($groupbuy_list)) {
            $groupbuy_list = array();
        }
        foreach ($groupbuy_list as $key => $_g) {
            empty($groupbuy_list[$key]['default_image']) && $groupbuy_list[$key]['default_image'] = Conf::get('default_goods_image');
            $tmp = current(unserialize($_g['spec_price']));
            $groupbuy_list[$key]['price'] = $tmp['price'];
            $groupbuy_list[$key]['group_price'] = $tmp['price'];
            $groupbuy_list[$key]['lefttime'] = lefttime($_g['end_time']);
        }

        return $groupbuy_list;
    }

    /* 取得最新商品 */

    function _get_new_goods($id, $num = 12) {
        //先获取登录账号的角色id
        $sgrade = $_SESSION['user_info']['sgrade'];
        //如果无登录，则默认为默认分组
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        $goods_mod = & bm('goods', array('_store_id' => $id));
        $goods_list = $goods_mod->find(array(
            'conditions' => "closed = 0 AND if_show = 1 AND display_sgrade like '%,$sgrade,%'",
            'fields' => 'goods_name, default_image, price, recommended, price_distributor',
            'order' => 'order_by asc , add_time desc',
            'limit' => $num,
        ));
        foreach ($goods_list as $key => $goods) {
            empty($goods['default_image']) && $goods_list[$key]['default_image'] = Conf::get('default_goods_image');
            //先获取登录账号的角色id
            $sgrade = $_SESSION['user_info']['sgrade'];
            //如果无登录，则默认为默认分组
            if (empty($sgrade)) {
                $sgrade = 1;
            }
            /*
             * 下面是将本角色对应的价格放到数组里面
             */
            //规格对应价格表
            $goodsspec_model = & m('goodsspec');
            //查找这个产品对应角色价钱，并保存到数组中(查找最低价格)
            $goodsspec = $goodsspec_model->get('goods_id =' . $goods['goods_id'] . ' AND spec_2 =' . $sgrade . ' ORDER BY price');
            $goods_list[$key]['spec_price'] = $goodsspec['price'];
        }
        return $goods_list;
    }

    /* 取得热卖商品 */

    function _get_hot_sale_goods($id, $num = 16) {
        $goods_mod = & bm('goods', array('_store_id' => $id));
        $goods_list = $goods_mod->find(array(
            'conditions' => "closed = 0 AND if_show = 1",
            'join' => 'has_goodsstatistics',
            'fields' => 'goods_name, default_image, price,sales',
            'order' => 'sales desc,add_time desc',
            'limit' => $num,
        ));
        foreach ($goods_list as $key => $goods) {
            empty($goods['default_image']) && $goods_list[$key]['default_image'] = Conf::get('default_goods_image');
        }
        return $goods_list;
    }

    /* 搜索到的结果 */

    function _assign_searched_goods($id) {
        $goods_mod = & bm('goods', array('_store_id' => $id));
        $search_name = LANG::get('all_goods');
        $sub_rule_id = $_GET['sub_rule_id'];
        if(empty($sub_rule_id)){
            $sub_rule_id = 0;
        }
        $this->assign('sub_rule_id',$sub_rule_id);
        $rule_products_model = & m('order_rule_products');
        $rule_goods_ids = $rule_products_model->find('sub_rule_id ='.$sub_rule_id);
        $rule_id_array = array();
        foreach($rule_goods_ids as $val):
            $rule_id_array[] = $val['goods_id'];
        endforeach;
        $rule_goods_ids_str = implode(',',$rule_id_array);
        $conditions = $this->_get_query_conditions(array(
            array(
                'field' => 'goods_name',
                'name' => 'keyword',
                'equal' => 'like',
            ),
        ));
        //如果搜索内容有，就增加商品介绍模糊搜索
        if (!empty($conditions)) {
            $conditions = substr($conditions, 5);
            $conditions = " AND (" . $conditions . " OR g.description like '%{$_GET['keyword']}%') ";
        }
//        echo $conditions;exit;
        if ($conditions) {
            $search_name = sprintf(LANG::get('goods_include'), $_GET['keyword']);
            $sgcate_id = 0;
        } else {
            $sgcate_id = empty($_GET['cate_id']) ? 0 : intval($_GET['cate_id']);
        }

        if ($sgcate_id > 0) {
            $gcategory_mod = & bm('gcategory', array('_store_id' => $id));
            $sgcate = $gcategory_mod->get_info($sgcate_id);
            $search_name = $sgcate['cate_name'];

            $sgcate_ids = $gcategory_mod->get_descendant_ids($sgcate_id);
        } else {
            $sgcate_ids = array();
        }

        /* 排序方式 */
        $orders = array(
            'add_time desc' => LANG::get('add_time_desc'),
            'price asc' => LANG::get('price_asc'),
            'price desc' => LANG::get('price_desc'),
            'views desc' => LANG::get('views_desc'),
            'sales desc' => LANG::get('sales_desc'),
            'recommended desc' => LANG::get('recommended_desc'),
        );

        $this->assign('orders', $orders);

        if (!empty($_GET['order'])) {
            switch ($_GET['order']) {
                case 'sales desc':
                    $sort = 4;
                    break;
                case 'add_time desc':
                    $sort = 1;
                    break;
                case 'price desc':
                    $sort = 2;
                    break;
                case 'views desc':
                    $sort = 3;
                    break;
                case 'recommended desc':
                    $sort = 0;
//                    $conditions = $conditions . ' AND g.recommended = 1 ';
                default:
                    $sort = 0;
                    break;
            }
        }
        if($_GET['recommended'] == 1){
            $conditions = $conditions . ' AND g.recommended = 1 ';
        }
        //先获取登录账号的角色id
        $sgrade = $_SESSION['user_info']['sgrade'];
        //如果无登录，则默认为默认分组
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        $conditions = $conditions . " AND g.display_sgrade like '%,$sgrade,%'";

        $this->assign('sort', $sort);
        $this->assign('cate_id', empty($_GET['cate_id']) ? 0 : intval($_GET['cate_id']));
        if ($id == 7 || $id == 13) {
            //强制修改排序信息--针对蓝羽和维涅斯，先按照手动输入排序，再按照原来的方式排序
            if ($_GET['order'] == 'add_time desc') {
                $order = 'order_by';   //新品按照order_by排序
            } else if ($_GET['order'] == 'price desc') {
                $order = 'price_distributor';  //价格按照经销商价格低到高
            } else if ($_GET['order'] == 'sales desc') {
                $order = 'sales_order, sales desc'; //按照销量排序
            } else if ($_GET['order'] == 'views desc') {
                $order = 'views_order, views desc';  //人气排序
            } else {
                $order = empty($_GET['order']) || !isset($orders[$_GET['order']]) ? 'add_time desc' : $_GET['order'];
            }
        } else {
            $order = empty($_GET['order']) || !isset($orders[$_GET['order']]) ? 'add_time desc' : $_GET['order'];
        }
        $page = $this->_get_page(16);
        if(!empty($rule_goods_ids_str)) {
            $goods_list = $goods_mod->get_list(array(
                'conditions' => 'closed = 0 AND if_show = 1 AND cate_id != 742 ' . $conditions . " AND g.goods_id in (" . $rule_goods_ids_str . ")",
                'count' => true,
                'fields' => 'g.price_distributor, ',
                'order' => $order,
                'limit' => $page['limit'],
            ), $sgcate_ids);
        }else{
            $goods_list = $goods_mod->get_list(array(
                'conditions' => 'closed = 0 AND if_show = 1 AND cate_id != 742 ' . $conditions ,
                'count' => true,
                'fields' => 'g.price_distributor, ',
                'order' => $order,
                'limit' => $page['limit'],
            ), $sgcate_ids);
        }
        foreach ($goods_list as $key => $goods) {
            empty($goods['default_image']) && $goods_list[$key]['default_image'] = Conf::get('default_goods_image');
        }
        $brand = $_GET['brand'];
        if($brand == 'check'){
            $user_price_model = & m('user_price');
            $user_brand_model = & m('user_brand');
            $user_id = $_SESSION['user_info']['user_id'];
            //查所在卖场
            $my_price = $user_price_model->find('user_id ='.$user_id);
            $price_ids = array();
            foreach($my_price as $val):
                $price_ids[] = $val['price_id'];
            endforeach;
            $price_ids_str = implode(',', $price_ids);
            //查同一卖场下面所有客户
            $price_users = $user_price_model->find('price_id in ('.$price_ids_str.") AND store_id =".$id);
            $brand_users = array();
            foreach($price_users as $val):
                $brand_users[] = $val['user_id'];
            endforeach;
            $brand_users_str = implode(',', $brand_users);
            $brands = $user_brand_model->find('user_id in ('.$brand_users_str." ) AND store_id =".$id);
            $can_not_brands = '';
            foreach($brands as $val):
                $can_not_brands .= "'".$val['brand_name']."',";
            endforeach;
            $can_not_brands_str = substr($can_not_brands, 0 , -1);
            $can_not_goods = $goods_mod->find(array(
                'conditions'  => 'brand in ('.$can_not_brands_str.")",
                'fields'     => 'goods_name',
            ));
            foreach ($can_not_goods as $key => $val ):
                unset($goods_list[$key]);
            endforeach;
        }
        $this->assign('searched_goods', $goods_list);
        $page['item_count'] = $goods_mod->getCount();
        $this->_format_page($page);
        $this->assign('page_info', $page);

        $this->assign('search_name', $search_name);
    }

    /**
     * 取得文章信息
     */
    function _get_article($id) {
        $article_mod = & m('article');
        return $article_mod->get_info($id);
    }

    function _get_seo_info($data) {
        $seo_info = $keywords = array();
        $seo_info['title'] = $data['store_name'] . ' - ' . Conf::get('site_title');
        $keywords = array(
            str_replace("\t", ' ', $data['region_name']),
            $data['store_name'],
        );
        //$seo_info['keywords'] = implode(',', array_merge($keywords, $data['tags']));
        $seo_info['keywords'] = implode(',', $keywords);
        $seo_info['description'] = sub_str(strip_tags($data['description']), 10, true);
        return $seo_info;
    }

    //OEM
    function oem() {
        $store_id = $_GET['store_id'];
        $oem_model = & m('oem');
        $oem = $oem_model->get('store_id =' . $store_id);
        if (!IS_POST) {

            $this->assign('oem', $oem);
            $this->display('oem.html');
        } else {
            $oem_customer_model = & m('oem_customer');
            $new_oem_customer = array(
                'phone' => $_POST['phone'],
                'telephone' => $_POST['telephone'],
                'weixin' => $_POST['weixin'],
                'customer' => $_POST['customer'],
                'ent' => $_POST['ent'],
                'qq' => $_POST['qq'],
                'email' => $_POST['email'],
            );
            $oem_customer_model->add($new_oem_customer);
            $tomail = $oem['email'];
            //如果有填写邮箱，就发邮箱通知oem联系客户
            if (!empty($tomail)) {
                $content = "<html>
                         <p>蓝羽商城有新的客户想咨询产品定制，请尽快联系客户：</p>
                         <p>客户名称：" . $_POST['customer'] . "</p>
                         <p>客户企业：" . $_POST['ent'] . "</p>
                         <p>客户电话：" . $_POST['phone'] . "</p>
                         <p>客户邮箱：" . $_POST['email'] . "</p>
                         </html>";
                $this->_mailto2($tomail, '请尽快联系客户', $content);
            }
            $this->show_message('感谢您的咨询,我们相关人员会即时联系您', '我们相关人员会即时联系您', 'index.php');
        }
    }

    function store_detail(){
        $id = $_GET['id'];
        $store_model = & m('store');
        $store = $store_model->get('store_id ='.$id);
        $this->assign('store',$store);
        $this->display('store_detail.html');
    }

    function contact_oem(){
        $store_id = $_GET['store_id'];
        $oem_model = & m('oem');
        $oem = $oem_model->get('store_id ='.$store_id);
        $this->assign('oem',$oem);
        $this->display('contact_oem.html');
    }

    function address() {
        $address_model = & m('address');
        $member_model = & m('member');
        $address = $address_model->find('user_id > 4000 AND user_id < 5000');
        foreach ($address as $val):
            $member = $member_model->get('user_id =' . $val['user_id']);
            $member['province'] = $val['region_name'];
            $member['region_id'] = $val['region_id'];
            $member_model->edit($member['user_id'], $member);
        endforeach;
    }

    //店铺的以量定价列表
    function groupbuy_nums() {
        $nums_model = & m('groupbuy_nums');
        $goods_model = & m('goods');
        $nums_price_model = & m('groupbuy_nums_price');
//        $store_id = $_GET['store_id'];
        $now = gmtime();
        //查店铺下所有状态为已发布而且时间还未结束的以量定价团购
        $nums = $nums_model->find(array(
            'conditions' => "status = 1 AND end_time >= " . $now,
        ));
        foreach ($nums as $key => $num):
            //保存商品详情
            $goods = $goods_model->get(array(
                'conditions' => 'goods_id =' . $num['goods_id'],
                'fields' => 'goods_name, default_image',
            ));
            $nums[$key]['goods'] = $goods;
            //保存价格规则
            $price = $nums_price_model->find('group_id =' . $num['id']);
            $nums[$key]['price_rule'] = $price;
        endforeach;
        $this->assign('nums', $nums);
        $this->display('groupbuy_list.html');
    }

    //以量定价下单页面
    function order_groupbuy() {
        //必须先登录
        $user_id = $_SESSION['user_info']['user_id'];
        if (empty($user_id)) {
            $this->show_warning('请先登录', '点击登录', 'index.php?app=member&act=login');
            return;
        }

        $id = $_GET['id'];
        $nums_model = & m('groupbuy_nums');
        $goods_model = & m('goods');
        $nums_price_model = & m('groupbuy_nums_price');
        $nums = $nums_model->get('id = ' . $id);
        $timeout = FALSE;
        if ($nums['end_time'] < gmtime()) {
            $timeout = true;
        }
        $this->assign('timeout', $timeout);
        //判断活动针对客户群，如果不是当前权限，就不能进入
        if ($nums['sgrade'] != $_SESSION['user_info']['sgrade']) {
            $this->show_warning('对不起，该活动只针对对应级别会员开放', '请返回', 'index.php');
            return;
        }
        //查有几多人参加了活动
        $groupbuy_nbums_buy = & m('groupbuy_nums_buy');
        $buyers = $groupbuy_nbums_buy->find('group_id = '.$id);
        $buyer_nums = count($buyers);
        $this->assign('buyer_nums', $buyer_nums);
        
        $goods = $goods_model->get('goods_id =' . $nums['goods_id']);
        $price_rule = $nums_price_model->find('group_id =' . $id);
        //省份
        $region_model = & m('region');
        $regions = $region_model->findAll();
        $is_max = false; //是否已经达到最大值
        //计算当前价格:
        $now_price = $nums_price_model->get('group_id =' . $id . " AND min_num <= " . $nums['total_nums'] . " AND max_num >" . $nums['total_nums']);
        
        
        if (empty($now_price)) {
            //查最小的数量对应价格
            $min_price = $nums_price_model->get(array(
                'conditions' => 'group_id =' . $id,
                'order' => 'min_num',
            ));
            //如果现在的数量连最低都不满足，价格就是原价
            if ($nums['total_nums'] < $min_price['min_num']) {
                $now_price['unit_price'] = $nums['price'];
            }
            //最大的数量对应价格
            $max_price = $nums_price_model->get(array(
                'conditions' => 'group_id =' . $id,
                'order' => 'min_num desc',
            ));

            //如果现在的数量比最大还打，现在价格就是最低的价格
            if ($nums['total_nums'] >= $max_price['max_num']) {
                $now_price['unit_price'] = $max_price['unit_price'];
                $now_price['total_price'] = $max_price['total_price'];
                $is_max = true;
            }
        }
        //如果还未达到最大值，就计算下一阶段需要的数量已经价格
        if (!$is_max) {
            //查下一阶段的价格
            if (empty($now_price['max_num'])) {
                $next_price = $min_price;
            } else {
                $next_price = $nums_price_model->get('group_id =' . $id . " AND min_num = " . $now_price['max_num']);
            }
            //计算还差几多进入下一阶段
            $next_nums = $next_price['min_num'] - $nums['total_nums'];
        }
        //查看当前账号已购买数量

        $nums_buy_model = & m('groupbuy_nums_buy');
        $has_buy = $nums_buy_model->get('group_id =' . $id . " AND user_id =" . $user_id);
        if (empty($has_buy)) {
            $has_buy['nums'] = 0;
        }
        
        //预计花费
        $e_price = $now_price['total_price']*$has_buy['nums'];
        
        //计算距离活动时间结束还有多少秒
        $countdown = $nums['end_time'] - gmtime();
        $this->assign('countdown', $countdown);
        $this->assign('e_price', $e_price);
        $this->assign('has_buy', $has_buy);
        $this->assign('price_rule', $price_rule);
        $this->assign('nums', $nums);
        $this->assign('goods', $goods);
        $this->assign('now_price', $now_price);
        $this->assign('next_nums', $next_nums);
        $this->assign('next_price', $next_price);
        $this->assign('regions', $regions);
        $this->display('nums_order.html');
    }

    //购买
    function order_nums() {

        $grade = $_SESSION['user_info']['sgrade'];
        if (empty($grade)) {
            echo 333; //未登陆
            exit;
        }
        if ($grade == 1) {
            echo 444; //无权限
            exit;
        }

        //收集页面信息
        $group_id = $_POST['group_id'];
        $nums = $_POST['nums'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $region_id = $_POST['region'];
        $name = $_POST['name'];
        if (empty($nums) || empty($phone) || empty($address) || empty($region_id)) {
            echo 222;
            exit;
        }
        if ($nums < 1) {
            echo 222;
            exit;
        }
        $region_model = & m('region');
        $region = $region_model->get('region_id =' . $region_id);
        $nums_model = & m('groupbuy_nums');
        $groupbuy = $nums_model->get('id =' . $group_id);
        if ($groupbuy['start_time'] > gmtime()) {
            echo 666;
            exit;
        }
        if ($groupbuy['status'] != 1 || $groupbuy['end_time'] < gmtime()) {
            echo 555; //活动不在正常状态
            exit;
        }
        //保存购买信息
        $new_buy = array(
            'group_id' => $group_id,
            'nums' => $nums,
            'user_id' => $_SESSION['user_info']['user_id'],
            'add_time' => time(),
            'status' => 1,
            'region_id' => $region_id,
            'region_name' => $region['region_name'],
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
        );
        $buy_model = & m('groupbuy_nums_buy');
        $buy_model->add($new_buy);
        $groupbuy['total_nums'] += $nums;
        $nums_model->edit($group_id, $groupbuy);
        echo 111;
        exit;
    }

    //动态修改购买数量
    function edit_groupbuy() {
        $group_id = $_POST['group_id'];
        $user_id = $_SESSION['user_info']['user_id'];
        $nums = $_POST['nums'];
        if (empty($nums) || empty($user_id) || empty($group_id)) {
            echo 222;
            exit;
        }
        $nums_model = & m('groupbuy_nums');
        $nums_buy_model = & m('groupbuy_nums_buy');
        $groupbuy_nums = $nums_model->get('id =' . $group_id);
        if ($groupbuy_nums['status'] != 1 || $groupbuy_nums['end_time'] < gmtime()) {
            echo 333;
            exit;
        }

        $nums_buy = $nums_buy_model->get('user_id =' . $user_id . " AND group_id =" . $group_id);

        $before = $nums_buy['nums'];
        $nums_buy['nums'] = $nums;
        $change_nums = $nums - $before;
        $groupbuy_nums['total_nums'] += $change_nums;
        //改变自己的购买量
        $nums_buy_model->edit($nums_buy['id'], $nums_buy);
        //改变活动总量
        $nums_model->edit($groupbuy_nums['id'], $groupbuy_nums);
        echo 111;
        exit;
    }

}

?>
