   
function fmoney(s,n){   
   n = n > 0 && n <= 20 ? n : 2;   
   s = parseFloat((s + "").replace(/[^\d\.-]/g, "")).toFixed(n) + "";   
   var l = s.split(".")[0].split("").reverse(),   
   r = s.split(".")[1];   
   t = "";   
   for(i = 0; i < l.length; i ++ )   
   {   
      t += l[i] + ((i + 1) % 3 == 0 && (i + 1) != l.length ? "," : "");   
   }   
   return t.split("").reverse().join("") + "." + r;   
} 
 //还原格式化数字
function rmoney(s){   
   return parseFloat(s.replace(/[^\d\.-]/g, ""));   
} 

 function ajaxloading(bool,msg){
	 if(bool==true){
		  msg==''||msg==null ? smsg = '<img src="loading.gif" style="height:2.2rem;"/>': smsg = msg;
	
	  if(msg==''||msg==null){
		var leftWindth = $(window).width();
		var selfWidth = $('.pageload').width();
		 selfWidth = leftWindth/2 - selfWidth; 
		 $('.pageload').css({left:selfWidth}).fadeTo(300,0.6);	
	    }else{
		 $('.pageload').css({left:'10%',width:'80%'}).fadeTo(300,0.6);	 
	    }		   
	   }else{
		 $('.pageload').fadeOut(300)
		 $('.pageload').remove();   
	   }
	 }
	
   //文字提示
   var loadTips = function(text,redirect,redtime){
	  ajaxloading(true,text);
	   redtime = redtime!=""&&redtime!=undefined&&redtime!=null?redtime:2000;
		  setTimeout(function(){
			          ajaxloading();
					  if(redirect!=""&&redirect!=undefined&&redirect!=null){
						 window.top.location.href=redirect;
					   }
					  },redtime);   
	 }
	 
   //用户注销
   var logout = function(re){
	   if(confirm('确定要注销吗？')){
		  $.post('/Admin/Login/logout',{type:'true'},function(d){
			if(d==''){
			  if(re=''){
				location.href='#';
			  }else{
				location.reload();  
			 }
		     }else{
			   alert(d);	 
		     }  
		  })   
		}  
	 }

  var winTips = function(msg){ 
   if(msg!=''){
	 $('body').prepend('<div id="common_win"><div class="common_win_bg"></div><div class="common_win_body"><div class="common_win_top"><div class="common_win_close" onclick="commonClose()" title="点击关闭"></div></div><div class="common_win_center">'+msg+'</div><div class="common_win_bottom" onclick="commonClose()" title="点击关闭"></div></div></div>');
	 } 
	 $('#common_win .common_win_bg').css({opacity:0.3})   
	}
	
//关闭提示窗口
 var commonClose = function(){
		  $('#common_win').remove();
		 }
	
  //延时加载新闻采集结果
  //关键字，返回元素[多个]
  //多个递归AJAX返回
  var loadgather = function(res,index){
	  index = index==''?0:index;
	  var len = keywords.length;
	  if(len>0&&len>index&&res){	   
	   setTimeout(function(){
	      $('.'+res).eq(index).load('/gather',{keywords:keywords[index]},function(){
			  loadgather(res,++index);
			 });
		  },300)	
		}  
	}

$(function(){
$('html body').append('<div class="scrolltop"></div>'); 
//返回顶部
$('.scrolltop').click(function(){
	$(this).fadeTo(100,1);
	$('html,body').animate({
		scrollTop:0
	  },400,function(){
		$('.scrolltop').hide();   
	  }) 
  });
  
  $(window).scroll(function(){
	 var top = $(document).scrollTop();
	 if(top>150){
		  $('.scrolltop').fadeTo(1,0.8); 	
	  }else{
	  $('.scrolltop').hide(); 	  
	 }  
   })
//链接加载效果
$('body a').click(function(){
  var href = $(this).attr('href');
	if(href!=''&& href.indexOf('javascript')==-1){
	   loadTips();	
	 }
  })

//返回顶部
$('.fhdb a').click(function(){
	$('html,body').animate({
		scrollTop:0
	  }) 
  })
  
 $('.searchtype').click(function(){
   var $this = $('.searchtype dl');
    if($this.is(':hidden')){
	  $this.fadeTo(200,1);
	  $(this).removeClass('s_list').addClass('s_list2');
	  $('body').prepend('<div class="opacity05bg"></div>');
	   $('.opacity05bg').fadeTo(200,0.5);
	 }else{
	  $this.fadeOut(200);
	  $(this).removeClass('s_list2').addClass('s_list');
	  $('.opacity05bg').remove();
	}
  })
  
  
  //搜索属性
  $('.searchtype dt').click(function(){
	 var index = $('.searchtype dt').index(this);
	 var value =  $('.searchtype dt').eq(index).attr('data-url'); 
	 var text =  $('.searchtype dt').eq(index).text();
	 $('.searchtype span').text(text);
	 $('#searchdata').attr('action',value);
   });
   $('#searchdata input[name=token]').remove();
  
       $(function(){     
          $("img").each(function (i,e){
              var imgsrc = $(e).attr("src");
              $(e).load(function(){
                   $("<p> ok "+ $(e).width()+":"+$(e).height()+"</p>").appendTo("#msgTool");
                }).error(function() {
                   $("<p> error "+ imgsrc  +"</p>").appendTo("#msgTool");
                   $(e).attr("src","su_default.gif");
                }) ;
            });
        }) 
  //百度广告尺寸调整
  $('.guangao-lm #cproIframe_u1830229').css({width:'100%',maxWidth:'640px',height:'auto'});
  $('iframe').css('max-width','640px');
  $('iframe,#cproIframe1Wrap > div,#cproIframe2Wrap > div,.container,#container,.container div,.container a').css({width:'100%',height:'auto'});
  $('iframe:last').css('display','none'); 
 })
 jQuery(function(){
//选项卡滑动切换通用
jQuery(function(){jQuery(".hoverTag .chgBtn").hover(function(){jQuery(this).parent().find(".chgBtn").removeClass("chgCutBtn");jQuery(this).addClass("chgCutBtn");var cutNum=jQuery(this).parent().find(".chgBtn").index(this);jQuery(this).parents(".hoverTag").find(".chgCon").hide();jQuery(this).parents(".hoverTag").find(".chgCon").eq(cutNum).show();})})

//选项卡点击切换通用
jQuery(function(){jQuery(".clickTag .chgBtn").click(function(){jQuery(this).parent().find(".chgBtn").removeClass("chgCutBtn");jQuery(this).addClass("chgCutBtn");var cutNum=jQuery(this).parent().find(".chgBtn").index(this);jQuery(this).parents(".clickTag").find(".chgCon").hide();jQuery(this).parents(".clickTag").find(".chgCon").eq(cutNum).show();})})

function autFun(){
	var mW=$(".mBan").width();
	var mBL=640/200;
	$(".mBan .slideBox .bd").css("height",mW/mBL);
	$(".mBan .slideBox .bd img").css("width",mW);
	$(".mBan .slideBox .bd img").css("height",mW/mBL);
}

function autFun2(){
	var mW2=$(".mBan2").width();
	var mBL2=640/200;
	$(".mBan2 .slideBox .bd").css("height",mW2/mBL2);
	$(".mBan2 .slideBox .bd img").css("width",mW2);
	$(".mBan2 .slideBox .bd img").css("height",mW2/mBL2);
}
setInterval(autFun,1);
setInterval(autFun2,1);

$(".mbom_ul li:last").css("border","none");

})
//屏蔽页面错误
jQuery(window).error(function(){
  return true;
});
jQuery("img").error(function(){
  $(this).hide();
});
 