;(function($){$.fn.simplePassMeter=function(o){var n=this;if(n.length<1){return n;}
o=(o)?o:{};o=audit($.extend({},$.fn.simplePassMeter.defaults,o));n.each(function(){if(this.tagName.toLowerCase()=='input'){setup(this,o);}});}
var audit=function(o){var d=$.fn.simplePassMeter.defaults;o.showOnFocus=!!o.showOnFocus;o.showOnValue=!!o.showOnValue;var c=o.container;c=(c)?$(c):null;o.container=(c&&c.length)?c:null;var rq=o.requirements;if(!rq){rq=d.requirements;}else{for(var k in rq){if(!d.requirements[k]){if(typeof rq[k].value=='undefined'||typeof rq[k].message!='string'||(typeof rq[k].regex!='string'&&!$.isFunction(rq[k].callback))){rq[k]=null;continue;}else{continue;}}
if(typeof rq[k].value=='undefined'){rq[k].value=d.requirements[k].value;}
if(typeof rq[k].message!='string'){rq[k].message=d.requirements[k].message;}
if(typeof rq[k].regex!='string'&&d.requirements[k].regex){rq[k].regex=d.requirements[k].regex;}
if(!$.isFunction(rq[k].callback)&&d.requirements[k].callback){rq[k].callback=d.requirements[k].callback;}
if(k=='minLength'){if(!Number(rq[k].value)||rq[k].value<1){rq[k].value=d.requirements[k].value;}}}}
if(rq['matchField']){$(rq['matchField'].value).bind('keyup.simplePassMeterMatch',function(){$(this).attr('active','true').unbind('keyup.simplePassMeterMatch');});}
if(!o.ratings||!o.ratings.length){o.ratings=d.ratings;}else{var ps=0;for(var i=0,l=o.ratings.length;i<l;++i){if((!Number(o.ratings[i].minScore)&&o.ratings[i].minScore!==0)||o.ratings[i].minScore<ps){o.ratings=d.ratings;break;}
ps=o.ratings[i].minScore;if(!o.ratings[i].className){o.ratings[i].className='good';}
if(!o.ratings[i].text){o.ratings[i].text='Good';}}}
return o;}
function setup(n,o){n=$(n);if(n.attr('id').length<1){n.attr('id','simplePassMeter_'+(++$.fn.simplePassMeter.uid));}
n.addClass('simplePassMeterInput');var base=n.attr('id');$('body').append("<div id='"+base+"_simplePassMeter' class='simplePassMeter' aria-controlled>"+"<p><span class='simplePassMeterIcon'></span><span class='simplePassMeterText'></span></p>"+"<div class='simplePassMeterBar'><div class='simplePassMeterProgress'></div></div>"+"</div>");n.attr('aria-controls',base+'_simplePassMeter');var b=$('#'+base+'_simplePassMeter').css('padding-bottom','8px');if(o.container){o.container.append(b);b.css('position','relative');}else{b.css('position','absolute');reposition(n,b,o);}
var m=b.find('.simplePassMeterBar').css({'position':'absolute','bottom':'0.15em','left':'5px','height':'5px','width':'95%'});var mp=m.find('.simplePassMeterProgress').css({'height':'5px','width':'0%'});n.bind('keyup.simplePassMeter',function(){n.attr('active','true');testPass(n,b,o);});n.bind('focus.simplePassMeter',function(){n.attr('active','true');testPass(n,b,o);});if(o.showOnFocus){b.hide();n.bind('focus.simplePassMeter',function(){b.show();}).bind('blur.simplePassMeter',function(){b.hide();});}
if(o.showOnValue){n.bind('keyup.simplePassMeter',function(){if(this.value.length<1){b.hide();}else{b.show();}});n.trigger('keyup.simplePassMeter');}
$.each(o.requirements,function(key,req){if(/.+Field$/.test(key)){var f=$(req.value);if(f.length==1){f.bind('keyup.simplePassMeter',function(){testPass(n,b,o);});}}});if(!o.container){$(window).resize(function(){reposition(n,b,o);});}
reset(b,o);}
function reposition(n,box,o){var t,b,r,l,ielr;t=b=l=r='auto';ielr=(document.all)?2:0;var pos=n.offset();var pl=pos.left;var pt=pos.top;if(o.location=='t'){l=pl+'px';t=(pt-box.height()-10-o.offset)+'px';}else if(o.location=='b'){l=pl+'px';t=(pt+n.height()+7+o.offset)+'px';}else if(o.location=='l'){r=($('body').width()-pl+o.offset)+'px';t=pt+'px';}else{l=(pl+n.width()+4+ielr+o.offset)+'px';t=pt+'px';}
box.css({'top':t,'right':r,'bottom':b,'left':l});}
function countContain(strPassword,strCheck)
{var nCount=0;for(i=0;i<strPassword.length;i++)
{if(strCheck.indexOf(strPassword.charAt(i))>-1)
{nCount++;}}
return nCount;}
var m_strUpperCase="ABCDEFGHIJKLMNOPQRSTUVWXYZ";var m_strLowerCase="abcdefghijklmnopqrstuvwxyz";var m_strNumber="0123456789";var m_strCharacters="!@#$%^&*?_~.,:!";function checkPassword(strPassword)
{var nScore=0;if(strPassword.length<5)
{nScore+=5;}
else if(strPassword.length>4&&strPassword.length<8)
{nScore+=10;}
else if(strPassword.length>7)
{nScore+=25;}
var nUpperCount=countContain(strPassword,m_strUpperCase);var nLowerCount=countContain(strPassword,m_strLowerCase);var nLowerUpperCount=nUpperCount+nLowerCount;if(nUpperCount==0&&nLowerCount!=0)
{nScore+=10;}
else if(nUpperCount!=0&&nLowerCount!=0)
{nScore+=20;}
var nNumberCount=countContain(strPassword,m_strNumber);if(nNumberCount==1)
{nScore+=10;}
if(nNumberCount>=3)
{nScore+=20;}
var nCharacterCount=countContain(strPassword,m_strCharacters);if(nCharacterCount==1)
{nScore+=10;}
if(nCharacterCount>1)
{nScore+=25;}
if(nNumberCount!=0&&nLowerUpperCount!=0)
{nScore+=2;}
if(nNumberCount!=0&&nLowerUpperCount!=0&&nCharacterCount!=0)
{nScore+=3;}
if(nNumberCount!=0&&nUpperCount!=0&&nLowerCount!=0&&nCharacterCount!=0)
{nScore+=5;}
return nScore;}
function testPass(n,b,o){var p=n.val();if(p.length<1){reset(b,o);n.trigger('score.simplePassMeter',[0]);return;}
var m='';s=checkPassword(p);s=Math.min(Math.round(s),100);setMeterUI(b,s,o,(m.length>0)?m:null);n.trigger('score.simplePassMeter',[s]);};function reset(b,o){var c='';for(var i=0,l=o.ratings.length;i<l;++i){c+=o.ratings[i].className+' ';}
b.removeClass(c).find('.simplePassMeterProgress').css('width','0%').end().find('.simplePassMeterText').text(o.defaultText);}
function setMeterUI(b,pct,o,m){pct=(Number(pct))?pct:0;pct=Math.min(Math.max(pct,0),100);m=(typeof m=='string')?m:null;b.find('.simplePassMeterProgress').css('width',pct+'%');var c='';var r=0;for(var i=0,l=o.ratings.length;i<l;++i){c+=o.ratings[i].className+' ';if(pct>=o.ratings[i].minScore){r=i;}}
b.removeClass(c);if(!m){b.addClass(o.ratings[r].className);}else{b.addClass(o.ratings[0].className);}
b.find('.simplePassMeterText').html(((m)?m:o.ratings[r].text));}
$.fn.simplePassMeter.uid=0;})(jQuery);