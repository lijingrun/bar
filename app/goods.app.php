<?php

/* 商品 */

class GoodsApp extends StorebaseApp
{

    var $_goods_mod;

    function __construct()
    {
        $this->GoodsApp();
    }

    function GoodsApp()
    {
        parent::__construct();
        $this->_goods_mod = &m('goods');
    }

    function index()
    {
        //先获取登录账号的角色id
        $sgrade = $_SESSION['user_info']['sgrade'];
        //如果无登录，则默认为默认分组
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        $goods_model = &m('goods');

        /* 参数 id */
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $find_good = $goods_model->get('goods_id =' . $id . " AND display_sgrade like '%,$sgrade,%'");
        if (empty($find_good['goods_id'])) {
            $this->show_warning("对不起，该商品暂时不针对您所在等级开放！");
            return;
        }
        $goods_count = count($goods_model->find("if_show = 1 AND store_id =".$find_good['store_id']));
        /* 商品各类参数 */
        $goods_statistics_model = &m('goodsstatistics');
        $goods_statistics = $goods_statistics_model->get("goods_id =" . $id);
        /* 自己是否已经收藏 */
        if (!empty($_SESSION['user_info']['user_id'])) {
            $collect_model = &db();
            $collect = $collect_model->getone(
                "select count(*) from ecm_collect where type='goods'  AND item_id=" . $id . " AND user_id =" . $_SESSION['user_info']['user_id']
            );
        }
        $spec_model = & m("goodsspec");
        //针对蓝羽商品，搜索数量最少的，同步库存
        if($find_good['store_id'] == 7){
            $spec = $spec_model->get(array(
                'conditions' => "goods_id =".$find_good['goods_id']." AND sku is not null AND sku != ''",
                'order' => 'stock',
            ));
            $change_specs = $spec_model->find("goods_id =".$spec['goods_id']." AND sku = '".$spec['sku']."'");
            foreach($change_specs as $val){
                $val['stock'] = $spec['stock'];
                $spec_model->edit($val['spec_id'],$val);
            }
        }


        $store_model = &m('store');
        $store = $store_model->get("store_id =" . $find_good['store_id']);
        //同类型产品（同品牌）
        $brand_goods = $goods_model->find("brand = '" . $find_good['brand'] . "' AND if_show = 1 AND store_id =" . $find_good['store_id'] . " AND goods_id !=" . $find_good['goods_id']);

        foreach($brand_goods as $key => $good){
            $spec = $spec_model->get(array(
                'conditions' => "goods_id =" . $good['goods_id'] . " AND spec_2 =" . $sgrade,
            ));
            $brand_goods[$key]['spec'] = $spec['spec_1'];
            $brand_goods[$key]['spec_price'] = $spec['price'];
            $brand_goods[$key]['spec_id'] = $spec['spec_id'];
            $brand_goods[$key]['original_price'] = $spec['original_price'];
        }

        //查看本商品是否在限时优惠活动期内
        $special_model = &m('special');
        $special_goods_model = &m('special_goods');
        $specials = $special_model->find("start <=" . gmtime() . " AND end >=" . gmtime());
        $special_id = array();
        if (!empty($specials)) {
            foreach ($specials as $special):
                $special_id[] = $special['special_id'];
            endforeach;
        }
        if (!empty($special_id)) {
            $special_id = implode(',', $special_id);
        }

        /* 可缓存数据 */
        $data = $this->_get_common_info($id);
        if ($data === false) {
            return;
        } else {
            $this->_assign_common_info($data);
        }
        header("Content-type:text/html;charset=utf-8");
        $images_model = &m('goodsimage');
        $images = $images_model->find("goods_id =" . $id);
        $goods_id = $data['goods']['goods_id'];
//        $spec_model = &m('goodsspec');
        $goods_specs = $spec_model->find(array(
            'conditions'  => "goods_id =" . $id . " AND spec_2 =" . $sgrade,
            'order' => 'price',
        ));
        //如果优惠活动不为空，就将价钱替换了
        if (!empty($special_id)) {
            foreach ($goods_specs as $key => $val):
                $special_goods = $special_goods_model->get("spec_id =" . $val['spec_id'] . " AND special_id in (" . $special_id . ")");
                if (!empty($special_goods)) {
                    $goods_specs[$key]['original_price'] = $val['price'];
                    $goods_specs[$key]['price'] = $special_goods['price'];
                }
            endforeach;
        }
        $goods_store_id = $data['goods']['store_id'];
        if (ECMALL_WAP == 1) {
            $data = $this->_get_goods_comment($id, 10);
            $this->_assign_goods_comment($data);
        }
        //获取商品的政策
        $order_rule_store_model = &m('order_rule_store');
        $order_rule_products_model = &m('order_rule_products');
        $order_products = $order_rule_products_model->find("goods_id =" . $id);
        $ids = array();
        foreach ($order_products as $val):
            $ids[] = $val['sub_rule_id'];
        endforeach;
        if (!empty($ids)) {
            $ids_str = implode(',', $ids);
            if (!empty($_SESSION['user_info']['user_id'])) {
                $order_rule_store = $order_rule_store_model->find('id in (' . $ids_str . ") AND (user_id=" . $_SESSION['user_info']['user_id'] . " OR user_id is null OR user_id = 0) AND enabled = 1");
            } else {
                $order_rule_store = $order_rule_store_model->find('id in (' . $ids_str . ") AND (user_id is null OR user_id = 0) AND enabled = 1");
            }
            $this->assign('order_rule_stores', $order_rule_store);
        }

        /* 更新浏览次数 */
        $this->_update_views($id);
        $this->assign('id', $id);
        //是否开启验证码
        if (Conf::get('captcha_status.goodsqa')) {
            $this->assign('captcha', 1);
        }

        // sku tyioocom
        $goods_pvs_mod = &m('goods_pvs');
        $props_mod = &m('props');
        $prop_value_mod = &m('prop_value');
        $goods_pvs = $goods_pvs_mod->get($id); // 取出该商品的属性字符串
        $goods_pvs_str = $goods_pvs['pvs'];
        $props = array();
        if (!empty($goods_pvs_str)) {
            $goods_pvs_arr = explode(';', $goods_pvs_str); //  分割成数组
            foreach ($goods_pvs_arr as $pv) {
                if ($pv) {
                    $pv_arr = explode(':', $pv);
                    $prop = $props_mod->get(array('conditions' => 'pid=' . $pv_arr[0] . ' AND status=1'));
                    if ($prop) {
                        $prop_value = $prop_value_mod->get(array('conditions' => 'pid=' . $pv_arr[0] . ' AND vid=' . $pv_arr[1] . ' AND status=1'));
                        if ($prop_value) {
                            $props[] = array('name' => $prop['name'], 'value' => $prop_value['prop_value']);
                        }
                    }
                }
            }
        }
        $this->assign('props', $props);
        // end sku

        /**
         * 查看产品所有的规格spec_id
         * 与K3库存同步，模式是先搜索inventoryanson表，查看是否有对应物流编码的记录，就就同步库存并删除记录，无就不用理会
         *
         */

        $spec_model = &m('goodsspec');
        $specs = $spec_model->find('goods_id =' . $goods_id);
        $k3_inventory_model = &m('inventoryanson');
        foreach ($specs as $spec):
            $k3_inventory = $k3_inventory_model->get("fFitemnumber like '" . $spec['sku'] . "'");
            if (!empty($k3_inventory)) {
                $spec['stock'] = $k3_inventory['fFQty'];
                $spec_model->edit($spec['spec_id'], $spec);
                $k3_inventory_model->drop('fFrowid =' . $k3_inventory['fFrowid']);
            }
        endforeach;

        /* Rin: Get sgrade of current user */
        $user_id = $this->visitor->get('user_id');

        $mod_user = &m('member');
        $user_info = $mod_user->get(array(
            'conditions' => "user_id = '{$user_id}'",
            'join' => 'has_store',
            'fields' => 'sgrade',
        ));
        //从get上面获取goods_detail
        $goods_detail = $_GET['goods_detail'];
        $this->assign('goods_detail', $goods_detail);
        $this->assign('find_good', $find_good);
        $this->assign('user_info', $user_info);
        $this->assign('images', $images);
        $this->assign('collect', $collect);
        $this->assign('store', $store);
        $this->assign('sgrade', $sgrade);
        $this->assign('goods_count',$goods_count);
        $this->assign('brand_goods', $brand_goods);
        $this->assign('goods_specs', $goods_specs);
        $this->assign('goods_statistics', $goods_statistics);
        $this->assign('guest_comment_enable', Conf::get('guest_comment'));
        $this->display('goods.index.html');
    }

    /*
     * 商品详细
     */
    function details()
    {
        //先获取登录账号的角色id
        $sgrade = $_SESSION['user_info']['sgrade'];
        //如果无登录，则默认为默认分组
        if (empty($sgrade)) {
            $sgrade = 1;
        }
        $goods_model = &m('goods');

        /* 参数 id */
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $find_good = $goods_model->get('goods_id =' . $id . " AND display_sgrade like '%,$sgrade,%'");
        if (empty($find_good['goods_id'])) {
            $this->show_warning("对不起，该商品暂时不针对您所在等级开放！");
            return;
        }

        /* 可缓存数据 */
        $data = $this->_get_common_info($id);
        if ($data === false) {
            return;
        } else {
            $this->_assign_common_info($data);
        }
        $goods_id = $data['goods']['goods_id'];
        $goods_store_id = $data['goods']['store_id'];
        if (ECMALL_WAP == 1) {
            $data = $this->_get_goods_comment($id, 10);
            $this->_assign_goods_comment($data);
        }


        /* 更新浏览次数 */
        $this->_update_views($id);
        $this->assign('id', $id);
        //是否开启验证码
        if (Conf::get('captcha_status.goodsqa')) {
            $this->assign('captcha', 1);
        }

        // sku tyioocom 
        $goods_pvs_mod = &m('goods_pvs');
        $props_mod = &m('props');
        $prop_value_mod = &m('prop_value');
        $goods_pvs = $goods_pvs_mod->get($id); // 取出该商品的属性字符串
        $goods_pvs_str = $goods_pvs['pvs'];
        $props = array();
        if (!empty($goods_pvs_str)) {
            $goods_pvs_arr = explode(';', $goods_pvs_str); //  分割成数组
            foreach ($goods_pvs_arr as $pv) {
                if ($pv) {
                    $pv_arr = explode(':', $pv);
                    $prop = $props_mod->get(array('conditions' => 'pid=' . $pv_arr[0] . ' AND status=1'));
                    if ($prop) {
                        $prop_value = $prop_value_mod->get(array('conditions' => 'pid=' . $pv_arr[0] . ' AND vid=' . $pv_arr[1] . ' AND status=1'));
                        if ($prop_value) {
                            $props[] = array('name' => $prop['name'], 'value' => $prop_value['prop_value']);
                        }
                    }
                }
            }
        }
        $this->assign('props', $props);
        // end sku

        /* Rin: Get sgrade of current user */
        $user_id = $this->visitor->get('user_id');

        $mod_user = &m('member');
        $user_info = $mod_user->get(array(
            'conditions' => "user_id = '{$user_id}'",
            'join' => 'has_store',
            'fields' => 'sgrade',
        ));

        $this->assign('user_info', $user_info);

        $this->assign('guest_comment_enable', Conf::get('guest_comment'));
        $this->display('goods.details.html');
    }


    /* 商品评论 */

    function comments()
    {
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }

        $data = $this->_get_common_info($id);
        if ($data === false) {
            return;
        } else {
            $this->_assign_common_info($data);
        }

        /* 赋值商品评论 */
        $data = $this->_get_goods_comment($id, 10);
        $this->_assign_goods_comment($data);

        $this->display('goods.comments.html');
    }

    /* 销售记录 */

    function saleslog()
    {
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }

        $data = $this->_get_common_info($id);
        if ($data === false) {
            return;
        } else {
            $this->_assign_common_info($data);
        }

        /* 赋值销售记录 */
        $data = $this->_get_sales_log($id, 10);
        $this->_assign_sales_log($data);

        $this->display('goods.saleslog.html');
    }

    function qa()
    {
        $goods_qa = &m('goodsqa');
        $id = intval($_GET['id']);
        if (!$id) {
            $this->show_warning('Hacking Attempt');
            return;
        }
        if (!IS_POST) {
            $data = $this->_get_common_info($id);
            if ($data === false) {
                return;
            } else {
                $this->_assign_common_info($data);
            }
            $data = $this->_get_goods_qa($id, 10);
            $this->_assign_goods_qa($data);

            //是否开启验证码
            if (Conf::get('captcha_status.goodsqa')) {
                $this->assign('captcha', 1);
            }
            $this->assign('guest_comment_enable', Conf::get('guest_comment'));
            /* 赋值产品咨询 */
            $this->display('goods.qa.html');
        } else {
            /* 不允许游客评论 */
            if (!Conf::get('guest_comment') && !$this->visitor->has_login) {
                $this->show_warning('guest_comment_disabled');

                return;
            }
            $content = (isset($_POST['content'])) ? trim($_POST['content']) : '';
            //$type = (isset($_POST['type'])) ? trim($_POST['type']) : '';
            $email = (isset($_POST['email'])) ? trim($_POST['email']) : '';
            $hide_name = (isset($_POST['hide_name'])) ? trim($_POST['hide_name']) : '';
            if (empty($content)) {
                $this->show_warning('content_not_null');
                return;
            }
            //对验证码和邮件进行判断

            if (Conf::get('captcha_status.goodsqa')) {
                if (base64_decode($_SESSION['captcha']) != strtolower($_POST['captcha'])) {
                    $this->show_warning('captcha_failed');
                    return;
                }
            }
            if (!empty($email) && !is_email($email)) {
                $this->show_warning('email_not_correct');
                return;
            }
            $user_id = empty($hide_name) ? $_SESSION['user_info']['user_id'] : 0;
            $conditions = 'g.goods_id =' . $id;
            $goods_mod = &m('goods');
            $ids = $goods_mod->get(array(
                'fields' => 'store_id,goods_name',
                'conditions' => $conditions
            ));
            extract($ids);
            $data = array(
                'question_content' => $content,
                'type' => 'goods',
                'item_id' => $id,
                'item_name' => addslashes($goods_name),
                'store_id' => $store_id,
                'email' => $email,
                'user_id' => $user_id,
                'time_post' => gmtime(),
            );
            if ($goods_qa->add($data)) {
                header("Location: index.php?app=goods&act=qa&id={$id}#module\n");
                exit;
            } else {
                $this->show_warning('post_fail');
                exit;
            }
        }
    }

    /**
     * 取得公共信息
     *
     * @param   int $id
     * @return  false   失败
     *          array   成功
     */
    function _get_common_info($id)
    {
        $cache_server = &cache_server();
        $key = 'page_of_goods_' . $id;
//        $data = $cache_server->get($key);
        $data = false;//本网页暂时不用缓存
        $cached = true;
        if ($data === false) {
            $cached = false;
            $data = array('id' => $id);

            /* 商品信息 */
            $goods = $this->_goods_mod->get_info($id);
            if (!$goods || $goods['if_show'] == 0 || $goods['closed'] == 1 || $goods['state'] != 1) {
                $this->show_warning('goods_not_exist');
                return false;
            }
            $goods['tags'] = $goods['tags'] ? explode(',', trim($goods['tags'], ',')) : array();

            $data['goods'] = $goods;

            /* 店铺信息 */
            if (!$goods['store_id']) {
                $this->show_warning('store of goods is empty');
                return false;
            }
            $this->set_store($goods['store_id']);
            $data['store_data'] = $this->get_store_data();

            /* 当前位置 */
            $data['cur_local'] = $this->_get_curlocal($goods['cate_id']);
            $data['goods']['related_info'] = $this->_get_related_objects($data['goods']['tags']);
            /* 分享链接 */
            $data['share'] = $this->_get_share($goods);

            $cache_server->set($key, $data, 1800);
        }
        if ($cached) {
            $this->set_store($data['goods']['store_id']);
        }

        return $data;
    }

    function _get_related_objects($tags)
    {
        if (empty($tags)) {
            return array();
        }
        $tag = $tags[array_rand($tags)];
        $ms = &ms();

        return $ms->tag_get($tag);
    }

    /* 赋值公共信息 */

    function _assign_common_info($data)
    {

        /* 商品信息 */
        $goods = $data['goods'];
        $url = SITE_URL . '/index.php?app=goods%26id=' . $goods['goods_id'];
        $goods['scan_code'] = '<img src=' . SITE_URL . '/index.php?app=qrcode&url=' . $url . '/>';
        $this->assign('goods', $goods);
        //销售件数直接加880
        $this->assign('sales_info', sprintf(LANG::get('sales'), $goods['sales'] ? $goods['sales'] + 880 : 880));
        $this->assign('comments', sprintf(LANG::get('comments'), $goods['comments'] ? $goods['comments'] : 0));

        /* 店铺信息 */
        $this->assign('store', $data['store_data']);

        /* 浏览历史 */
        $this->assign('goods_history', $this->_get_goods_history($data['id']));

        /* 默认图片 */
        $this->assign('default_image', Conf::get('default_goods_image'));

        /* 当前位置 */
        $this->_curlocal($data['cur_local']);

        /* 配置seo信息 */
        $this->_config_seo($this->_get_seo_info($data['goods']));

        /* 商品分享 */
        $this->assign('share', $data['share']);

        $this->import_resource(array(
            'script' => 'jquery.jqzoom.js',
            'style' => 'res:jqzoom.css'
        ));
    }

    /* 取得浏览历史 */

    function _get_goods_history($id, $num = 9)
    {
        $goods_list = array();
        $goods_ids = ecm_getcookie('goodsBrowseHistory');
        $goods_ids = $goods_ids ? explode(',', $goods_ids) : array();
        if ($goods_ids) {
            $rows = $this->_goods_mod->find(array(
                'conditions' => $goods_ids,
                'fields' => 'goods_name,default_image',
            ));
            foreach ($goods_ids as $goods_id) {
                if (isset($rows[$goods_id])) {
                    empty($rows[$goods_id]['default_image']) && $rows[$goods_id]['default_image'] = Conf::get('default_goods_image');
                    $goods_list[] = $rows[$goods_id];
                }
            }
        }
        $goods_ids[] = $id;
        if (count($goods_ids) > $num) {
            unset($goods_ids[0]);
        }
        ecm_setcookie('goodsBrowseHistory', join(',', array_unique($goods_ids)));

        return $goods_list;
    }

    /* 取得销售记录 */

    function _get_sales_log($goods_id, $num_per_page)
    {
        $data = array();

        $page = $this->_get_page($num_per_page);
        $order_goods_mod = &m('ordergoods');
        $sales_list = $order_goods_mod->find(array(
            'conditions' => "goods_id = '$goods_id' AND status = '" . ORDER_FINISHED . "'",
            'join' => 'belongs_to_order',
            'fields' => 'buyer_id, buyer_name, add_time, anonymous, goods_id, specification, price, quantity, evaluation',
            'count' => true,
            'order' => 'add_time desc',
            'limit' => $page['limit'],
        ));
        $data['sales_list'] = $sales_list;

        $page['item_count'] = $order_goods_mod->getCount();
        $this->_format_page($page);
        $data['page_info'] = $page;
        $data['more_sales'] = $page['item_count'] > $num_per_page;

        return $data;
    }

    /* 赋值销售记录 */

    function _assign_sales_log($data)
    {
        $this->assign('sales_list', $data['sales_list']);
        $this->assign('page_info', $data['page_info']);
        $this->assign('more_sales', $data['more_sales']);
    }

    /* 取得商品评论 */

    function _get_goods_comment($goods_id, $num_per_page)
    {
        $data = array();

        $page = $this->_get_page($num_per_page);
        $order_goods_mod = &m('ordergoods');
        $comments = $order_goods_mod->find(array(
            'conditions' => "goods_id = '$goods_id' AND evaluation_status = '1'",
            'join' => 'belongs_to_order',
            'fields' => 'buyer_id, buyer_name, anonymous, evaluation_time, comment, evaluation',
            'count' => true,
            'order' => 'evaluation_time desc',
            'limit' => $page['limit'],
        ));
        $data['comments'] = $comments;

        $page['item_count'] = $order_goods_mod->getCount();
        $this->_format_page($page);
        $data['page_info'] = $page;
        $data['more_comments'] = $page['item_count'] > $num_per_page;

        return $data;
    }

    /* 赋值商品评论 */

    function _assign_goods_comment($data)
    {
        $this->assign('goods_comments', $data['comments']);
        $this->assign('page_info', $data['page_info']);
        $this->assign('more_comments', $data['more_comments']);
    }

    /* 取得商品咨询 */

    function _get_goods_qa($goods_id, $num_per_page)
    {
        $page = $this->_get_page($num_per_page);
        $goods_qa = &m('goodsqa');
        $qa_info = $goods_qa->find(array(
            'join' => 'belongs_to_user',
            'fields' => 'member.user_name,question_content,reply_content,time_post,time_reply',
            'conditions' => '1 = 1 AND item_id = ' . $goods_id . " AND type = 'goods'",
            'limit' => $page['limit'],
            'order' => 'time_post desc',
            'count' => true
        ));
        $page['item_count'] = $goods_qa->getCount();
        $this->_format_page($page);

        //如果登陆，则查出email
        if (!empty($_SESSION['user_info'])) {
            $user_mod = &m('member');
            $user_info = $user_mod->get(array(
                'fields' => 'email',
                'conditions' => '1=1 AND user_id = ' . $_SESSION['user_info']['user_id']
            ));
            extract($user_info);
        }

        return array(
            'email' => $email,
            'page_info' => $page,
            'qa_info' => $qa_info,
        );
    }

    /* 赋值商品咨询 */

    function _assign_goods_qa($data)
    {
        $this->assign('email', $data['email']);
        $this->assign('page_info', $data['page_info']);
        $this->assign('qa_info', $data['qa_info']);
    }

    /* 更新浏览次数 */

    function _update_views($id)
    {
        $goodsstat_mod = &m('goodsstatistics');
        $goodsstat_mod->edit($id, "views = views + 1");
    }

    /**
     * 取得当前位置
     *
     * @param int $cate_id 分类id
     */
    function _get_curlocal($cate_id)
    {
        $parents = array();
        if ($cate_id) {
            $gcategory_mod = &bm('gcategory');
            $parents = $gcategory_mod->get_ancestor($cate_id, true);
        }

        $curlocal = array(
            array('text' => LANG::get('all_categories'), 'url' => url('app=category')),
        );
        foreach ($parents as $category) {
            $curlocal[] = array('text' => $category['cate_name'], 'url' => url('app=search&cate_id=' . $category['cate_id']));
        }
        $curlocal[] = array('text' => LANG::get('goods_detail'));

        return $curlocal;
    }

    function _get_share($goods)
    {
        $m_share = &af('share');
        $shares = $m_share->getAll();
        $shares = array_msort($shares, array('sort_order' => SORT_ASC));
        $goods_name = ecm_iconv(CHARSET, 'utf-8', $goods['goods_name']);
        $goods_url = urlencode(SITE_URL . '/' . str_replace('&amp;', '&', url('app=goods&id=' . $goods['goods_id'])));
        $site_title = ecm_iconv(CHARSET, 'utf-8', Conf::get('site_title'));
        $share_title = urlencode($goods_name . '-' . $site_title);
        foreach ($shares as $share_id => $share) {
            $shares[$share_id]['link'] = str_replace(
                array('{$link}', '{$title}'), array($goods_url, $share_title), $share['link']);
        }
        return $shares;
    }

    function _get_seo_info($data)
    {
        $seo_info = $keywords = array();
        $seo_info['title'] = $data['goods_name'] . ' - ' . Conf::get('site_title');
        $keywords = array(
            $data['brand'],
            $data['goods_name'],
            $data['cate_name']
        );
        $seo_info['keywords'] = implode(',', array_merge($keywords, $data['tags']));
        $seo_info['description'] = sub_str(strip_tags($data['description']), 10, true);
        return $seo_info;
    }

    //产品图片放大页面
    function image()
    {
        $image = $_GET['image'];
        $this->assign('image', $image);
        $this->display('image.html');
    }


    //查商品类型
    function _assign_searched_goods($id, $goods_id)
    {
        $goods_mod =& bm('goods', array('_store_id' => $id));  //只查该店铺的商品
        $search_name = LANG::get('all_goods');

        $conditions = $this->_get_query_conditions(array(
            array(
                'field' => 'goods_name',
                'name' => 'keyword',
                'equal' => 'like',
            ),
        ));

        //查产品属于的本店分类的id（由于墙纸厂的特殊性，本店分类只能是一个）
        $category_goods_model = &m('category_goods');
        $category = $category_goods_model->get('goods_id = ' . $goods_id);
//        print_r($category);exit;
        $sgcate_id = $category['cate_id'];
        if ($sgcate_id > 0) {
            $gcategory_mod =& bm('gcategory', array('_store_id' => $id));
            $sgcate = $gcategory_mod->get_info($sgcate_id);
            $search_name = $sgcate['cate_name'];

            $sgcate_ids = $gcategory_mod->get_descendant_ids($sgcate_id);
        } else {
            $sgcate_ids = array();
        }
        $goods_list = $goods_mod->get_list(array(
            'conditions' => 'closed = 0 AND if_show = 1' . $conditions,
//            'fields' => 'goods_id',
            'count' => true,
        ), $sgcate_ids);
        foreach ($goods_list as $key => $goods) {
            empty($goods['default_image']) && $goods_list[$key]['default_image'] = Conf::get('default_goods_image');
        }
        return $goods_list;
    }

    //ajax获取地区对应的qq客服号
    function find_qq()
    {

        $store_id = $_POST['store_id'];
        $qq_service_model = &m('qq_service');
        $member_model = &m('member');
        $user_id = $_SESSION['user_info']['user_id'];
        if (empty($user_id) || empty($store_id)) {
            echo '222';
            exit;
        }

        $member = $member_model->get('user_id =' . $user_id);
        //查客户对应地区的qq号
        $qq = $qq_service_model->get('store_id =' . $store_id . " AND region like '" . $member['province'] . "'");
        if (!empty($qq['qq'])) {
            echo $qq['qq'];
        } else {
            echo '111';
        }
        exit;
    }

    //客户品牌保护申请
    function protect_apply()
    {
        if (empty($_SESSION['user_info']['sgrade']) || $_SESSION['user_info']['user_id'] == 1) {
            $this->show_warning('对不起，只有vip以上客户可以申请！');
            return;
        }
        if (!IS_POST) {
            $goods_model = &m('goods');
            $goods_id = $_GET['goods_id'];
            if (!$this->check_protect($goods_id)) {
                $this->show_warning('对不起，该品牌已经被同一区域其他商家申请了');
                return;
            }
            $goods = $goods_model->get('goods_id =' . $goods_id);
            $brand_goods = $goods_model->find(array(
                'conditions' => "brand like '" . $goods['brand'] . "'",
                'fields' => 'goods_name,default_image',
            ));
            $province_model = &m('province');
            $provinces = $province_model->findAll();
            $this->assign('provinces', $provinces);
            $brand_model = &m('brand');
            $brand = $brand_model->get("brand_name like '" . $goods['brand'] . "'");
            $this->assign('brand', $brand);
            $this->assign('brand_goods', $brand_goods);
            $this->display('protect_apply.html');
        } else {
            $province_id = $_POST['province'];
            $city = $_POST['city'];
            $address = $_POST['address'];
            $province_model = &m('province');
            $province = $province_model->get('id =' . $province_id);
            $brand_id = $_POST['brand_id'];
            $brand_name = $_POST['brand_name'];
            $store_id = $_POST['store_id'];
            $user_id = $_SESSION['user_info']['user_id'];
            $user_brand_modle = &m('user_brand');
            $user_brand = $user_brand_modle->get('user_id =' . $user_id . " AND brand_id =" . $brand_id . " AND status != 2");
            if (!empty($user_brand['id'])) {
                $this->show_warning('您已经申请了该品牌的保护政策，不能重复申请！');
                return;
            }
            $user_model = &m('member');
            $user = $user_model->get('user_id =' . $user_id);
            $new_user_brand = array(
                'brand_id' => $brand_id,
                'user_id' => $user_id,
                'user_name' => $user['user_name'],
                'brand_name' => $brand_name,
                'province' => $province['name'],
                'city' => $city,
                'address' => $address,
                'status' => 3,
                'add_time' => gmtime(),
                'store_id' => $store_id,
            );
            $user_brand_modle->add($new_user_brand);
            $this->show_message('申请成功！', '请等待厂家审核', 'index.php?app=member');
        }
    }

    //根据省份查城市
    function find_city()
    {
        $province_id = $_POST['province_id'];
        $city_model = &m('city');
        $citys = $city_model->find('topid =' . $province_id);
        foreach ($citys as $city):
            echo "<option value='" . $city['name'] . "'>";
            echo $city['name'];
            echo "</option>";
        endforeach;
        exit;
    }

    //品牌保护审核
    function check_protect($goods_id)
    {
        $goods_model = &m('goods');
        $goods = $goods_model->get(array(
            'conditions' => 'goods_id =' . $goods_id,
            'fields' => 'brand',
        ));
        //没有品牌，直接通过
        if (empty($goods['brand'])) {
            return true;
        }
        $brand_model = &m('brand');
        $brand = $brand_model->get("brand_name like '" . $goods['brand'] . "'");
        if (empty($brand['brand_id'])) {
            return true;
        }
        //查是否已经绑定
        $user_brand_model = &m('user_brand');
        $user_brand = $user_brand_model->get('status = 1 AND brand_id =' . $brand['brand_id'] . " AND user_id =" . $_SESSION['user_info']['user_id']);
        if (!empty($user_brand['id'])) {
            return true;
        }
        //查卖场
        $user_price_model = &m('user_price');
        $user_price = $user_price_model->find('user_id =' . $_SESSION['user_info']['user_id']);
        //查卖场所有绑定的用户
        foreach ($user_price as $price):
            $prices = $user_price_model->find("price_id =" . $price['price_id']);
            //查卖场中是否存在绑定了该品牌的客户
            foreach ($prices as $val):
                $user_has_brand = $user_brand_model->get('status = 1 AND brand_id=' . $brand['brand_id'] . " AND user_id =" . $val['user_id']);
                //有人绑定，就不可以买了
                if (!empty($user_has_brand['id'])) {
                    return false;
                }
            endforeach;
        endforeach;
        return true;
    }

}

?>
