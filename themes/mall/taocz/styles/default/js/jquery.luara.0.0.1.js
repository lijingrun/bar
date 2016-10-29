/**
 * luara
 * Jquery图片切换插件 基于Jquery1.8.3
 * Version 0.0.1 2014-8-21
 * By Mr.肥鹅 Practice makes perfect.
 */
jQuery.fn.extend({
    /*
     配置器
     config = {
        width : '容器宽度',
        height :  '容器高度',
        deriction : '图片滑动方向' || top || left
        interval : '图片切换间隔时间' 默认3000毫秒
        speed : '动画时长' 指定毫秒数
        selected : '导航器选中样式'
     }
     */
    luara : function(config){
        var
            //容器及元素相关变量
            $warpElement = $(this).eq(0),
            $containerElement = $(this).find('ul').eq(0),
            $containerUlLiElements = $containerElement.find('li'),
            $containerOlLiElements = $(this).find('ol').eq(0).find('li'),
            $imgElements = $warpElement.find('img'),
            imgCount = $imgElements.length,

            //配置相关变量
            config = config || {},
            width = config.width || getImageSize().width,
            height = config.height || getImageSize().height,
            deriction = config.deriction || '',
            className = 'luara-'+ deriction,
            interval = (config.interval>0?config.interval:-config.interval) || 3000,
            speed = getSpeed(),
            selected = config.selected,

            //循环控制相关变量
            index = 0,
            isStop = false,
            timer,

            //函数相关变量
            animateHandler;


        //初始化外围容器大小
        //初始化图片容器大小
        //初始化图片大小
        //初始化导航器样式
        $warpElement.width(width).height(height).addClass(className);
        $containerElement.width(getContainerWidth(deriction)).height(height);
        $containerUlLiElements.width(width).height(height);
        $containerOlLiElements.eq(0).addClass(selected);

        //初始函数销毁
        (function(){
            getContainerWidth = null;
            getImageSize = null;
            getSpeed = null;
        })();

        //根据指定的方向设置图片容器的宽度
        //未指定该值时，设置为第一张图片宽度
        function getContainerWidth(){
            var temp;
            switch (deriction){
                case 'top':
                    temp = width;
                    break;
                case 'left':
                    temp = width * imgCount;
                    break;
                default :
                    temp = width;
                    break;
            }
            return temp;
        }

        //当没有指定外围包装器的宽度时
        //获取第一张图片的宽度值
        function getImageSize(){
            var $img = $warpElement.find('img').eq(0),
                size = {};
            size.width = $img.width();
            size.height = $img.height();
            return size;
        }

        //获得动画时长数
        //默认动画时长为切换间隔时长一半
        function getSpeed(speed){
            var speed = speed || config.speed || (interval/6);
            if(speed>interval){
                speed = interval;
            }else if(speed<interval&&speed<0){
                speed = arguments.callee(-speed);
            }
            return speed;
        }

        //根据指定值返回图片切换效果函数
        //默认返回的函数为淡进淡出切换
        animateHandler = (function(){
            switch (deriction){
                case  'top':
                    return function(){
                        $containerElement.animate({top:-height*index+'px'},speed);
                    };
                case 'left':
                    return function(){
                        $containerElement.animate({left:-width*index+'px'},speed);
                    };
                default :
                    return function(){
                        $containerUlLiElements.hide().eq(index).fadeIn(speed);
                    }
            }
        })();

        //给导航器绑定click事件
        $containerOlLiElements.mouseover(function(){
             $containerOlLiElements.eq(index).removeClass(selected);
             index = $containerOlLiElements.index($(this));
             $(this).addClass(selected);
             animateHandler();
        });

        //给外围容器绑定鼠标进入事件
        //给外围容器绑定鼠标离开事件
        $warpElement.mouseenter(function(){
            clearTimeout(timer);
        }).mouseleave(function(){
            loop();
        });

        //循环体函数
        function loop(){
            timer = setTimeout(function(){
                index++;
                $containerOlLiElements.eq(index-1).removeClass(selected);
                if(index==imgCount){
                    index = 0;
                }
                animateHandler();
                $containerOlLiElements.eq(index).addClass(selected);
                loop();
            },interval);
        };
        loop();
    }
});