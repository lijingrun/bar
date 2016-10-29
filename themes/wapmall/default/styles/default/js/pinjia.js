// JavaScript Document 
 
 $(function() {
      $.fn.raty.defaults.path = 'images/';
      $('#function-demo').raty({
	  	number: 5,
		score: 5,	
		targetType: 'hint',
        path      : 'images/',
		hints     : ['差得离谱','不满意','一般','满意','非常满意'],
        cancelOff : 'cancel-off-big.png',
        cancelOn  : 'cancel-on-big.png',
        size      : 28,
        starHalf  : 'star-half-big.png',
        starOff   : 'star-off-big.png',
        starOn    : 'star-on-big.png',
        target    : '#function-hint',
        cancel    : false,
        targetKeep: true,
		targetText: '非常满意',

        click: function(score, evt) {          
        }
      });      
	 
    });  
	
    $(function() {
      $.fn.raty.defaults.path = 'images/';
      $('#function-demo2').raty({
	  	number: 5,
		score: 5,
		targetType: 'hint',
        path      : 'images/',
		hints     : ['差得离谱','不满意','一般','满意','非常满意'],
        cancelOff : 'cancel-off-big.png',
        cancelOn  : 'cancel-on-big.png',
        size      : 28,
        starHalf  : 'star-half-big.png',
        starOff   : 'star-off-big.png',
        starOn    : 'star-on-big.png',
        target    : '#function-hint2',
        cancel    : false,
        targetKeep: true,
		targetText: '非常满意',

        click: function(score, evt) {
          
        }
      });      
	 
    });  
	
    $(function() {
      $.fn.raty.defaults.path = 'images/';
      $('#function-demo3').raty({
	  	number: 5,
		score: 5,
		targetType: 'hint',
        path      : 'images/',
		hints     : ['差得离谱','不满意','一般','满意','非常满意'],
        cancelOff : 'cancel-off-big.png',
        cancelOn  : 'cancel-on-big.png',
        size      : 28,
        starHalf  : 'star-half-big.png',
        starOff   : 'star-off-big.png',
        starOn    : 'star-on-big.png',
        target    : '#function-hint3',
        cancel    : false,
        targetKeep: true,
		targetText: '非常满意',

        click: function(score, evt) {
          
        }
      });      
	 
    });  