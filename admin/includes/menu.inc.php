<?php

return array(
    'dashboard' => array(
        'text' => Lang::get('dashboard'),
        'subtext' => Lang::get('offen_used'),
        'default' => 'welcome',
        'children' => array(
            'welcome' => array(
                'text' => Lang::get('welcome_page'),
                'url' => 'index.php?act=welcome',
            ),
            'aboutus' => array(
                'text' => Lang::get('aboutus_page'),
                'url' => 'index.php?act=aboutus',
            ),
            'base_setting' => array(
                'parent' => 'setting',
                'text' => Lang::get('base_setting'),
                'url' => 'index.php?app=setting&act=base_setting',
            ),
            'user_manage' => array(
                'text' => Lang::get('user_manage'),
                'parent' => 'user',
                'url' => 'index.php?app=user',
            ),
            'store_manage' => array(
                'text' => Lang::get('store_manage'),
                'parent' => 'store',
                'url' => 'index.php?app=store',
            ),
            'goods_manage' => array(
                'text' => Lang::get('goods_manage'),
                'parent' => 'goods',
                'url' => 'index.php?app=goods',
            ),
            'order_manage' => array(
                'text' => Lang::get('order_manage'),
                'parent' => 'trade',
                'url' => 'index.php?app=order'
            ),
        ),
    ),
    // 设置
    'setting' => array(
        'text' => Lang::get('setting'),
        'default' => 'base_setting',
        'children' => array(
            'base_setting' => array(
                'text' => Lang::get('base_setting'),
                'url' => 'index.php?app=setting&act=base_setting',
            ),
            'region' => array(
                'text' => Lang::get('region'),
                'url' => 'index.php?app=region',
            ),
            'payment' => array(
                'text' => Lang::get('payment'),
                'url' => 'index.php?app=payment',
            ),
            'theme' => array(
                'text' => Lang::get('theme'),
                'url' => 'index.php?app=theme',
            ),
            'waptheme' => array(
                'text' => Lang::get('waptheme'),
                'url' => 'index.php?app=waptheme',
            ),
            'template' => array(
                'text' => Lang::get('template'),
                'url' => 'index.php?app=template',
            ),
            'mailtemplate' => array(
                'text' => Lang::get('noticetemplate'),
                'url' => 'index.php?app=mailtemplate',
            ),
            'index_img' => array(
                'text'  => '首页图片',
                'url'   => 'index.php?app=index_img'
            ),
            'mounth' => array(
                'text'  => '大姨妈政策',
                'url'   => 'index.php?app=policy'
            ),
        ),
    ),
    // 商品
    'goods' => array(
        'text' => Lang::get('goods'),
        'default' => 'goods_manage',
        'children' => array(
            'gcategory' => array(
                'text' => Lang::get('gcategory'),
                'url' => 'index.php?app=gcategory',
            ),
            'brand' => array(
                'text' => Lang::get('brand'),
                'url' => 'index.php?app=brand',
            ),
            'goods_manage' => array(
                'text' => Lang::get('goods_manage'),
                'url' => 'index.php?app=goods',
            ),
            // tyioocom 
            'props_manage' => array(
                'text' => Lang::get('props_manage'),
                'url' => 'index.php?app=props',
            ),
            // end			
            'recommend_type' => array(
                'text' => LANG::get('recommend_type'),
                'url' => 'index.php?app=recommend'
            ),
            //积分兑换(已经整合到正常商品处)
//            'redeem' => array(
//                'text' => LANG::get('redeem'),
//                'url' => 'index.php?app=redeem'
//            )
            //活动转场设置
            'promotion' => array(
                'text' => '专场活动',
                'url' => 'index.php?app=special',
            ),
        ),
    ),
    // 店铺
    'store' => array(
        'text' => Lang::get('store'),
        'default' => 'store_manage',
        'children' => array(
            'sgrade' => array(
                'text' => Lang::get('sgrade'),
                'url' => 'index.php?app=sgrade',
            ),
            'scategory' => array(
                'text' => Lang::get('scategory'),
                'url' => 'index.php?app=scategory',
            ),
            //by cengnlaeng
            'ultimate_store' => array(
                'text' => Lang::get('ultimate_store'),
                'url' => 'index.php?app=ultimate_store',
            ),
            //end
            'store_manage' => array(
                'text' => Lang::get('store_manage'),
                'url' => 'index.php?app=store',
            ),
        ),
    ),
    // 会员
    'user' => array(
        'text' => Lang::get('user'),
        'default' => 'user_manage',
        'children' => array(
            'user_manage' => array(
                'text' => Lang::get('user_manage'),
                'url' => 'index.php?app=user',
            ),
            'admin_manage' => array(
                'text' => Lang::get('admin_manage'),
                'url' => 'index.php?app=admin',
            ),
            'user_notice' => array(
                'text' => Lang::get('user_notice'),
                'url' => 'index.php?app=notice',
            ),
            'salesman_manage' => array(
                'text' => Lang::get('salesman_manage'),
                'url' => 'index.php?app=salesman',
            ),
            'advance_payment' => array(
                'text' => '预付款管理',
                'url' => 'index.php?app=advance_payment',
            ),
        ),
    ),
    // 交易
    'trade' => array(
        'text' => Lang::get('trade'),
        'default' => 'order_manage',
        'children' => array(
            'order_manage' => array(
                'text' => Lang::get('order_manage'),
                'url' => 'index.php?app=order'
            ),
            'order_rule' => array(
                'text' => Lang::get('order_rule'),
                'url' => 'index.php?app=order_rule'
            ),
            'order_rule_group' => array(
                'text' => Lang::get('order_rule_group'),
                'url' => 'index.php?app=order_rule_group'
            ),
        ),
    ),
    // 网站
    'website' => array(
        'text' => Lang::get('website'),
        'default' => 'acategory',
        'children' => array(
            'acategory' => array(
                'text' => Lang::get('acategory'),
                'url' => 'index.php?app=acategory',
            ),
            'article' => array(
                'text' => Lang::get('article'),
                'url' => 'index.php?app=article',
            ),
            'partner' => array(
                'text' => Lang::get('partner'),
                'url' => 'index.php?app=partner',
            ),
            'navigation' => array(
                'text' => Lang::get('navigation'),
                'url' => 'index.php?app=navigation',
            ),
            'db' => array(
                'text' => Lang::get('db'),
                'url' => 'index.php?app=db&amp;act=backup',
            ),
//            'groupbuy' => array(
//                'text' => Lang::get('groupbuy'),
//                'url' => 'index.php?app=groupbuy',
//            ),
            'consulting' => array(
                'text' => LANG::get('consulting'),
                'url' => 'index.php?app=consulting',
            ),
            'share_link' => array(
                'text' => LANG::get('share_link'),
                'url' => 'index.php?app=share',
            ),
            'indeximg' => array(
                'text' => '商城首页图片',
                'url' => 'index.php?app=changeimg',
            ),
            'active' => array(
                'text' => '限时抢',
                'url' => 'index.php?app=active',
            ),
        ),
    ),
    // 扩展
    'extend' => array(
        'text' => Lang::get('extend'),
        'default' => 'plugin',
        'children' => array(
            'plugin' => array(
                'text' => Lang::get('plugin'),
                'url' => 'index.php?app=plugin',
            ),
            'module' => array(
                'text' => Lang::get('module'),
                'url' => 'index.php?app=module&act=manage',
            ),
            'widget' => array(
                'text' => Lang::get('widget'),
                'url' => 'index.php?app=widget',
            ),
        ),
    ),
);
?>
