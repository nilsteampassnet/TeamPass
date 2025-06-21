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
**/
function countdown()
{
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

    // Session will soon be closed
    if (DayTill === '00:00:50') {
        showExtendSession();
        $('#countdown').css('color', 'red');
    }

    // Manage end of session
    if ($('#temps_restant').val() !== '' && DayTill <= '00:00:00' && parseInt($('#please_login').val()) !== 1) {
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
 * [prepareExchangedData description]
 * @param  {[type]} data [description]
 * @param  {[type]} type [description]
 * @param  {[type]} key  [description]
 * @param  {[type]} fileName  [description]
 * @param  {[type]} functionName  [description]
 * @return {[type]} purify [description]
 */
function prepareExchangedData(data, type, key, fileName = '', functionName = '', purify = true)
{
    if (type === 'decode') {
        if (parseInt($('#encryptClientServerStatus').val()) === 0) {
            try {
                return purifyData($.parseJSON(data));
            }
            catch (e) {
                return jsonErrorHdl(data);
            }
        } else {
            try {
                let encryption = new Encryption();
                return purifyData(JSON.parse(encryption.decrypt(data, key)));
            }
            catch (e) {
                return jsonErrorHdl('<b>Next error occurred</b><div>' + e + '</div>'
                    + (fileName !== '' ? '<br><b>Informations:</b><div>  - File: ' + fileName + '<br>  - Function: ' + functionName + '</div>': '')
                    + '<div><br><b>Raw answer from server:</b><br>'+data+'</div>');
            }
        }
    } else if (type === 'encode') {
        if (parseInt($('#encryptClientServerStatus').val()) === 0) {
            return purify === true ? purifyData(data) : data;
        } else {
            let encryption = new Encryption();
            return purify === true ? purifyData(encryption.encrypt(data, key)) : encryption.encrypt(data, key);
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
    return sanitizeDom(
        DOMPurify.sanitize(
            textCleaned,
            {USE_PROFILES: {html:bHtml, svg:bSvg, svgFilters: bSvgFilters}}
        )
    );
}

/**
 * Permits to purify the content of an object using simplePurifier
 * Usefull for ajax answers
 * Can exclude some fields from HTML purification
 */
const htmlFields = ['description', 'desc'];
function purifyData(obj, bHtml = false, bSvg = false, bSvgFilters = false) {
    if (Array.isArray(obj)) {
        return obj.map(item => purifyData(item, bHtml, bSvg, bSvgFilters));
    } else if (typeof obj === 'object' && obj !== null) {
        let purifiedObject = {};
        for (let key in obj) {
            if (obj.hasOwnProperty(key)) {
                const forceHtml = htmlFields.includes(key);
                purifiedObject[key] = purifyData(
                    obj[key],
                    forceHtml ? true : bHtml,
                    bSvg,
                    bSvgFilters
                );
            }
        }
        return purifiedObject;
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

        console.log(currentString);
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
    newString = div.innerHTML;
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