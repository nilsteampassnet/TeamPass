/**
 * gauge.js 1.32 (12-May-2009) (c) by Christian Effenberger 
 * All Rights Reserved. Source: gauge.netzgesta.de
 * Distributed under Netzgestade Software License Agreement.
 * This license permits free of charge use on non-commercial 
 * and private web sites only under special conditions. 
 * Read more at... http://www.netzgesta.de/cvi/LICENSE.txt
 * syntax:
	
	gauge.defaultWidth		= 400;		//INT 48|8-n (px canvas|var width) 
	gauge.defaultHeight		= 30;		//INT 12|48-n (px canvas|var height) (should be dividable by 3)
	gauge.defaultName		= null;		//STR (canvas|var name|id)
	gauge.defaultVertical	= false;	//BOOLEAN (vertical orientation)
	gauge.defaultReflex 	= 0.5;		//FLT 0.0-1.0 (transparency of the reflection)
	gauge.defaultRadius 	= 1.0;		//FLT 0.0-1.0 (radius of the corners)
	gauge.defaultOpacity 	= 0.25;		//FLT 0.0-1.0 (opacity - only visible with limit==true)
	gauge.defaultLimit		= false;	//BOOLEAN (limited value mode)
	gauge.defaultGradient	= false;	//BOOLEAN (color gradient for limit==true and values length==2)
	gauge.defaultNoscale	= false;	//BOOLEAN (no vertical tick marks)
	gauge.defaultBusy		= false;	//BOOLEAN (no pulsating busy bar)
	gauge.defaultPulse		= 100;		//INT 30-1000 (pulsation speed in milliseconds)
	gauge.defaultScale		= null;		//INT 2-n null==auto (number of scale areas)
	gauge.defaultValues		= null;		//OBJ (array of number values)
	gauge.defaultEmpty		= '#cccccc';//STR (empty area color)
	gauge.defaultColors		= ['#3765D9','#9EDE7C','#9E42EE','#EC7612','#00aaaa','#cc0000','#aaaa00','#008000']; //OBJ (array of colors)
	
STR=gauge.add( object, options ); //RETURNS canvas|var name|id 
STR=gauge.add( object, { width:value, height:value, name:value, vertical:value, limit:value, busy:value, pulse:value, reflex:value, radius:value, opacity: value, scale:value, noscale:value, gradient:value, empty:value, values:value, colors:value } );
	gauge.modify( canvas|var, options );
	gauge.modify( canvas|var, { limit:value, busy:value, pulse:value, reflex:value, radius:value, opacity: value, scale:value, noscale:value, gradient:value, empty:value, values:value, colors:value } );
	gauge.remove( canvas|var );
FLT=gauge.version;
STR=gauge.released;
	
 *
**/

var gauge = { version : 1.32, released : '2009-05-12 17:46:00', 
	defaultWidth : 400, defaultHeight : 30, defaultReflex : 0.5, defaultOpacity : 0.25, defaultPulse : 100,
	defaultLimit : false, defaultNoscale : false, defaultGradient : false, defaultVertical : false, defaultBusy : false, 
	defaultRadius : 1.0, defaultScale : null, defaultEmpty : '#cccccc', defaultName : null, defaultValues : null,
	defaultColors : ['#3765d9','#9ede7c','#9e42ee','#ec7612','#00aaaa','#cc0000','#aaaa00','#008000'],
	add : function(object, options) {
		function uniqueID() {var v=Date.parse(new Date())+Math.floor(Math.random()*100000000000); return v.toString(16);}
		var self,vo,width,height,tmp,id,defopts={"width":gauge.defaultWidth,"height":gauge.defaultHeight,"pulse":gauge.defaultPulse,"name":gauge.defaultName,"limit":gauge.defaultLimit,"vertical":gauge.defaultVertical,"noscale":gauge.defaultNoscale,"busy":gauge.defaultBusy,"gradient":gauge.defaultGradient,"scale":gauge.defaultScale,"reflex":gauge.defaultReflex,"opacity":gauge.defaultOpacity,"empty":gauge.defaultEmpty,"radius":gauge.defaultRadius,"colors":gauge.defaultColors,"values":gauge.defaultValues};
		if(options) {for(var i in defopts) {if(!options[i]) {options[i]=defopts[i];}}}else {options=defopts;} vo=(typeof options['vertical']==='boolean'?options['vertical']:self.options['vertical']);
		width=Math.max((typeof options['width']==='number'?options['width']:gauge.defaultWidth),(vo?8:48)); height=Math.max((typeof options['height']==='number'?options['height']:gauge.defaultHeight),(vo?48:12));
		if(!vo&&width<(height*3)) {width=(height*3);} if(vo&&height<(width*3)) {height=(width*3);} id=(typeof options['name']==='string'?options['name']:gauge.defaultName);
		object.id=(object.id!='undefined'?object.id:uniqueID()); tmp=(id==''||id==null?object.id+'_gauge':id); 
		if(!document.getElementById(tmp)) {
			try {
				if(document.all&&document.namespaces&&!window.opera) {
					if(document.namespaces['v']==null) {
						var e=["shape","shapetype","group","background","path","formulas","handles","fill","stroke","shadow","textbox","textpath","imagedata","line","polyline","curve","roundrect","oval","rect","arc","image"],s=document.createStyleSheet(); 
						for(var i=0; i<e.length; i++) {s.addRule("v\\:"+e[i],"behavior: url(#default#VML);");} document.namespaces.add("v","urn:schemas-microsoft-com:vml");
					} 
					var dpl=(object.currentStyle.display.toLowerCase()=='block')?'block':'inline-block';        
					var self=document.createElement(['<var style="zoom:1;display:'+dpl+';width:'+width+'px;height:'+height+'px;padding:0px;margin:0px;">'].join(''));
					var flt=object.currentStyle.styleFloat.toLowerCase(); self.dpl=(flt=='left'||flt=='right')?'inline':'inline-block';
				}else {
					var self=document.createElement('canvas');
					self.wk4=navigator.appVersion.indexOf('WebKit')!=-1&&!document.defaultCharset?1:0;
					self.ge8=navigator.userAgent.indexOf('Gecko')>-1&&window.updateCommands&&!window.external?1:0;
				}
				if(self||self.getContext("2d")) {
					self.options=options; self.id=id!=null?id:object.id+'_gauge';
					self.style.padding='0px'; self.style.margin='0px'; self.style.border='none'; 
					self.style.height=height+'px'; self.style.width=width+'px'; self.height=height; self.width=width;
					object.innerHTML=''; object.appendChild(self); gauge.modify(self, options);
					return self.id;
				}else {return false;}
			}catch(e) {return false;}
		}return false;
	},
	
	modify : function(self, options) {
		function hex2rgb(val) {function h2d(v){return(Math.max(0,Math.min(parseInt(v,16),255)));} return h2d(val.substr(1,2))+','+h2d(val.substr(3,2))+','+h2d(val.substr(5,2));};
		function P(i) {var p=self.cl.concat(gauge.defaultColors),k=p.length-1,t=i; if(t>k) {t=(i%k)-1;} return p[t];};
		function F(a,z,v,q) {var r,g,b,x,y,l=1-v;
			function h2d(h){return(Math.max(0,Math.min(parseInt(h,16),255)));};
			function d2h(v) {v=Math.round(Math.min(Math.max(0,v),255)); return("0123456789ABCDEF".charAt((v-v%16)/16)+"0123456789ABCDEF".charAt(v%16));}
			x=h2d(a.substr(1,2)); y=h2d(z.substr(1,2)); r=Math.max(0,Math.min(255,parseInt((x*l)+(y*v))));
			x=h2d(a.substr(3,2)); y=h2d(z.substr(3,2)); g=Math.max(0,Math.min(255,parseInt((x*l)+(y*v))));
			x=h2d(a.substr(5,2)); y=h2d(z.substr(5,2)); b=Math.max(0,Math.min(255,parseInt((x*l)+(y*v))));
			if(!q) {return('#'+d2h(r)+d2h(g)+d2h(b));}else {return(r+','+g+','+b);}
		};
		if(self) {var i,q,g,c,n,a,z,l,t=0,j=0,m=0,s=0,e=0,y=0,x=0,v=(self.width<self.height?1:0),r=parseInt(v?self.width/2:self.height/3),h=v?self.height-r:r*2,w=self.width,b=parseInt((v?w:h)*1.1),k=Math.round(((v?h:w)-(2*r))/b);
			try {self.cc=(typeof options['empty']==='string'?options['empty']:self.options['empty']);
				self.cc=self.cc.match(/^#[0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]$/i)?self.cc:gauge.defaultEmpty; self.options['empty']=self.cc; self.dc=hex2rgb(self.cc);
				self.ty=Math.min(Math.max((typeof options['reflex']==='number'?options['reflex']:self.options['reflex']),0.001),1.0); self.options['reflex']=self.ty;
				self.op=Math.min(Math.max((typeof options['opacity']==='number'?options['opacity']:self.options['opacity']),0.001),1.0); self.options['opacity']=self.op;
				self.cr=Math.min(Math.max((typeof options['radius']==='number'?options['radius']:self.options['radius']),0.001),1.0); self.options['radius']=self.cr;
				self.pl=Math.min(Math.max((typeof options['pulse']==='number'?options['pulse']:self.options['pulse']),30),1000); self.options['pulse']=self.pl;
				self.cl=(typeof options['colors']==='object'?options['colors']:self.options['colors']); self.options['colors']=self.cl; 
				self.vl=(typeof options['values']==='object'?options['values']:self.options['values']); self.options['values']=self.vl;
				self.gr=(typeof options['gradient']==='boolean'?options['gradient']:self.options['gradient']); self.options['gradient']=self.gr;
				self.lv=(typeof options['limit']==='boolean'?options['limit']:self.options['limit']); self.options['limit']=self.lv;
				self.ns=(typeof options['noscale']==='boolean'?options['noscale']:self.options['noscale']); self.options['noscale']=self.ns; self.vo=(self.width<self.height?1:0);
				self.bu=(typeof options['busy']==='boolean'?options['busy']:self.options['busy']); self.options['busy']=self.bu; 
				if(options['scale']!=null) {self.sn=Math.min(Math.max((typeof options['scale']==='number'?options['scale']:self.options['scale']),2),parseInt((v?h:w)/8)); self.options['scale']=self.sn;} 
				if(self.sn!=null) {b=parseInt((v?h:w)/self.sn); k=Math.round((v?h:w)/b)-1;} q=parseInt(r*self.cr); if(self.timer) {window.clearInterval(self.timer);}
				if(document.all&&document.namespaces&&!window.opera&&self.tagName.toUpperCase()=="VAR") {
					var head,foot,fyll,flex,shadb,shadd,scalb='',scald='',j='m 0,0 l 0,'+parseInt((r-q)*100)+' qy '+parseInt(q*100)+','+parseInt(r*100)+' l '+parseInt((w-q)*100)+','+parseInt(r*100)+' qx '+parseInt(w*100)+','+parseInt((r-q)*100)+' l '+parseInt(w*100)+',0 x e';
					head='<v:group style="zoom:1;display:'+self.dpl+';margin:0px;padding:0px;position:relative;width:'+self.width+'px;height:'+self.height+'px;" coordsize="'+self.width+','+self.height+'"><v:rect filled="f" stroked="f" strokeweight="0" style="zoom:1;display:block;position:absolute;top:0px;left:0px;margin:0px;padding:0px;width:'+self.width+'px;height:'+self.height+'px;"></v:rect>'; foot='</v:group>';
					fyll='<v:roundrect id="'+(self.id+'_fyll')+'" arcsize="'+(self.cr*0.5)+'" style="width:'+w+'px;height:'+h+'px;left:0px;top:0px;position:absolute;display:block;margin:0px;padding:0px;" filled="f" stroked="f" strokeweight="0">';
					flex='<v:shape id="'+(self.id+'_flex')+'" coordorigin="0,0" coordsize="'+(w*100)+','+(r*100)+'" path="'+j+'" style="filter:Alpha(opacity='+(100-(100*self.ty))+',finishOpacity=0,startX=0,finishX=0,startY=0,finishY=100,style=1); width:'+w+'px;height:'+r+'px;left:0px;top:'+h+'px;position:absolute;display:block;margin:0px;padding:0px;rotation:180;" filled="f" stroked="f" strokeweight="0">';
					if(v) {j='m '+parseInt(r*100)+',0 l '+parseInt(q*100)+',0 qx 0,'+parseInt(q*100)+' l 0,'+parseInt((h-q)*100)+' qy '+parseInt(q*100)+','+parseInt(h*100)+' l '+parseInt(r*100)+','+parseInt(h*100)+' x e';
						shadb='<v:shape coordorigin="0,0" coordsize="'+(r*100)+','+(h*100)+'" path="'+j+'" style="width:'+r+'px;height:'+h+'px;left:0px;top:0px;position:absolute;display:block;margin:0px;padding:0px;rotation:0;" filled="t" stroked="f" strokeweight="0"><v:fill color="white" opacity="0.5" color2="white" o:opacity2="0.0" type="gradient" method="linear" angle="270" /></v:shape>';
						shadd='<v:shape coordorigin="0,0" coordsize="'+(r*100)+','+(h*100)+'" path="'+j+'" style="width:'+r+'px;height:'+h+'px;left:'+r+'px;top:0px;position:absolute;display:block;margin:0px;padding:0px;rotation:180;" filled="t" stroked="f" strokeweight="0"><v:fill color="black" opacity="0.33" color2="black" o:opacity2="0.0" type="gradient" method="sigma" angle="90" /></v:shape>';
					}else {shadb='<v:shape coordorigin="0,0" coordsize="'+(w*100)+','+(r*100)+'" path="'+j+'" style="width:'+w+'px;height:'+r+'px;left:0px;top:0px;position:absolute;display:block;margin:0px;padding:0px;rotation:180;" filled="t" stroked="f" strokeweight="0"><v:fill color="white" opacity="0.5" color2="white" o:opacity2="0.0" type="gradient" method="linear" angle="180" /></v:shape>';
						shadd='<v:shape coordorigin="0,0" coordsize="'+(w*100)+','+(r*100)+'" path="'+j+'" style="width:'+w+'px;height:'+r+'px;left:0px;top:'+r+'px;position:absolute;display:block;margin:0px;padding:0px;rotation:0;" filled="t" stroked="f" strokeweight="0"><v:fill color="black" opacity="0.33" color2="black" o:opacity2="0.0" type="gradient" method="sigma" angle="0" /></v:shape>';
					}if(self.vl&& !self.bu) {l=self.vl.length-(self.lv&&self.vl.length>=2?1:0); for(i=0; i<l; i++) {m+=Math.abs(self.vl[i]);} m=Math.max(m,Math.abs(self.vl[self.vl.length-1])); a=P(0); z=P(l-1); e=0; n='';
						if(self.lv&&self.gr&&l==1) {z=self.cc; c=P(1); s=100*(Math.abs(self.vl[0])/m); c=F(a,c,s/100); n='0% '+a+', '+s+'% '+c+', '+s+'% '+z;}else {
							for(i=0; i<l; i++) {c=P(i); s=100*(Math.abs(self.vl[i])/m); if(i==0&&i==(l-1)&&self.lv) {z=self.cc; n+=s+'% '+c+', '+s+'% '+z;}else if(i==0) {n+=s+'% '+c+', ';}else if(i==(l-1)&&self.lv) {z=self.cc; n+=e+'% '+c+', '+(e+s)+'% '+c+', '+(e+s)+'% '+z;}else if(i==(l-1)) {n+=e+'% '+c;}else {n+=e+'% '+c+', '+(e+s)+'% '+c+', ';}e+=s;}
						}fyll+='<v:fill color="'+a+'" color2="'+z+'" type="gradient" colors="'+n+'" method="linear" angle="'+(v?0:270)+'" on="t" /></v:roundrect>';
						if(v) {flex+='<v:fill color="'+(self.lv&&Math.abs(self.vl[0])<=0?self.cc:P(0))+'" on="t" /></v:shape>';}else{flex+='<v:fill color="'+a+'" color2="'+z+'" type="gradient" colors="'+n+'" method="linear" angle="'+(v?0:270)+'" on="t" /></v:shape>';}
					}else {c=self.bu?P(0):self.cc; fyll+='<v:fill color="'+c+'" on="t" /></v:roundrect>'; flex+='<v:fill color="'+c+'" on="t" /></v:shape>';}
					if(!self.ns) {a=''; z=''; 
						if(v) {for(i=0; i<k; i++) {a+=' m 100,'+parseInt((h*100)-(b*100)-(i*(b*100))+100)+' l '+parseInt((w*100)-100)+','+parseInt((h*100)-(b*100)-(i*(b*100))+100)+' e'; z+=' m 100,'+parseInt((h*100)-(b*100)-(i*(b*100)))+' l '+parseInt((w*100)-100)+','+parseInt((h*100)-(b*100)-(i*(b*100)))+' e';}}
						else {for(i=0; i<k; i++) {a+=' m '+parseInt((b*100)+(i*(b*100))+100)+',100 l '+parseInt((b*100)+(i*(b*100))+100)+','+parseInt((h*100)-100)+' e'; z+=' m '+parseInt((b*100)+(i*(b*100)))+',100 l '+parseInt((b*100)+(i*(b*100)))+','+parseInt((h*100)-100)+' e';}}
						scalb='<v:shape coordorigin="0,0" coordsize="'+(w*100)+','+(h*100)+'" path="'+a+'" style="width:'+w+'px;height:'+h+'px;left:0px;top:0px;position:absolute;display:block;margin:0px;padding:0px;" filled="f" stroked="t"><v:stroke color="white" opacity="0.33" weight="0.1pt" miterlimit="0" endcap="round" /></v:shape>';
						scald='<v:shape coordorigin="0,0" coordsize="'+(w*100)+','+(h*100)+'" path="'+z+'" style="width:'+w+'px;height:'+h+'px;left:0px;top:0px;position:absolute;display:block;margin:0px;padding:0px;" filled="f" stroked="t"><v:stroke color="black" opacity="0.2" weight="0.1pt" miterlimit="0" endcap="round" /></v:shape>';
					}self.innerHTML=head+flex+fyll+scalb+scald+shadb+shadd+foot;
					if(self.bu) {a=document.getElementById(self.id+'_flex').firstChild; z=document.getElementById(self.id+'_fyll').firstChild; j=0; t=0; self.timer=window.setInterval(function() {c=F(P(0),P(1),j/10); a.color=c; z.color=c; t==1?j--:j++; if(j>10&&t==0){j--; t=1;} if(j<0&&t==1){j++; t=0;}},self.pl);}
				}else if(self.tagName.toUpperCase()=="CANVAS"&&self.getContext("2d")) {self.ctx=self.getContext("2d");
					function fill(x,y,w,h) {if(self.wk4) {self.ctx.beginPath(); self.ctx.rect(x,y,w,h); self.ctx.closePath(); self.ctx.fill();}else {self.ctx.fillRect(x,y,w,h);}};
					function paint(j) {self.ctx.clearRect(0,0,self.width,self.height); g=self.ctx.createLinearGradient(0,0,(v?w:0),(v?0:h)); g.addColorStop(0,'rgba(255,255,255,0.75)'); g.addColorStop(0.05,'rgba(255,255,255,0.5)'); 
						g.addColorStop(0.5,'rgba(127,127,127,0.4)'); g.addColorStop(0.95,'rgba(0,0,0,0.55)'); g.addColorStop(1,'rgba(0,0,0,'+(v?0.66:0.8)+')'); self.ctx.lineWidth=0.25; self.ctx.lineCap='butt'; self.ctx.save(); 
						self.ctx.beginPath(); self.ctx.moveTo(0,h-q); self.ctx.quadraticCurveTo(0,h,q,h); self.ctx.quadraticCurveTo(0,h,0,h+q); self.ctx.lineTo(0,h+r); self.ctx.lineTo(w,h+r); self.ctx.lineTo(w,h+q); self.ctx.quadraticCurveTo(w,h,w-q,h); self.ctx.quadraticCurveTo(w,h,w,h-q); self.ctx.lineTo(w,q); self.ctx.quadraticCurveTo(w,0,w-q,0); self.ctx.lineTo(q,0); self.ctx.quadraticCurveTo(0,0,0,q); self.ctx.closePath(); self.ctx.clip();
						if(self.ge8) {self.ctx.fillStyle="rgba(0,0,0,0)"; self.ctx.fillRect(0,0,self.width,self.height);}
						if(self.bu) {a=F(P(0),P(1),j/10,true); self.ctx.fillStyle="rgba("+a+",1)"; fill(0,0,self.width,self.height);
						}else if(self.vl) {l=self.vl.length-(self.lv&&self.vl.length>=2?1:0); for(i=0; i<l; i++) {m+=Math.abs(self.vl[i]);} m=Math.max(m,Math.abs(self.vl[self.vl.length-1]));
							if(self.lv&&self.gr&&l==1) {s=(v?h:w)*(Math.abs(self.vl[0])/m); a=P(0); z=P(1); c=self.ctx.createLinearGradient(0,0,(v?0:w),(v?h:0)); c.addColorStop((v?1:0),'rgba('+hex2rgb(a)+',1)'); c.addColorStop((v?0:1),'rgba('+hex2rgb(z)+',1)'); x=x+e; y=h-s; e=s; self.ctx.fillStyle=c; if(v) {fill(0,y,w,s);}else {fill(x,0,s,self.height);}}
							else {if(v) {y=h; for(i=0; i<l; i++) {c=P(i); s=h*(Math.abs(self.vl[i])/m); y=y-s; e=s; self.ctx.fillStyle=c; fill(0,y,w,s);}}else {for(i=0; i<l; i++) {c=P(i); s=w*(Math.abs(self.vl[i])/m); x=x+e; e=s; self.ctx.fillStyle=c; fill(x,0,s,self.height);}}}
							if((v&&y>0.25)||(!v&&(x+s)<w)) {x=x+e; s=w-x; self.ctx.fillStyle="rgba("+self.dc+","+(window.opera?1.0:self.op)+")"; if(v) {fill(0,0,w,y);}else {fill(x,0,s,self.height);}}
							if(v) {self.ctx.fillStyle="rgba("+(self.lv&&Math.abs(self.vl[0])<=0?self.dc:hex2rgb(P(0)))+","+(window.opera?1.0:self.op)+")"; fill(0,h,w,w);}
						}else {self.ctx.fillStyle="rgba("+self.dc+","+(window.opera?1.0:self.op)+")"; fill(0,0,self.width,self.height);}
						self.ctx.fillStyle=g; fill(0,0,w,h); if(!self.ns) {
							g=self.ctx.createLinearGradient((v?0.5:0),(v?0:0.5),(v?w:0),(v?0:h)); g.addColorStop(0,"rgba(254,254,254,1)"); g.addColorStop(0.66,"rgba(254,254,254,0.8)"); g.addColorStop(1,"rgba(254,254,254,0)");
							if(v) {for(i=0; i<k; i++) {self.ctx.beginPath(); self.ctx.moveTo(.5,h-(b+(i*b)+.5)); self.ctx.lineTo(w,h-(b+(i*b)+.5)); self.ctx.strokeStyle='rgba(0,0,0,0.75)'; self.ctx.stroke(); self.ctx.beginPath(); self.ctx.moveTo(.5,h-(b+(i*b))); self.ctx.lineTo(w,h-(b+(i*b))); self.ctx.strokeStyle=g; self.ctx.stroke();}}
							else {for(i=0; i<k; i++) {self.ctx.beginPath(); self.ctx.moveTo(b+(i*b),.5); self.ctx.lineTo(b+(i*b),h+(window.opera?0:r)); self.ctx.strokeStyle='rgba(0,0,0,0.75)'; self.ctx.stroke(); self.ctx.beginPath(); self.ctx.moveTo(b+(i*b)+.5,.5); self.ctx.lineTo(b+(i*b)+.5,h); self.ctx.strokeStyle=g; self.ctx.stroke();}}
						}g=self.ctx.createLinearGradient(0,h,0,h+r); g.addColorStop(0,"rgba(0,0,0,1)"); g.addColorStop(0.1,"rgba(0,0,0,0.5)"); g.addColorStop(0.5,"rgba(0,0,0,0)"); g.addColorStop(1,"rgba(0,0,0,0)");
						self.ctx.fillStyle=g; fill(0,h,w,h+r); self.ctx.globalCompositeOperation=(window.opera?"xor":"destination-out"); 
						g=self.ctx.createLinearGradient(0,h,0,h+r); g.addColorStop(1,"rgba(0,0,0,1.0)"); g.addColorStop(0,"rgba(0,0,0,"+self.ty+")");
						self.ctx.fillStyle=g; fill(0,h,w,h+r); self.ctx.restore();
					}; if(self.bu) {j=0; t=0; self.timer=window.setInterval(function() {paint(j); t==1?j--:j++; if(j>10&&t==0){j--; t=1;} if(j<0&&t==1){j++; t=0;}},self.pl);}else{paint();}
				} 
			}catch(e) {}
		}return false;
	},
	
	remove : function(self) {
		if(self) {if(self.tagName.toUpperCase()=="CANVAS"||self.tagName.toUpperCase()=="VAR") {var parent=self.parentNode; parent.removeChild(self);}}return false;
	}
}	
