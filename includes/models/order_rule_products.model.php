<?php

class Order_rule_productsModel extends BaseModel
{
    var $table  = 'order_rule_products';
    var $prikey = 'id';
    var $_name  = 'order_rule_products';
    var $_relation  = array(
        // 一个商品规格只能属于一个商品
        'belongs_to_goods' => array(
            'model'         => 'goods',
            'type'          => BELONGS_TO,
            'foreign_key'   => 'goods_id',
            'reverse'       => 'has_goodsspec',
        ),
    );
}

?>
