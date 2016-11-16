/*
*	Agses Flickering - HTML5-CANVAS
*	Type: jQuery Plugin
*   Author: David Mirk
*   Owner: ICSL GmbH 
*	Website: www.icsl.at
*	Website: www.agses.net
*/
//jquery wrapper
$.fn.agsesInit = function(options) {
	if (undefined != $(this).data('agsesFlicker')) {
		console.log("AgsesFlicker already initialized, call .agsesFlickerCanvas(message) to change flickercode");
	} else {
		var agsesFlicker = new AgsesFlicker(this);	
		$(this).data('agsesFlicker', agsesFlicker);
		$(this).data('agsesFlicker').init(options);
	}
};

$.fn.agsesFlicker = function(message) {
	if (undefined != $(this).data('agsesFlicker')) 
		$(this).data('agsesFlicker').agsesFlickerCanvas(message);
	else
		console.log("AgsesFlicker not initialized, call .agsesInit(options) first");
};

$.fn.agsesZoom = function(factor) {
	if (undefined != $(this).data('agsesFlicker')) 
		$(this).data('agsesFlicker').zoomCanvas(factor);
	else
		console.log("AgsesFlicker not initialized, call .agsesInit(options) first");
};

$.fn.agsesDelay = function(factor) {
	if (undefined != $(this).data('agsesFlicker')) 
		$(this).data('agsesFlicker').changeDelay(factor);
	else
		console.log("AgsesFlicker not initialized, call .agsesInit(options) first");
};

$.fn.agsesToggleStyle = function(button) {
	if (undefined != $(this).data('agsesFlicker')) 
		$(this).data('agsesFlicker').toggleStyle(button);
	else
		console.log("AgsesFlicker not initialized, call .agsesInit(options) first");
};

//Plugin
function AgsesFlicker(canvas) {
	this._c = canvas;
	this._o;
	//setting variables
	this.delay = 40;
	this.style = 'trapez';
	this.colorTrue = "#fff";
	this.colorFalse = "#000";	
	this.colorBorder = "#B92840";	
	this.buttonsPath = "includes/libraries/Authentication/agses/buttons/";

	this.topX = 80;
	this.bottomX = 0;
	this.topWidth = 50;
	this.bottomWidth = 76;	
	this.topY = 10;
	this._scale = 1.0;

	this.baseWidth = 440.0;
	this.height = 130.0;

	//working variables
	this.intid;
	this.msg;
	this.idx;
	this.fcnt;
	
	this.init = function(options) {
		var self = this;
		self._c = canvas;   
		self._o = options;

		self.baseWidth = self.bottomWidth*self._scale*6;

		if (!$(self._c).hasClass("agses_canvas"))
			$(self._c).addClass("agses_canvas")

		$(self._c).attr("width", self.baseWidth);

		if (options.controls == null || options.controls == "undefined" || (options.controls && options.controls == true)) {
			self.agsesInjectControls();	
		} else {
			$(self._c).css("padding-bottom","10px");
		}

		if (options.delay && options.delay > 10)
			self.delay = options.delay;

		if (options.style && options.style != "")
			self.changeStyle(options.style);

		if (options.flickerColor && options.flickerColor != "")
			self.colorFalse = options.flickerColor;		

		if (options.borderColor && options.borderColor != "")
			self.colorBorder = options.borderColor;		

		if (options.height && options.height > 10)
			self.height = options.height;

		if (options.width && options.width > 0) {		
			if (options.width != self.baseWidth) {
				self._scale = options.width/self.baseWidth;				
			}
		} else 
			self._scale = 460/self.baseWidth;	

		self.scaleCanvas();

		if (options.message && options.message != "") 
			self.agsesFlickerCanvas(options.message);
		else {
			self.drawCanvasFlicker([1,1,1,1,1,1]);
			self.drawCanvasText("Loading data...");
		}
	}

	this.agsesInjectControls = function() {
		var self = this;
		
		var id = $(self._c).attr("id");
		var controls = $('<div class="agses_controls" id="'+id+'_controls"></div>');

		var slowerButton = $('<a onclick="$(\'#'+id+'\').agsesDelay(10);" id="'+id+'_slowerButton"></a>');
		$(slowerButton).append('<img src="'+self.buttonsPath+'button_image_slow_enabled.png" alt="" style="border-style: none;">');

		var fasterButton = $('<a onclick="$(\'#'+id+'\').agsesDelay(-10);" id="'+id+'_fasterButton"></a>');
		$(fasterButton).append('<img src="'+self.buttonsPath+'button_image_fast_enabled.png" alt="" style="border-style: none;">');

		var zoomOutButton = $('<a onclick="$(\'#'+id+'\').agsesZoom(-0.3);" id="'+id+'_zoomInButton" style="margin-left:10px"></a>');
		$(zoomOutButton).append('<img src="'+self.buttonsPath+'button_image_zoomout_enabled.png" alt="" style="border-style: none;">');

		var zoomInButton = $('<a onclick="$(\'#'+id+'\').agsesZoom(0.3);" id="'+id+'_zoomOutButton"></a>');
		$(zoomInButton).append('<img src="'+self.buttonsPath+'button_image_zoomin_enabled.png" alt="" style="border-style: none;">');

		var styleButton = $('<a onclick="$(\'#'+id+'\').agsesToggleStyle(this);" style="float:right" id="'+id+'_styleButton"></a>');
		$(styleButton).append('<img src="'+self.buttonsPath+'button_image_bars.png" alt="" style="border-style: none;">');

		controls.append(slowerButton);
		controls.append(fasterButton);	

		controls.append(zoomOutButton);
		controls.append(zoomInButton);	
		
		controls.append(styleButton);

		$(controls).css("width",self.baseWidth);
		$(self._c).after(controls);
	}

	this.agsesFlickerCanvas = function(message) {
		var self = this;
		if (self.agsesSetMessageCanvas(message)) 
			self.agsesStartFlickeringCanvas();
		else {
			self.drawCanvasFlicker([1,1,1,1,1,1]);
			self.drawCanvasText("Error: Invalid Message");
		}
	}

	this.agsesSetMessageCanvas = function(message) {
		var self = this;
		// --- VARIABLES
		var tmpMsg, tmpCnt, codeStr, codeVal, isValid, i;

		// --- IMPLEMENTATION
		isValid = false;
		
		// message must be non-empty and its length even (2 nibbles per code frame)
		if ((message.length > 0) && (message.length % 2 === 0)) {

			tmpMsg = message.toLowerCase();
			tmpCnt = tmpMsg.length / 2;
				
			// check code string for invalid characters and frame values
			isValid = true;
			for (i = 0; (i < tmpCnt) && isValid; i++) {
				codeStr = tmpMsg.charAt(2*i) + tmpMsg.charAt(2*i+1);
				codeVal = parseInt('0x'+codeStr, 16);
				isValid = (codeVal >= 0) && (codeVal <= 63);
			}
			
			// if message is valid, assign it to global variables
			if (isValid === true) {
				self.msg = tmpMsg;
				self.fcnt = tmpCnt;
				self.idx = 0;
			}
		}

		return isValid;
	}

	this.agsesStartFlickeringCanvas = function() {
		var self = this;
		// make sure flickering isn't already running
		self.agsesStopFlickeringCanvas();
			
		// check if we have something to display
		if (self.msg !== '') {
			// install timer for next frame
			self.intid = window.setTimeout(function() {
				self.agsesShowNextFrameCanvas();
			}, self.delay);
		}
	}

	this.agsesStopFlickeringCanvas = function() {
		var self = this;
		if (self.intid !== 0) {
			window.clearTimeout(self.intid);
			self.intid = 0;
		}
	}

	this.agsesShowNextFrameCanvas = function() {
		var self = this;
		// --- VARIABLES
		var code = self.msg.charAt(2*self.idx)+self.msg.charAt(2*self.idx+1);

		self.drawCanvasFlicker(Hex2BinArray(code));

		// advance position in message
		self.idx = ++self.idx % self.fcnt;

		// rearm timer for next frame		
		if (self.intid !== 0) {
			window.clearTimeout(self.intid);
		}

		self.intid = window.setTimeout(function() {
			self.agsesShowNextFrameCanvas();
		}, self.delay);
	}

	this.drawCanvasText = function(text) {
		var self = this;
		if (self._c == null || self._c == "undefined")
			return;

		var c2 = self._c[0].getContext("2d");

		c2.fillStyle = self.colorFalse;
		c2.font = "20px Arial";
		c2.textAlign = "center";
		c2.fillText(text,self._c.width()/2,(self._c.height())/2+self.topY+3);
	}

	this.drawCanvasFlicker = function(binFrame) {
		var self = this;
		if (self._c == null || self._c == "undefined")
			return;

		var c2 = self._c[0].getContext("2d");

		c2.clearRect(0, 0, c2.canvas.width, c2.canvas.height);		

		var startBottomX = self.bottomX;
		var startTopX = self.topX*self._scale;	
		var topWidth = self.topWidth*self._scale;
		var bottomWidth = self.bottomWidth*self._scale;
		var height = self.height*self._scale;
		var lineTopY = (height - self.topY) / 3;

		self.drawBorders();

		for(var b in binFrame) {
			var bin = binFrame[b];
			if (bin == 0)
				c2.fillStyle = self.colorFalse;
			else
				c2.fillStyle = self.colorTrue;

			c2.beginPath();
			c2.moveTo(startTopX, self.topY);
			c2.lineTo(startBottomX, height);
			c2.lineTo(startBottomX+bottomWidth, height);
			c2.lineTo(startTopX+topWidth, self.topY);
			c2.closePath();
			c2.fill();
			
			startTopX += topWidth;
			startBottomX += bottomWidth;
		}		

		if(self.height > 100 && self.style == "trapez") {
			//dotted lines
			c2.strokeStyle = "#ccc";
			c2.setLineDash([2,2]);
			c2.beginPath();
			c2.moveTo(self.topX*self._scale-25*self._scale, self.topY+lineTopY);
			c2.lineTo(self.topX*self._scale+6*topWidth+25*self._scale, self.topY+lineTopY);
			c2.closePath();
			c2.stroke();

			c2.beginPath();
			c2.moveTo(self.topX*self._scale-52*self._scale, self.topY+2*lineTopY);
			c2.lineTo(self.topX*self._scale+6*topWidth+52*self._scale, self.topY+2*lineTopY);
			c2.closePath();
			c2.stroke();
		}
	
	}

	this.drawBorders = function() {
		var self = this;
		if (self._c == null || self._c == "undefined")
			return;

		var c2 = self._c[0].getContext("2d");

		var startBottomX = self.bottomX;
		var startTopX = self.topX*self._scale;	
		var topWidth = self.topWidth*self._scale;
		var bottomWidth = self.bottomWidth*self._scale;
		var height = self.height*self._scale;

		if(self.style == "trapez") {
			//borders
			c2.fillStyle = self.colorBorder;
			c2.beginPath();
			c2.moveTo(startTopX-10, 0);
			c2.lineTo(startBottomX-15, height);
			c2.lineTo(startBottomX, height);
			c2.lineTo(startTopX+20, 0);
			c2.closePath();
			c2.fill();

			c2.beginPath();
			c2.moveTo(startTopX+6*topWidth-20, 0);
			c2.lineTo(startBottomX+6*bottomWidth, height);
			c2.lineTo(startBottomX+6*bottomWidth+15, height);
			c2.lineTo(startTopX+6*topWidth+10, 0);
			c2.closePath();
			c2.fill();
		}

		//little arrows
		var offset = 0;
		if(self.style == "trapez")
			c2.fillStyle = "#fff";
		else {
			c2.fillStyle = "#000";
			offset = 3;
		}

		c2.beginPath();
		c2.moveTo(startTopX-2+offset, 3);
		c2.lineTo(startTopX+offset, 10);
		c2.lineTo(startTopX+4+offset, 3);
		c2.closePath();
		c2.fill();

		c2.fillStyle = "#000";
		for(var i = 1; i < 6; i++) {
			c2.beginPath();
			c2.moveTo(startTopX+i*topWidth-2, 3);
			c2.lineTo(startTopX+i*topWidth, 10);
			c2.lineTo(startTopX+i*topWidth+4, 3);
			//c2.lineTo(startTopX+6, 5);
			c2.closePath();
			c2.fill();
		}

		if(self.style == "trapez")
			c2.fillStyle = "#fff";
		else
			c2.fillStyle = "#000";
		c2.beginPath();
		c2.moveTo(startTopX+6*topWidth-2-offset, 3);
		c2.lineTo(startTopX+6*topWidth-offset, 10);
		c2.lineTo(startTopX+6*topWidth+4-offset, 3);
		//c2.lineTo(startTopX+6, 5);
		c2.closePath();
		c2.fill();
	}

	this.scaleCanvas = function() {
		var self = this;
		if (self._c == null || self._c == "undefined")
			return;				

		var newWidth = parseInt(self.bottomWidth*self._scale*6);
		var newHeight = parseInt(self.height*self._scale);

		self._c.width(newWidth);
		$(self._c).attr("width", newWidth);

		//self.height = newHeight;
		self._c.height(newHeight);
		$(self._c).attr("height", newHeight);

		$('#'+$(self._c).attr("id")+'_controls').css("width",("width", newWidth));
	}

	this.zoomCanvas = function(factor) {
		var self = this;
		if (self._c == null || self._c == "undefined")
			return;	

		var id = $(self._c).attr("id");
		var outb = $('#'+id+'_zoomOutButton');
		var inb = $('#'+id+'_zoomInButton');

		if ((self._scale+factor) > 3) {
			$(outb).find("img").attr("src",self.buttonsPath+"button_image_zoomin_disabled.png");			
			return;
		} else {
			$(outb).find("img").attr("src",self.buttonsPath+"button_image_zoomin_enabled.png");			
		}

		if ((self._scale+factor) < 0.3) {
			$(inb).find("img").attr("src",self.buttonsPath+"button_image_zoomout_disabled.png");
			return;
		} else {
			$(inb).find("img").attr("src",self.buttonsPath+"button_image_zoomout_enabled.png");
		}

		self._scale += factor;		

		self.scaleCanvas();
	}

	this.changeDelay = function(factor) {
		var self = this;
		if (self._c == null || self._c == "undefined")
			return;	

		var id = $(self._c).attr("id");
		var slb = $('#'+id+'_slowerButton');
		var fab = $('#'+id+'_fasterButton');

		if ((self.delay+factor) < 30) {
			$(fab).find("img").attr("src",self.buttonsPath+"button_image_fast_disabled.png");			
			return;
		} else {
			$(fab).find("img").attr("src",self.buttonsPath+"button_image_fast_enabled.png");			
		}

		if ((self.delay+factor) > 100) {
			$(slb).find("img").attr("src",self.buttonsPath+"button_image_slow_disabled.png");
			return;
		} else {
			$(slb).find("img").attr("src",self.buttonsPath+"button_image_slow_enabled.png");
		}

		self.delay += factor;
	}

	this.changeStyle = function(style) {
		var self = this;
		self.style = style;

		var id = $(self._c).attr("id");
		var stb = $('#'+id+'_styleButton');

		if (self.style == "bars") {
			self.topX = 0;
			self.bottomX = 0;
			self.topWidth = 76;
			self.bottomWidth = 76;
			$(stb).find("img").attr("src",self.buttonsPath+"button_image_trapez.png");
		} else if (self.style=="trapez") {
			self.topX = 80;
			self.bottomX = 0;
			self.topWidth = 50;
			self.bottomWidth = 76;
			$(stb).find("img").attr("src",self.buttonsPath+"button_image_bars.png");
		}	
	}

	this.toggleStyle = function(button) {
		var self = this;
		if (self.style == "bars") {
			self.changeStyle("trapez");		
		} else if (self.style=="trapez") {
			self.changeStyle("bars");		
		}		
	}
}

//Useful Functions
function checkHex(n){return/^[0-9A-Fa-f]{1,64}$/.test(n)}
function Hex2Bin(n){if(!checkHex(n))return 0;return parseInt(n,16).toString(2)}
function Hex2BinArray(n) {var tempBin = padZero(""+Hex2Bin(n),6,0);return [...tempBin];}
function padZero(str, len, c){var s= str, c= c || '0';while(s.length< len) {s= c+ s}; return s;}