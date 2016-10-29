<?php

class Order_rule_storeModel extends BaseModel
{
    var $table  = 'order_rule_store';
    var $prikey = 'id';
    var $_name  = 'order_rule_store';
    var $_relation  = array(
        'has_rule_products' => array(
            'model'         => 'order_rule_products',
            'type'          => HAS_MANY,
            'foreign_key'   => 'sub_rule_id',
            'dependent'     => true
        ),
	);
}

?>
