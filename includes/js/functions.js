/*
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @version   
 * @file      functions.js
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
*   Countdown before session expiration
*   Periodically syncs with server to ensure accuracy
**/
// Initialize last session sync time from sessionStorage (persistent across page reloads)
if (typeof lastSessionSync === 'undefined') {
    var lastSessionSync = parseInt(sessionStorage.getItem('lastSessionSync')) || 0;
}
// Sync interval: 5 minutes (300000 ms)
if (typeof sessionSyncInterval === 'undefined') {
    var sessionSyncInterval = 300000;
}
// Track if extend session dialog has been shown
if (typeof extendSessionShown === 'undefined') {
    var extendSessionShown = false;
}

function countdown()
{
    // Do not execute countdown on login page
    if ($('body').hasClass('login-page')) {
        return false;
    }

    // if a process is in progress then do not decrease the time counter.
    if (typeof ProcessInProgress !== 'undefined' && ProcessInProgress === true) {
        $('.countdown-icon')
            .addClass('fas fa-history')
            .removeClass('far fa-clock');

        $(this).delay(1000).queue(function()
        {
            countdown();
            $(this).dequeue();
        });

        return false;
    }

    // Periodically sync session time with server (every 5 minutes)
    let currentTime = new Date().getTime();
    if (lastSessionSync > 0 && currentTime - lastSessionSync > sessionSyncInterval) {
        syncSessionTimeWithServer();
        lastSessionSync = currentTime;
        sessionStorage.setItem('lastSessionSync', currentTime.toString());
    }

    // Continue
    let DayTill;
    let hoursInDay = 24;
    let limitTen = 10;
    let oneSecondsMs = 1000;
    let theDay =  $('#temps_restant').val();
    let today = new Date();
    let second = Math.floor(theDay - today.getTime() / oneSecondsMs);
    let minute = Math.floor(second / hourInMinutes);
    let hour = Math.floor(minute / hourInMinutes);
    let CHour= hour % hoursInDay;
    if (CHour < limitTen) {
        CHour = '0' + CHour;
    }
    let CMinute= minute % hourInMinutes;
    if (CMinute < limitTen) {
        CMinute = '0' + CMinute;
    }
    let CSecond= second % hourInMinutes;
    if (CSecond < limitTen) {
        CSecond = '0' + CSecond;
    }
    DayTill = CHour + ':' + CMinute + ':' + CSecond;

    // Session will soon be closed (check if <= 2 minutes using numeric comparison)
    if (second <= 120 && extendSessionShown === false) {
        showExtendSession();
        $('#countdown').css('color', 'red');
        extendSessionShown = true;
    } else if (second <= 120) {
        // Keep the countdown red even if dialog was already shown
        $('#countdown').css('color', 'red');
    }

    // Manage end of session (check if <= 0 seconds using numeric comparison)
    if ($('#temps_restant').val() !== '' && second <= 0 && parseInt($('#please_login').val()) !== 1) {
        $('#please_login').val('1');
        $(location).attr('href','index.php?session=expired');
    }

    //Rewrite the string to the correct information.
    if ($('#countdown')) {
        $('#countdown').html('<i class="far fa-clock countdown-icon mr-1"></i>' + DayTill);
    }

    //Create the timer 'counter' that will automatic restart function countdown() again every second.
    $(this).delay(1000).queue(function()
    {
        countdown();
        $(this).dequeue();
    });
}

/**
 * Synchronize session time with server
 * This ensures the countdown reflects the actual server-side session state
 */
function syncSessionTimeWithServer() {
    // Check if we have the necessary data
    if (typeof store === 'undefined' || !store.get('teampassUser')) {
        return;
    }

    var data = {
        'user_id': store.get('teampassUser').user_id,
    };

    $.ajax({
        type: 'POST',
        url: 'sources/main.queries.php',
        data: {
            type: 'user_get_session_time',
            type_category: 'action_user',
            data: prepareExchangedData(JSON.stringify(data), 'encode', store.get('teampassUser').key),
            key: store.get('teampassUser').key
        },
        dataType: 'text',
        async: true,
        success: function(serverData) {
            try {
                var decodedData = prepareExchangedData(serverData, 'decode', store.get('teampassUser').key);
                if (decodedData && decodedData.timestamp !== undefined && decodedData.timestamp > 0) {
                    // Update temps_restant with server value
                    $('#temps_restant').val(decodedData.timestamp);
                }
            } catch (e) {
                // Silently fail - will retry in next sync interval
                console.log('Session sync failed:', e);
            }
        },
        error: function() {
            // Silently fail - will retry in next sync interval
        }
    });
}


/**
*
**/
function unsanitizeString(string) {
    if(string !== "" && string !== null) {
        string = string.replace(/\\/g,"").replace(/&#92;/g, "\\").replace(/&quot;/g, '"');
    }
    return string;
}

/**
*   Clean up a string and delete any scripting tags
**/
function sanitizeString(string) {
    if(string !== "" && string !== null && string !== undefined) {
        string = string.replace(/\\/g,"&#92;").replace(/"/g,"&quot;");
        string = string.replace(new RegExp("\\s*<script[^>]*>[\\s\\S]*?</script>\\s*","ig"), "");
    }
    return string;
}

/**
*   Checks if URL has expected format
**/
function validateURL(url) {
    let urlregex = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
    return urlregex.test(url);
}


function split( val ) {
    return val.split( / \s*/ );
}

function extractLast( term ) {
    return split( term ).pop();
}

/**
 * Shows error message
 * @param  {string} message  Message to display
 * @return {boolean}         False
 */
function jsonErrorHdl(message)
{
    //show as confirm
    // Prepare modal
    showModalDialogBox(
        '#warningModal',
        '<i class="fas fa-warning fa-lg warning mr-2"></i>Caution',
        message,
        '',
        'Close',
        true,
        true
    );

    // Actions on modal buttons
    $(document).on('click', '#warningModalButtonClose', function() {
        
    });
    $(document).on('click', '#warningModalButtonAction', function() {
        // SHow user
    });
    return false;
}


/**
 * Converts a value (object, TypedArray, number, boolean, etc.) into a safe UTF-8 string
 * suitable for being passed to the CryptoJS encryption library (Encryption.encrypt()).
 * * This normalization is crucial to prevent the 'Uncaught RangeError: Failed to set the 
 * 'length' property on 'Array': Invalid array length' when the payload is not a string.
 * It ensures the input to the crypto library is always a standard string representation.
 * * @param {*} v The value to normalize (can be string, object, ArrayBuffer, null, undefined).
 * @returns {string} A safe UTF-8 string representation of the input.
 */
function tpSafePlain(v) {
    // 1. Handle common empty/invalid primitives
    if (v === undefined || v === null) return '';
    if (typeof v === 'string') return v;

    // 2. Handle ArrayBuffer / TypedArray (binary data)
    if (typeof ArrayBuffer !== 'undefined') {
        try {
            // Check if it's an ArrayBuffer itself
            if (v instanceof ArrayBuffer) {
                // Use modern TextDecoder if available
                if (typeof TextDecoder !== 'undefined') {
                    return new TextDecoder().decode(new Uint8Array(v));
                }
                // Fallback for older browsers
                return String.fromCharCode.apply(null, new Uint8Array(v));
            }
            // Check if it's a TypedArray view (Uint8Array, etc.)
            if (ArrayBuffer.isView && ArrayBuffer.isView(v)) {
                // Use modern TextDecoder if available
                if (typeof TextDecoder !== 'undefined') {
                    return new TextDecoder().decode(v);
                }
                // Fallback using buffer manipulation
                return String.fromCharCode.apply(null, new Uint8Array(v.buffer, v.byteOffset, v.byteLength));
            }
        } catch (e) {
            // Fallback for complex binary conversion errors
        }
    }

    // 3. Handle Primitives (number, boolean)
    if (typeof v === 'number' || typeof v === 'boolean') return String(v);

    // 4. Handle Objects (JSON serialization)
    if (typeof v === 'object') {
        try { 
            // Attempt to stringify objects (e.g., standard AJAX data)
            return JSON.stringify(v); 
        } catch (e) { 
            // Fallback for circular references or other JSON errors
            return String(v); 
        }
    }
    
    // 5. Final fallback for unknown types
    return String(v);
}

/**
 * Parses a string into JSON in a safe and robust way, without failing 
 * on invalid inputs like "undefined", empty strings, null, or HTML content.
 * * @param {*} data The data to parse. Expected to be a string or string-like.
 * @returns {{ok: boolean, value: *, error?: string}} A structured result object.
 * 'ok: true' indicates successful parsing.
 */
function safeParseJSONMaybe(data) {
    // Return early for common invalid values
    if (data === null || data === undefined) return { ok: false, error: 'empty', value: data };
    
    // If it's already an object (e.g., already parsed by a preceding process), return it as OK
    if (typeof data === 'object') return { ok: true, value: data };

    // Ensure we are working with a string for JSON parsing
    if (typeof data !== 'string') return { ok: false, error: 'not a string', value: data };
    
    const t = data.trim();
    
    // Check for common invalid JSON literals
    if (t === '' || t === 'undefined' || t === 'null') {
        return { ok: false, error: 'invalid json literal', value: data };
    }
    
    // Attempt standard JSON parsing
    try {
        return { ok: true, value: JSON.parse(t) };
    } catch (e) {
        // Return detailed error information on parsing failure
        return { ok: false, error: e && e.message ? e.message : 'parse error', value: data };
    }
}

/**
 * Handles the preparation of data exchanged between client and server, 
 * including optional encryption/decryption using the configured key.
 * * This function has been enhanced for:
 * 1. Robust data ENCODING: Always normalizes the payload to a safe string before encryption (using tpSafePlain).
 * 2. Robust data DECODING: Uses safeParseJSONMaybe for robust handling of server responses (plain or decrypted).
 * * @param {*} data The data payload to process (encode or decode).
 * @param {'encode'|'decode'} type Operation type.
 * @param {string} key The encryption key (if active).
 * @param {string} [fileName=''] Context: File where the function was called.
 * @param {string} [functionName=''] Context: Function where the call originated.
 * @param {boolean} [purify=true] Whether to run the result through purifyData.
 * @param {boolean} [bStringify=false] Whether to stringify the result of purifyData (rarely used here).
 * @returns {*} The processed data (decrypted/parsed object, encrypted string, or error HTML).
 */
function prepareExchangedData(data, type, key, fileName = '', functionName = '', purify = true, bStringify = false)
{
    // Determine the current encryption status globally
    const ENC_ACTIVE = parseInt($('#encryptClientServerStatus').val()) === 1;

    if (type === 'decode') {
        if (!ENC_ACTIVE) {
            // Response expected as plain JSON -> robust parsing
            const parsed = safeParseJSONMaybe(data);
            
            if (parsed.ok) {
                return purifyData(parsed.value, false, false, false, bStringify);
            } else {
                // Handle non-JSON server response gracefully with detailed error info
                return jsonErrorHdl(
                    '<b>Server response is not valid JSON</b>'
                    + (fileName ? '<br><b>Informations:</b><div>  - File: ' + fileName + '<br>  - Function: ' + functionName + '</div>' : '')
                    + '<div><br><b>Raw data:</b><br>' + sanitizeDom(String(parsed.value)) + '</div>'
                );
            }
        } else {
            // Encrypted response -> decrypt, then robust parsing
            try {
                let encryption = new Encryption();
                const decryptedStr = encryption.decrypt(data, key);
                
                const parsed = safeParseJSONMaybe(decryptedStr);
                
                if (!parsed.ok) {
                    // Handle case where decryption works, but the content is not valid JSON
                    return jsonErrorHdl(
                        '<b>Decrypted payload is not valid JSON</b>'
                        + (fileName ? '<br><b>Informations:</b><div>  - File: ' + fileName + '<br>  - Function: ' + functionName + '</div>' : '')
                        + '<div><br><b>Raw decrypted data:</b><br>' + sanitizeDom(String(decryptedStr)) + '</div>'
                    );
                }
                
                return purifyData(parsed.value, false, false, false, bStringify);
            } catch (e) {
                // Handle case where decryption itself failed (e.g., bad key, corrupted data)
                return jsonErrorHdl(
                    '<b>Decryption error occurred</b><div>' + e + '</div>'
                    + (fileName !== '' ? '<br><b>Informations:</b><div>  - File: ' + fileName + '<br>  - Function: ' + functionName + '</div>' : '')
                    + '<div><br><b>Raw answer from server:</b><br>' + sanitizeDom(String(data)) + '</div>'
                );
            }
        }
    } else if (type === 'encode') {
        if (!ENC_ACTIVE) {
            // ENCODE branch (No encryption)
            // Keep the original behavior (purify the data according to the flag)
            return purify === true ? purifyData(data, false, false, false, bStringify) : data;
        } else {
            // ENCODE branch (With encryption)
            let encryption = new Encryption();
            
            // IMPORTANT: Normalize the payload to a string BEFORE encryption to avoid RangeError
            const safePayload = tpSafePlain(data);
            
            // Perform encryption
            const out = encryption.encrypt(safePayload, key);
            
            // Preserve the original semantics (purify the encrypted string if requested)
            return purify === true ? purifyData(out, false, false, false, bStringify) : out;
        }
    } else {
        return false;
    }
}

function isJsonString(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

/**
 * 
 * @param {string} data Crypted string
 * @param {string} key  Session key
 */
function unCryptData(data, key)
{
    if (data !== undefined && data.substr(0, 7) === 'crypted') {
        let uncryptedData = prepareExchangedData(
            data.substr(7),
            'decode',
            key
        )
        
        if (uncryptedData.password.length > 0)
            return uncryptedData.password;
        else
            return false;
    }
    return false;
}

/**
 * 
 * @param {string}data Crypted string
 * @param {string}key  Session key
 * @param  {[type]} fileName  [description]
 * @param  {[type]} functionName  [description]
 */
function decodeQueryReturn(data, key, fileName = '', functionName = '')
{
    try {
        return prepareExchangedData(data , "decode", key, fileName, functionName);
    } catch (e) {
        // error
        toastr.remove();
        toastr.error(
            'An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data,
            'Error', {
                timeOut: 5000,
                progressBar: true
            }
        );
        return false;
    }
}

/**
 * 
 * @param {string} action Action
 * @param {string} name   Name
 * @param {array} data    Data
 */
function browserSession(action, name, data)
{
    // Initialize the session
    if (action === 'init') {
        if (store.get(name) === 'undefined'
            || store.get(name) === undefined
        ) {
            store.set(
                name,
                data
            );
        } else {
            // Ensure all entries exist
            $(data).each(function(value, key) {
                store.update(
                    name,
                    function(bSession)
                    {
                        bSession.key = value;
                    }
                )
            });
        }
    }
}

/**
 * 
 * @param {string} modalId      Modal id
 * @param {string} title        Title
 * @param {string} body         Body
 * @param {string} actionButton Action Button
 * @param {string} closeButton  Close Button
 * @param {string} xlSize       Size
 * @param {string} warningModal Warning Modal
 * @param {string} closeCross   Close on cross
 */
function showModalDialogBox(
    modalId,
    title,
    body,
    actionButton,
    closeButton,
    xlSize = false,
    warningModal = false,
    closeCross = true
) {
    $(modalId + 'Title').html(title);
    $(modalId + 'Body').html(body);
    if (actionButton === '') {
        $(modalId + 'ButtonAction').addClass('hidden');
    } else {
        $(modalId + 'ButtonAction').removeClass('hidden');
        $(modalId + 'ButtonAction').html(actionButton);
    }
    if (closeButton === '') {
        $(modalId + 'ButtonClose').addClass('hidden');
    } else {
        $(modalId + 'ButtonClose').removeClass('hidden');
        $(modalId + 'ButtonClose').html(closeButton);
    }
    if (xlSize === true) {
        $(modalId + ' div:first').addClass('modal-xl');
    } else {
        $(modalId + ' div:first').removeClass('modal-xl');
    }
    if (warningModal === true) {
        $(modalId + ':eq(1)').addClass('bg-warning');
    } else {
        $(modalId + ':eq(1)').removeClass('bg-warning');
    }
    if (closeCross === false) {
        $(modalId + 'CrossClose').addClass('hidden');
    } else {
        $(modalId + 'CrossClose').removeClass('hidden');
    }
    $(modalId).modal({
        backdrop : 'static',
        keyboard : false,
        show: true,
        focus: true
    });
    $(modalId).modal('handleUpdate');
}

/**
 * Sanitize a string
 * 
 * @param {string} str  The string
 */
function htmlEncode(str){
    return String(str).replace(/[^\w. ]/gi, function(c){
        return '&#'+c.charCodeAt(0)+';';
    });
}

/* Extend String object with method to encode multi-byte string to utf8
 * - monsur.hossa.in/2012/07/20/utf-8-in-javascript.html
 * - note utf8Encode is an identity function with 7-bit ascii strings, but not with 8-bit strings;
 * - utf8Encode('x') = 'x', but utf8Encode('ça') = 'Ã§a', and utf8Encode('Ã§a') = 'ÃÂ§a'*/
if (typeof String.prototype.utf8Encode == 'undefined') {
    String.prototype.utf8Encode = function() {
        return unescape( encodeURIComponent( this ) );
    };
}

/* Extend String object with method to decode utf8 string to multi-byte */
if (typeof String.prototype.utf8Decode == 'undefined') {
    String.prototype.utf8Decode = function() {
        try {
            return decodeURIComponent( escape( this ) );
        } catch (e) {
            return this; // invalid UTF-8? return as-is
        }
    };
}

function simplePurifier(
    text,
    bHtml = false,
    bSvg = false,
    bSvgFilters = false,
    bSanitize = true
) 
{
    var textCleaned = String(text)
    .replaceAll('&lt;', '<')
    .replaceAll('&#x3C;', '<')
    .replaceAll('&#x3c;', '<')
    .replaceAll('&#60;', '<')
    .replaceAll('&gt;', '>')
    .replaceAll('&#x3E;', '>')
    .replaceAll('&#x3e;', '>')
    .replaceAll('&#62;', '>')
    .replaceAll('&amp;', '&')
    .replaceAll('&#38;', '&')
    .replaceAll('&#038;', '&')
    .replaceAll('&#x26;', '&')
    .replaceAll('&quot;', '"')
    .replaceAll('&#34;;', '"')
    .replaceAll('&#034;;', '"')
    .replaceAll('&#x22;', '"')
    .replaceAll('&#39;', "'")
    .replaceAll('&#039;', "'");

    if (bSanitize === false || textCleaned.includes("img")) {
        return textCleaned;
    }

    // If no HTML, SVG or SVG filters are requested, return the cleaned text
    if (bHtml === true) {
        return sanitizeDom(
            DOMPurify.sanitize(
                textCleaned,
                {USE_PROFILES: {html:bHtml, svg:bSvg, svgFilters: bSvgFilters}}
            )
        );
    }
      
    // Sanitize with DOMPurify
    const sanitized = DOMPurify.sanitize(
        textCleaned,
        { USE_PROFILES: { html: bHtml, svg: bSvg, svgFilters: bSvgFilters } }
    );

    // Convert sanitized text to plain text
    const div = document.createElement('div');
    div.innerHTML = sanitized;
    const plainText = div.textContent;
    div.remove();
    return plainText;
}

/**
 * Permits to purify the content of an object using simplePurifier
 * Usefull for ajax answers
 * Can exclude some fields from HTML purification
 * Can exclude some fields from purification
 */
const htmlFields = ['description', 'desc', 'html'];
const ignoredFields = ['pw', 'previous_password', 'current_password', 'old_password', 'new_password', 'otp'];
function purifyData(obj, bHtml = false, bSvg = false, bSvgFilters = false, bStringify = false) {
    if (Array.isArray(obj)) {
        const purifiedObject = obj.map(item => purifyData(item, bHtml, bSvg, bSvgFilters, false));
        return (bStringify === true) ? JSON.stringify(purifiedObject) : purifiedObject;
    } else if (typeof obj === 'object' && obj !== null) {
        let purifiedObject = {};
        for (let key in obj) {
            if (obj.hasOwnProperty(key)) {
                if (ignoredFields.includes(key)) {
                    purifiedObject[key] = obj[key]; // Skip purification
                } else {
                    const forceHtml = htmlFields.includes(key);
                    purifiedObject[key] = purifyData(
                        obj[key],
                        forceHtml ? true : bHtml,
                        bSvg,
                        bSvgFilters,
                        false
                    );
                }
            }
        }
        const result = (bStringify === true) ? JSON.stringify(purifiedObject) : purifiedObject;
        return result;
    } else if (typeof obj === 'string') {
        return simplePurifier(obj, bHtml, bSvg, bSvgFilters);
    } else {
        return obj;
    }
}

/**
 * Permits to decode HTML entities in a string
 * This function is useful to decode HTML entities that may have been encoded
 * during the sanitization process.
 * @param {*} input 
 * @returns html decoded string
 */
function htmlDecode(input) {
    const doc = new DOMParser().parseFromString(input, 'text/html');
    return doc.documentElement.textContent;
}

/**
 * Permits to purify the content of a string using domPurify
 * @param {*} field 
 * @param {*} bHtml 
 * @param {*} bSvg 
 * @param {*} bSvgFilters 
 * @param {*} text 
 * @returns bool||string
 */
function fieldDomPurifier(
    field,
    bHtml = false,
    bSvg = false,
    bSvgFilters = false,
    text = ''
)
{
    if (field === undefined ||field === '') {
        return false;
    }
    let string = '';
    text = (text === '') ? $(field).val() : text;
    
    // Purify string
    string = simplePurifier(text, bHtml, bSvg, bSvgFilters);
    
    // Clear field if string is empty and warn user
    if (string === '' && text !== '') {
        $(field).val('');
        return false;
    }

    return string;
}

/**
 * Permits to get all fields of a class and purify them
 * @param {*} elementClass 
 * @returns array
 */
function fieldDomPurifierLoop(elementClass)
{
    let purifyStop = false,
        arrFields = [];
    $.each($(elementClass), function(index, element) {
        purifiedField = fieldDomPurifier(
            '#' + $(element).attr('id'), 
            $(element).hasClass('purifyHtml') === true ? true : false,
            $(element).hasClass('purifySvg') === true ? true : false,
            $(element).hasClass('purifySvgFilter') === true ? true : false,
            typeof $(element).data('purify-text') !== 'undefined' ? $(element).data('purify-text') : ''
        );

        if (purifiedField === false) {
            // Label is empty
            toastr.remove();
            toastr.warning(
                'XSS attempt detected. Please remove all special characters from your input.',
                'Error', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            $('#' + $(element).attr('id')).focus();
            purifyStop = true;
            return {
                'purifyStop' : purifyStop,
                'arrFields' : arrFields
            };
        } else {
            $(element).val(purifiedField);
            if (typeof $(element).data('field') !== 'undefined') {
                arrFields[$(element).data('field')] = purifiedField;
            } else if (typeof $(element).data('field-name') !== 'undefined') {
                arrFields[$(element).data('field-name')] = purifiedField;
            }
        }
    });
    
    // return
    return {
        'purifyStop' : purifyStop,
        'arrFields' : arrFields
    };
}

/**
 * Permits to purify the content of a string using domPurify
 * @param {*} field 
 * @param {*} bHtml 
 * @param {*} bSvg 
 * @param {*} bSvgFilters 
 * @returns bool||string
 */
function fieldDomPurifierWithWarning(
    field,
    bHtml = false,
    bSvg = false,
    bSvgFilters = false,
    bSetting = false,
)
{
    if (field === undefined || field === '') {
        return false;
    }
    if ($(field).val() === '') {
        return '';
    }
    let string = '',
        currentString = $(field).val();

    // if bSetting is true, we use the setting value
    // remove any closing ', string that could corrupt the setting
    if (bSetting === true && Array.isArray(currentString) === false) {
        currentString = currentString.replace(/',/g, '');
    }

    // Purify string
    string = simplePurifier(
        sanitizeDom(currentString),
        bHtml,
        bSvg,
        bSvgFilters
    );
    
    // Clear field if string is empty and warn user
    if (string === '') {
        toastr.remove();
        toastr.warning(
            'XSS attempt detected. Please remove all special characters from your input.',
            'Error', {
                timeOut: 5000,
                progressBar: true
            }
        );
        $(field).focus();
        return false;
    }

    return string;
}

const sanitizeDom = (str) => {
    const div = document.createElement('div');
    div.textContent = str;
    const newString = div.innerHTML;
    div.remove();
    return newString;
}


function doAjaxQuery(type, url, data) {
    return new Promise(function(resolve, reject) {
        $.ajax({
            type: type,
            url: url, 
            data: data,
            dataType: 'json',
            success: function(response) {
                // Traitement de la réponse
                console.log(response);

                // Vous pouvez également effectuer des opérations ici avec la réponse

                // Résoudre la promesse avec la réponse
                resolve(response);
            },
            error: function(xhr, status, error) {
                // Gestion des erreurs
                console.error(error);

                // Rejeter la promesse en cas d'erreur
                reject(error);
            }
        });
    });
}

/**
 * Permits to check if a string is a valid base64 encoded string
 * @param {*} str 
 * @returns 
 */
function isBase64(str) {
    if (typeof str !== 'string') return false;

    // If the prefix is there, it's clearly base64
    if (str.startsWith('b64:')) return true;

    // Check if the string contains only valid base64 characters
    if (!/^[A-Za-z0-9+/=]+$/.test(str)) return false;

    // Base64 strings should be a multiple of 4 in length
    if (str.length % 4 !== 0) return false;

    try {
        const decoded = atob(str);
        // Test if the decoded string can be re-encoded to the same base64 string
        return btoa(decoded).replace(/=+$/, '') === str.replace(/=+$/, '');
    } catch (e) {
        return false;
    }
}

/**
 * Permits to decode a filename that may be encoded in base64
 * @param {*} encodedName 
 * @returns 
 */
function decodeFilename(encodedName) {
    try {
        if (typeof encodedName !== 'string') return encodedName;

        if (encodedName.startsWith('b64:')) {
            return atob(encodedName.substring(4));
        }

        if (isBase64(encodedName)) {
            return atob(encodedName);
        }

        // Else, return the string as is
        return encodedName;
    } catch (e) {
        return encodedName; // If atob fails, return the original string
    }
}