// JavaScript Document
var line = 0;//定义一个全局的line以用于记录当前显示的是哪一行
var keycontrol = 0; //用于控制当弹出框还未显示或失去焦点时，上下键取值的问题，如果失去焦点，上下键将不取li里的值,0表示不取值，1表示可取值
var vkeyword ="";
var time;

$(document).ready(function(){

	common.staticLog();
	
	$(".allsort").css("height",$(window).height() - 50);
	
	$(".cataItem").on("click",function(){
		$(".cur").removeClass("cur");
		$(".categoryList").hide();
		$(this).addClass("cur");
		var c_index = $(this).index();
		$(".cataDetail").scrollTop();
		$(".cataDetail").children().eq(c_index).show();
	});
			jQuery(document.documentElement).click(function () {
			keycontrol = 0;//将keycontrol的值初始化为0,防止上下键取li里的值
			none();
		});
	});

function loading(state,content){}

var common = {
	  
	GetUrlParam : function(paramName) {
		var oRegex = new RegExp('[\?&]' + paramName + '=([^&]+)', 'i');
		var oMatch = oRegex.exec(window.location.search);
		if (oMatch && oMatch.length > 1)
			return oMatch[1];
		else
			return '';
	},
	// 空格验证
    
    staticLog:function(label){
	    var v_label=label||"";
		var _umy = encodeURIComponent(document.location.href)+v_label+"&&&"+encodeURIComponent(document.referrer||"")+"&&&"+encodeURIComponent(location.search||"")+"&&&"+new Date().getTime();
		 
	},
	currentUrl:function(){
		return window.location.href;
	}, 
	 
}; 
 