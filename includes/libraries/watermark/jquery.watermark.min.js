/*
 * jQuery Watermark plugin
 * Version 1.2 (7-DEC-2010)
 * @requires jQuery v1.3 or later
 *
 * Examples at: http://mario.ec/static/jq-watermark/
 * Copyright (c) 2010 Mario Estrada
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/mit-license.php
 *
 */
(function(a){var k=a.browser.msie&&a.browser.version<8;a.watermarker=function(){};a.extend(a.watermarker,{defaults:{color:"#999",left:0,top:0,fallback:false,animDuration:300,minOpacity:0.6},setDefaults:function(e){a.extend(a.watermarker.defaults,e)},checkVal:function(e,c){e.length==0?a(c).show():a(c).hide();return e.length>0},html5_support:function(){return"placeholder"in document.createElement("input")}});a.fn.watermark=function(e,c){var i;c=a.extend(a.watermarker.defaults,c);i=this.filter("textarea, input:not(:checkbox,:radio,:file,:submit,:reset)");
if(!(c.fallback&&a.watermarker.html5_support())){i.each(function(){var b,f,j,g,d,h=0;b=a(this);if(b.attr("data-jq-watermark")!="processed"){f=b.attr("placeholder")!=undefined&&b.attr("placeholder")!=""?"placeholder":"title";j=e===undefined||e===""?a(this).attr(f):e;g=a('<span class="watermark_container"></span>');d=a('<span class="watermark">'+j+"</span>");f=="placeholder"&&b.removeAttr("placeholder");g.css({display:"inline-block",position:"relative"});k&&g.css({zoom:1,display:"inline"});b.wrap(g).attr("data-jq-watermark",
"processed");if(this.nodeName.toLowerCase()=="textarea"){e_height=b.css("line-height");e_height=e_height==="normal"?parseInt(b.css("font-size")):e_height;h=b.css("padding-top")!="auto"?parseInt(b.css("padding-top")):0}else{e_height=b.outerHeight();if(e_height<=0){e_height=b.css("padding-top")!="auto"?parseInt(b.css("padding-top")):0;e_height+=b.css("padding-bottom")!="auto"?parseInt(b.css("padding-bottom")):0;e_height+=b.css("height")!="auto"?parseInt(b.css("height")):0}}h+=b.css("margin-top")!="auto"?
parseInt(b.css("margin-top")):0;f=b.css("margin-left")!="auto"?parseInt(b.css("margin-left")):0;f+=b.css("padding-left")!="auto"?parseInt(b.css("padding-left")):0;d.css({position:"absolute",display:"block",fontFamily:b.css("font-family"),fontSize:b.css("font-size"),color:c.color,left:4+c.left+f,top:c.top+h,height:e_height,lineHeight:e_height+"px",textAlign:"left",pointerEvents:"none"}).data("jq_watermark_element",b);a.watermarker.checkVal(b.val(),d);d.click(function(){a(a(this).data("jq_watermark_element")).trigger("focus")});
b.before(d).bind("focus.jq_watermark",function(){a.watermarker.checkVal(a(this).val(),d)||d.stop().fadeTo(c.animDuration,c.minOpacity)}).bind("blur.jq_watermark change.jq_watermark",function(){a.watermarker.checkVal(a(this).val(),d)||d.stop().fadeTo(c.animDuration,1)}).bind("keydown.jq_watermark",function(){a(d).hide()}).bind("keyup.jq_watermark",function(){a.watermarker.checkVal(a(this).val(),d)})}});return this}};a(document).ready(function(){a(".jq_watermark").watermark()})})(jQuery);