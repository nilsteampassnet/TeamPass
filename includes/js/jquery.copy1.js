/**
 * copy plugin
 *
 * Copyright (c) 2007 Yang Shuai (http://yangshuai.googlepages.com)
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 *
 */

/**
 * Use this plugin, you can copy the given text into
 * clipboard in either ie or fx.
 *
 * @example $.copy('some text here');
 * @desc Copy the string 'some text here' into clipboard.
 *
 *
 * @param String t The text that will be copyed to clipboard.
 *
 * @author Yang Shuai/yangshuai@gmail.com
 */
jQuery.copy=function(t)
{
	if(typeof t=='undefined')
	{
		t='';
	}
	d=document;
	if (window.clipboardData)
	{
		window.clipboardData.setData('Text',t);
	}
	else
	{
		var f='flashcopier';
		if(!d.getElementById(f))
		{
			var dd=d.createElement('div');
			dd.id=f;
			d.body.appendChild(dd);
		}
		d.getElementById(f).innerHTML='';
		var i='<embed src="copy.swf" FlashVars="clipboard='+encodeURIComponent(t)+'" width="0" height="0" type="application/x-shockwave-flash"></embed>';
		d.getElementById(f).innerHTML=i;
	}
}
