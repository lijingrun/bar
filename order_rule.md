购满数量赠送产品

---

{"rules_amount":"数量下限", "rules_discount": "折扣", "rules_discount_mode": "折扣方式", "rules_gift_quantity": "赠送数量"}

---

if( intval($arguments["rules_amount"]) <= $goods['quantity'] ) {
  // present
  $present_good = $this->default_cart_item($return['items'], $goods['goods_id']);
  $present_good['price'] = 0;
  $present_good['spec_id'] = $goods['spec_id'];
  $present_good['specification'] = $goods['specification'];

  // addition.
  $present_good['goods_name'] .= '(赠送产品)';
  $present_good['quantity'] = intval( intval($arguments["rules_gift_quantity"])*($goods['quantity']/intval($arguments["rules_amount"])));

  // add present, here use $cart_items
  array_push($cart_items, $present_good );

  // set priority
  $procedured_goods[$osrs[order_rule_id]][$osrs[rp_spec_id]] = 1;
}

---



特殊客户特殊价格

---

{"rules_price":"价格"}

---

$return['amount'] -= $goods['quantity'] * $goods['price'];
$return['amount'] += $goods['quantity'] * intval($arguments["rules_price"]);

// replace cart_item goods price.
$cart_items[$rec_id]['price'] = intval($arguments["rules_price"]);

// replace 
$return['discount'] += $goods['quantity'] * $goods['price'] - $goods['quantity'] * intval($arguments["rules_price"]);

// replace cart_item sub total price.
$cart_items[$rec_id]['subtotal'] = $goods['quantity'] * intval($arguments["rules_price"]);



组合产品按数量赠送

---

{"rule_each_quantity_lower": "数量下限", "rule_each_quantity_upper": "数量上限", "rule_total_quantity": "总量", "rule_present_product": { "caption": "赠送商品", "label": "select", 
"script": "$('#products-list').change( function() { $('select[name=\"rule_present_product\"]').empty(); $('#products-list option').each( function() { $('select[name=\"rule_present_product\"]').append( '<option value=\"' + $(this).val() + '\">' + $(this).text() + '</option>' ) } ); }  ); $('#products-list').change(); $('#add-product, #rm-product').bind('click', function() {$('#products-list').change();} )" } , "rule_present_quantity": "赠送数量"}

---

if( !$rule_storage[$osrs[order_rule_id]][$osrs[rp_spec_id]] ) {

  // define
  $rule_storage[$osrs[order_rule_id]][$osrs[rp_spec_id]] = 1;
  $rule_storage[$osrs[order_rule_id]]['total_quantity'] += $goods['quantity'];

  if( $goods['quantity'] > intval($arguments["rule_each_quantity_upper"]) || 
      $goods['quantity'] < intval($arguments["rule_each_quantity_lower"]) ) 
  $rule_storage[$osrs[order_rule_id]]['error_code'] = 1;

}

// no error, condition comfortable
if( !$rule_storage[$osrs[order_rule_id]]['error_code'] && 
  $rule_storage[$osrs[order_rule_id]]['total_quantity'] >= intval($arguments["rule_total_quantity"]) ) {
  
  // get products
  $model_goodsspec = &m('goodsspec');
  $present_product = $model_goodsspec->get('spec_id=' . $arguments["rule_present_product"] );
  
  // present
  $present_good = $this->default_cart_item($return['items'], $present_product['goods_id']);
  $present_good['price'] = 0;
  $present_good['spec_id'] = $present_product['spec_id'];
  $present_good['specification'] = $present_product['spec_1'];

  // addition.
  $present_good['goods_name'] .= '(组合赠送产品)';
  $present_good['quantity'] = intval( $arguments["rule_present_quantity"] );

  // add present, here use $cart_items
  array_push($cart_items, $present_good );
}

---

if( !$rule_storage[$osrs[order_rule_id]][$osrs[rp_spec_id]] ) {
  if( !$rule_storage[$osrs[order_rule_id]]['min_price']) $rule_storage[$osrs[order_rule_id]]['min_price'] = 9999999;

  // define
  $rule_storage[$osrs[order_rule_id]][$osrs[rp_spec_id]] = 1;

  if( $goods['quantity'] > intval($arguments["rule_each_quantity_upper"]) || 
      $goods['quantity'] < intval($arguments["rule_each_quantity_lower"]) ) {
      //jump over
  }
  else {
      $rule_storage[$osrs[order_rule_id]]['total_quantity'] += $goods['quantity'];
      if( $goods['price'] < $rule_storage[$osrs[order_rule_id]]['min_price'] ) {
          $rule_storage[$osrs[order_rule_id]]['min_price'] = $goods['price'];
          $rule_storage[$osrs[order_rule_id]]['goods_id'] = $goods['goods_id'];
          $rule_storage[$osrs[order_rule_id]]['spec_id'] = $goods['spec_id'];
          $rule_storage[$osrs[order_rule_id]]['specification'] = $goods['specification'];
          $rule_storage[$osrs[order_rule_id]]['quantity'] = intval($arguments["rule_present_quantity"]);
      }
  }
}

// no error, condition comfortable
if( !$rule_storage[$osrs[order_rule_id]]['error_code'] && 
  $rule_storage[$osrs[order_rule_id]]['total_quantity'] >= intval($arguments["rule_total_quantity"]) ) {

  // has presented.
  $rule_storage[$osrs[order_rule_id]]['error_code'] = 2;
}

---

[{"logic": "AND", "model": "order_rule", "conditions": "1=1", "fields": "id", "join": ""}]

---

foreach ($rule_storage as $k => $v) {
    if( $v['error_code'] == 2 ) {
        $present_good = $this->default_cart_item($return['items'], $v['goods_id']);
        $present_good['goods_name'] .= '(组合赠送)';
        $present_good['quantity'] = $v['quantity'];
        $present_good['price'] = 0;
        $present_good['spec_id'] = $v['spec_id'];
        $present_good['specification'] = $v['specification'];

        // add present, here use $return['items']
        array_push($return['items'], $present_good );
    }
}
