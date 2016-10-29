<?php
//积分兑换商品的model
class Redeem_ordersModel extends BaseModel{
    var $table = 'redeem_orders';
    var $prikey = 'order_id';
    var $name = 'redeem_orders';
    //多表关联
    var $_relation = array(
        'redeem_goods' =>array(
            'model' => 'redeem_goods', //对应的关联表
            'type'  => BELONGS_TO,
            'foreign_key'   => 'goods_id',
            'dependent'     => true,
        ),
    );

}

