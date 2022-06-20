/** 
 * =================================================================
 * Javascript code for OWASP CSRF Protector
 * Task it does: Fetch csrftoken from cookie, and attach it to every
 * 		POST request
 *		Allowed GET url
 *			-- XHR
 *			-- Static Forms
 *			-- URLS (GET only)
 *			-- dynamic forms
 * =================================================================
 */

var CSRFP_FIELD_TOKEN_NAME = 'csrfp_hidden_data_token';
var CSRFP_FIELD_URLS = 'csrfp_hidden_data_urls';

var CSRFP = {
	CSRFP_TOKEN: 'CSRFP-Token',
	/**
	 * Array of patterns of url, for which csrftoken need to be added
	 * In case of GET request also, provided from server
	 *
	 * @var {Array}
	 */
	checkForUrls: [],
	/**
	 * Returns true if the get request doesn't need csrf token.
	 *
	 * @param {String} url to check.
	 * @return {Boolean} true if csrftoken is not needed.
	 */
	_isValidGetRequest: function (url) {
		for (var i = 0; i < CSRFP.checkForUrls.length; i++) {
			var match = CSRFP.checkForUrls[i].exec(url);
			if (match !== null && match.length > 0) {
				return false;
			}
		}
		return true;
	},
	/**
	 * Returns auth key from cookie.
	 *
	 * @return {String} auth key from cookie.
	 */
	_getAuthKey: function () {
		var regex = new RegExp(`(?:^|;\s*)${CSRFP.CSRFP_TOKEN}=([^;]+)(;|$)`);
		var regexResult = regex.exec(document.cookie);
		if (regexResult === null) {
			return null;
		}

		return regexResult[1];
	},
	/** 
	 * Returns domain name of a url.
	 *
	 * @param {String} url - url to check.
	 * @return {String} domain of the input url.
	 */
	_getDomain: function (url) {
		// TODO(mebjas): add support for other protocols that web supports.
		if (url.indexOf('http://') !== 0 && url.indexOf('https://') !== 0) {
			return document.domain;
		}
		return /http(s)?:\/\/([^\/]+)/.exec(url)[2];
	},
	/**
     * Creates hidden input element with CSRF_TOKEN in it.
	 *
	 * @return {HTMLInputElement} hidden input element.
	 */
	_createHiddenInputElement: function () {
		var inputElement = document.createElement('input');
		inputElement.setAttribute('name', CSRFP.CSRFP_TOKEN);
		inputElement.setAttribute('class', CSRFP.CSRFP_TOKEN);
		inputElement.type = 'hidden';
		inputElement.value = CSRFP._getAuthKey();
		return inputElement;
	},
	/**
	 * Returns absolute url from the input relative components.
	 *
	 * @param {String} basePart - base part of the url.
	 * @param {String} relativePart - relative part of the url.
	 * @return {String} absolute url.
	 */
	_createAbsolutePath: function (basePart, relativePart) {
		var stack = basePart.split("/");
		var parts = relativePart.split("/");
		stack.pop();

		for (var i = 0; i < parts.length; i++) {
			if (parts[i] === ".") {
				continue;
			}
			if (parts[i] === "..") {
				stack.pop();
			} else {
				stack.push(parts[i]);
			}
		}
		return stack.join("/");
	},
	/**
	 * Creates a function wrapper around {@param runnableFunction}, removes
     * CSRF Token before calling the function and then put it back.
	 *
	 * @param {Function} runnableFunction - function to run.
	 * @param {Object} htmlFormObject - reference form object.
	 * @return modified wrapped function.
	 */
	_createCsrfpWrappedFunction: function (runnableFunction, htmlFormObject) {
		return function (event) {
			// Remove CSRf token if exists
			if (typeof htmlFormObject[CSRFP.CSRFP_TOKEN] !== 'undefined') {
				var target = htmlFormObject[CSRFP.CSRFP_TOKEN];
				target.parentNode.removeChild(target);
			}

			// Trigger the functions
			var result = runnableFunction.apply(this, [event]);

			// Now append the CSRFP-Token back
			htmlFormObject.appendChild(CSRFP._createHiddenInputElement());
			return result;
		};
	},
	/**
	 * Initialises the CSRFProtector js script.
	 */
	_init: function () {
		this.CSRFP_TOKEN = document.getElementById(
			CSRFP_FIELD_TOKEN_NAME).value;

		try {
			var csrfFieldElem = document.getElementById(CSRFP_FIELD_URLS);
			this.checkForUrls = JSON.parse(csrfFieldElem.value);
		} catch (exception) {
			console.error(exception);
			console.error('[ERROR] [CSRF Protector] unable to parse blacklisted'
				+ ` url fields. Exception = ${exception}`);
		}

		// Convert the rules received from php library to regex objects.
		for (var i = 0; i < CSRFP.checkForUrls.length; i++) {
			this.checkForUrls[i]
				= this.checkForUrls[i].replace(/\*/g, '(.*)')
					.replace(/\//g, "\\/");
			this.checkForUrls[i] = new RegExp(CSRFP.checkForUrls[i]);
		}
	}
}

//==========================================================
// Adding tokens, wrappers on window onload
//==========================================================

function csrfprotector_init() {

	// Call the init function
	CSRFP._init();

	// Basic FORM submit event handler to intercept the form request and attach
	// a CSRFP TOKEN if it's not already available.
	var basicSubmitInterceptor = function (event) {
		if (!event.target[CSRFP.CSRFP_TOKEN]) {
			event.target.appendChild(CSRFP._createHiddenInputElement());
		} else {
			//modify token to latest value
			event.target[CSRFP.CSRFP_TOKEN].value = CSRFP._getAuthKey();
		}
	};

	//==================================================================
	// Adding csrftoken to request resulting from <form> submissions
	// Add for each POST, while for mentioned GET request
	// TODO - check for method
	//==================================================================
	// run time binding
	document.querySelector('body').addEventListener('submit', function (event) {
		if (event.target.tagName.toLowerCase() === 'form') {
			basicSubmitInterceptor(event);
		}
	});

	//==================================================================
	// Adding csrftoken to request resulting from direct form.submit() call
	// Add for each POST, while for mentioned GET request
	// TODO - check for form method
	//==================================================================
	HTMLFormElement.prototype.submit_real = HTMLFormElement.prototype.submit;
	HTMLFormElement.prototype.submit = function () {
		// check if the FORM already contains the token element
		if (!this.getElementsByClassName(CSRFP.CSRFP_TOKEN).length) {
			this.appendChild(CSRFP._createHiddenInputElement());
		}
		this.submit_real();
	};

	/**
	 * Add wrapper for HTMLFormElements addEventListener so that any further 
	 * addEventListens won't have trouble with CSRF token
	 * todo - check for method
	 */
	HTMLFormElement.prototype.addEventListener_real
		= HTMLFormElement.prototype.addEventListener;
	HTMLFormElement.prototype.addEventListener = function (
		eventType, func, bubble) {
		if (eventType === 'submit') {
			var wrappedFunc = CSRFP._createCsrfpWrappedFunction(func, this);
			this.addEventListener_real(eventType, wrappedFunc, bubble);
		} else {
			this.addEventListener_real(eventType, func, bubble);
		}
	};

	/**
	 * Add wrapper for IE's attachEvent
	 * todo - check for method
	 * todo - typeof is now obsolete for IE 11, use some other method.
	 */
	if (HTMLFormElement.prototype.attachEvent) {
		HTMLFormElement.prototype.attachEvent_real
			= HTMLFormElement.prototype.attachEvent;
		HTMLFormElement.prototype.attachEvent = function (eventType, func) {
			if (eventType === 'onsubmit') {
				var wrappedFunc = CSRFP._createCsrfpWrappedFunction(func, this);
				this.attachEvent_real(eventType, wrappedFunc);
			} else {
				this.attachEvent_real(eventType, func);
			}
		}
	}

	//==================================================================
	// Wrapper for XMLHttpRequest & ActiveXObject (for IE 6 & below)
	// Set X-No-CSRF to true before sending if request method is 
	//==================================================================

	/** 
	 * Wrapper to XHR open method
	 * Add a property method to XMLHttpRequest class
	 * @param: all parameters to XHR open method
	 * @return: object returned by default, XHR open method
	 */
	function new_open(method, url, async, username, password) {
		this.method = method;
		var isAbsolute = url.indexOf("./") === -1;
		if (!isAbsolute) {
			var base = location.protocol + '//' + location.host
				+ location.pathname;
			url = CSRFP._createAbsolutePath(base, url);
		}

		if (method.toLowerCase() === 'get' && !CSRFP._isValidGetRequest(url)) {
			var token = CSRFP._getAuthKey();
			if (url.indexOf('?') === -1) {
				url += `?${CSRFP.CSRFP_TOKEN}=${token}`
			} else {
				url += `&${CSRFP.CSRFP_TOKEN}=${token}`;
			}
		}

		return this.old_open(method, url, async, username, password);
	}

	/** 
	 * Wrapper to XHR send method
	 * Add query parameter to XHR object
	 *
	 * @param: all parameters to XHR send method
	 *
	 * @return: object returned by default, XHR send method
	 */
	function new_send(data) {
		if (this.method.toLowerCase() === 'post') {
			// attach the token in request header
			this.setRequestHeader(CSRFP.CSRFP_TOKEN, CSRFP._getAuthKey());
		}
		return this.old_send(data);
	}

	if (window.XMLHttpRequest) {
		// Wrapping
		XMLHttpRequest.prototype.old_send = XMLHttpRequest.prototype.send;
		XMLHttpRequest.prototype.old_open = XMLHttpRequest.prototype.open;
		XMLHttpRequest.prototype.open = new_open;
		XMLHttpRequest.prototype.send = new_send;
	}
	if (typeof ActiveXObject !== 'undefined') {
		ActiveXObject.prototype.old_send = ActiveXObject.prototype.send;
		ActiveXObject.prototype.old_open = ActiveXObject.prototype.open;
		ActiveXObject.prototype.open = new_open;
		ActiveXObject.prototype.send = new_send;
	}
	//==================================================================
	// Rewrite existing urls ( Attach CSRF token )
	// Rules:
	// Rewrite those urls which matches the regex sent by Server
	// Ignore cross origin urls & internal links (one with hashtags)
	// Append the token to those url already containing GET query parameter(s)
	// Add the token to those which does not contain GET query parameter(s)
	//==================================================================

	for (var i = 0; i < document.links.length; i++) {
		document.links[i].addEventListener("mousedown", function (event) {
			var href = event.target.href;
			if (typeof href !== "string") {
				return;
			}
			var urlParts = href.split('#');
			var url = urlParts[0];
			var hash = urlParts[1];

			if (CSRFP._getDomain(url).indexOf(document.domain) === -1
				|| CSRFP._isValidGetRequest(url)) {
				//cross origin or not to be protected by rules -- ignore
				return;
			}

			var token = CSRFP._getAuthKey();
			if (url.indexOf('?') !== -1) {
				if (url.indexOf(CSRFP.CSRFP_TOKEN) === -1) {
					url += `&${CSRFP.CSRFP_TOKEN}=${token}`;
				} else {
					var replacementString = `${CSRFP.CSRFP_TOKEN}=${token}$1`;
					url = url.replace(
						new RegExp(CSRFP.CSRFP_TOKEN + "=.*?(&|$)", 'g'),
						replacementString);
				}
			} else {
				url += `?${CSRFP.CSRFP_TOKEN}=${token}`;
			}

			event.target.href = url;
			if (hash) {
				event.target.href += `#${hash}`;
			}
		});
	}
}

window.addEventListener("DOMContentLoaded", function () {
	csrfprotector_init();

	// Dispatch an event so clients know the library has initialized
	var postCsrfProtectorInit = new Event('postCsrfProtectorInit');
	window.dispatchEvent(postCsrfProtectorInit);
}, false);
