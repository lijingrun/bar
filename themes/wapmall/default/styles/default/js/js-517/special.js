// JavaScript Document
$(function(){
	var offsetTop = $(".nav").offset().top;
	tabsFix(offsetTop);
});

function tabsFix(offsetTop){
	$(window).scroll(function() {
	   	if($(window).scrollTop() > offsetTop)    
		{
			$(".nav").addClass("current");	
			$(".nav span").click(function(){
				$(this).parents(".nav").css("display","none");
				$(".fix_nav").css("display","block");
				$(".fix_nav").removeClass("show_1");
				$("#opacity_body").css("display","block");
			})
		}
		else
		{
			$(".nav").removeClass("current");	
			$(".nav span").click(function(){
				$(this).parents(".nav").css("display","none");
				$(".fix_nav").css("display","block");
				$(".fix_nav").addClass("show_1");
				$("#opacity_body").css("display","block");
	        })
		}
    });	
	
	}
$(window).scroll(function(){
	var currLayer = 1;
	//为页面添加页面滚动监听事件
	var wst =  $(window).scrollTop() //滚动条距离顶端值
	for (i=1; i<=$("ul.list1 li").length; i++){             //加循环
	    if($("#layer"+i).offset().top-200<=wst){ //判断滚动条位置
			$('.nav li a').removeClass("current"); //清除c类
			$("#a"+i).addClass("current");	//给当前导航加c类
			currLayer=i;
		}
	}
	var divLeft = 0;
	for(var i = 1;i<=$('ul.list1 li').length;i++){
		if(i<currLayer){
			divLeft = divLeft+$('#a'+i).width()+60;
		}
	}
	$(".scrollArea").animate({scrollLeft:divLeft},10);
	
})
var c,n;
jQuery(function($) {
	n = $(".nav li").size();
	$(".nav ul").css("width",500 * n);
    $(".slide li a").click(function(){
		$(".nav").addClass("current");
		c=$(this).parent().index();
	    $($(".nav li")[c]).siblings().children().removeClass("current");
        $("html, body").animate({    
            scrollTop: $($(this).attr("href")).offset().top - 166 + "px"
        }, 500);
        return false; 
	});      
});

$(function(){
	var offsetTop = $(".nav").offset().top;
	if($(window).scrollTop() < offsetTop){
			$(".nav span").click(function(){
				$(this).parents(".nav").css("display","none");
				$(".fix_nav").css("display","block");
				$(".fix_nav").addClass("show_1");
				$("#opacity_body").css("display","block");
	})
	}else{
		$(".nav span").click(function(){
		$(this).parents(".nav").css("display","none");
		$(".fix_nav").css("display","none");
		$(".fix_nav").removeClass("show_1");
		$("#opacity_body").css("display","block");
	})
	}
	$(document).bind("click",function(e){
		var target  = $(e.target);
		if(target.closest(".nav span").length == 0){
		  $(".fix_nav").css("display","none");
		  $(".nav").css("display","block");
		  $("#opacity_body").css("display","none");
		}
	})
	
	})
$(function(){
	$(".slide_nav span").click(function(){
		$(".nav").css("display","block");
		$(".fix_nav").css("display","none");
		$("#opacity_body").css("display","none");
		})
	$(".fix_nav li a").click(function(){
		$(".nav").css("display","block");
		$(".nav").addClass("current");
		$(".fix_nav").css("display","none");
		$("#opacity_body").css("display","none");
		})
	})