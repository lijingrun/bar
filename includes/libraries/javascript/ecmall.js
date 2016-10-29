jQuery.extend({
  getCookie : function(sName) {
    var aCookie = document.cookie.split("; ");
    for (var i=0; i < aCookie.length; i++){
      var aCrumb = aCookie[i].split("=");
      if (sName == aCrumb[0]) return decodeURIComponent(aCrumb[1]);
    }
    return '';
  },
  setCookie : function(sName, sValue, sExpires) {
    var sCookie = sName + "=" + encodeURIComponent(sValue);
    if (sExpires != null) sCookie += "; expires=" + sExpires;
    document.cookie = sCookie;
  },
  removeCookie : function(sName) {
    document.cookie = sName + "=; expires=Fri, 31 Dec 1999 23:59:59 GMT;";
  }
});
function drop_confirm(msg, url){
    if(confirm(msg)){
        window.location = url;
    }
}

/* 显示Ajax表单 */
function ajax_form(id, title, url, width)
{
    if (!width)
    {
        width = 400;
    }
    var d = DialogManager.create(id);
    d.setTitle(title);
    d.setContents('ajax', url);
    d.setWidth(width);
    d.show('center');

    return d;
}
function go(url){
    window.location = url;
}

function change_captcha(jqObj){
    jqObj.attr('src', 'index.php?app=captcha&' + Math.round(Math.random()*10000));
}

/* 格式化金额 */
function price_format(price){
    if(typeof(PRICE_FORMAT) == 'undefined'){
        PRICE_FORMAT = '&yen;%s';
    }
    price = number_format(price, 2);

    return PRICE_FORMAT.replace('%s', price);
}

function number_format(num, ext){
    if(ext < 0){
        return num;
    }
    num = Number(num);
    if(isNaN(num)){
        num = 0;
    }
    var _str = num.toString();
    var _arr = _str.split('.');
    var _int = _arr[0];
    var _flt = _arr[1];
    if(_str.indexOf('.') == -1){
        /* 找不到小数点，则添加 */
        if(ext == 0){
            return _str;
        }
        var _tmp = '';
        for(var i = 0; i < ext; i++){
            _tmp += '0';
        }
        _str = _str + '.' + _tmp;
    }else{
        if(_flt.length == ext){
            return _str;
        }
        /* 找得到小数点，则截取 */
        if(_flt.length > ext){
            _str = _str.substr(0, _str.length - (_flt.length - ext));
            if(ext == 0){
                _str = _int;
            }
        }else{
            for(var i = 0; i < ext - _flt.length; i++){
                _str += '0';
            }
        }
    }

    return _str;
}

/* 收藏商品 */
function collect_goods(id)
{
    var url = SITE_URL + '/index.php?app=my_favorite&act=add&type=goods&ajax=1';
    $.getJSON(url, {'item_id':id}, function(data){
        alert(data.msg);
    });
}

/* 收藏店铺 */
function collect_store(id)
{
    var url = SITE_URL + '/index.php?app=my_favorite&act=add&type=store&jsoncallback=?&ajax=1';
    $.getJSON(url, {'item_id':id}, function(data){
        alert(data.msg);
    });
}
/* 火狐下取本地全路径 */
function getFullPath(obj)
{
    if(obj)
    {
        //ie
        if (window.navigator.userAgent.indexOf("MSIE")>=1)
        {
            obj.select();
            if(window.navigator.userAgent.indexOf("MSIE") == 25){
            	obj.blur();
            }
            return document.selection.createRange().text;
        }
        //firefox
        else if(window.navigator.userAgent.indexOf("Firefox")>=1)
        {
            if(obj.files)
            {
                //return obj.files.item(0).getAsDataURL();
            	return window.URL.createObjectURL(obj.files.item(0)); 
            }
            return obj.value;
        }
		
        return obj.value;
    }
}


/**
 *    启动邮件队列
 *
 *    @author    Garbin
 *    @param     string req_url
 *    @return    void
 */
function sendmail(req_url)
{
    $(function(){
        var _script = document.createElement('script');
        _script.type = 'text/javascript';
        _script.src  = req_url;
        document.getElementsByTagName('head')[0].appendChild(_script);
    });
}
/* 转化JS跳转中的 ＆ */
function transform_char(str)
{
    if(str.indexOf('&'))
    {
        str = str.replace(/&/g, "%26");
    }
    return str;
}



////////////////////////////////////////////////////////////////////////////////////////////
// book when 5 boxes.
$( function() {
	// init
	var id = [2141, 2142, 2173, 2174, 2175, 2185, 2189, 2190, 2508, 2509];
	var quantity_limit = 10;
	var _ = {
		'NOT_ENOUGH_QUANTITY': '此活动商品'+quantity_limit+'箱起订',
	};

	// process
	var $search = location.search.substring(1, location.search.Length);
	var location_parser = function( str ) {
		var search = {};
		$.each( str, function(i, v) {
				search[v.split('=')[0]] = v.split('=')[1]
		});
		return search;
	};
	var search = location_parser($search.split('&'));

	if( search.app == 'goods' && 
		$.inArray( parseInt(search.id), id ) != -1 ) {
		$('a[onclick="buy();"], #notice-xnkf_tel').hide();
		$('a[onclick="to_shop();"], #notice-xnkf')
			.css( {'float': 'none', 'width': '100%'} )
			.attr( {'href': 'javascript: void(0);', 'onclick': ''} )
			.bind('click', function() {
				if( parseInt($('.quantity').val()) >= quantity_limit )
					to_shop();
				else 
					alert(_['NOT_ENOUGH_QUANTITY']);
			});
	}
	else if( search.app == 'cart' ) {
		$('.order .pic-s a').each( function() { 
			var $search = $(this).attr('href').split('?')[1];
			var search = {};
			if( $search != undefined )
				search = location_parser($search.split('&'));
			if( search.app == 'goods' && $.inArray( parseInt(search.id), id ) != -1 ) {
				try {
					var input_box = $( '#' + $(this).parent().parent().attr('id').replace('cart', 'input') );
				}
				catch(e) {
					var input_box = $( '#' + $(this).parent().parent().parent().attr('id').replace('cart', 'input') );
				}
				input_box.parent().prev().removeAttr('onclick');
				input_box.parent().next().removeAttr('onclick');
				input_box.attr('readonly', 'readonly');
			};
		});	
	}
});
////////////////////////////////////////////////////////////////////////////////////////////


//js链接到客服系统
function get_customer(store_id){
    $.ajax({
        type : "post",
        url : "index.php?app=customer&act=get_customer_url",
        data : {'store_id' : store_id},
        success : function(data){
            //alert(data);
            location.href=data;
        }

    });
}

// baidu
/*
var _hmt = _hmt || [];
(function() {
	var hm = document.createElement("script");
	hm.src = "//hm.baidu.com/hm.js?a52b8d6b1f6d4c3ba0478bf7896195ea";
	var s = document.getElementsByTagName("script")[0];
	s.parentNode.insertBefore(hm, s);
})();
*/