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
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
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
 * @return {[type]}      [description]
 */
function prepareExchangedData(data, type, key, fileName = '', functionName = '')
{
    if (type === 'decode') {
        if (parseInt($('#encryptClientServer').val()) === 0) {
            try {
                return $.parseJSON(data);
            }
            catch (e) {
                return jsonErrorHdl(data);
            }
        } else {
            try {
                let encryption = new Encryption();
                return JSON.parse(encryption.decrypt(data, key));
            }
            catch (e) {
                return jsonErrorHdl('<b>Next error occurred</b><div>' + e + '</div>'
                    + (fileName !== '' ? '<br><b>Informations:</b><div>  - File: ' + fileName + '<br>  - Function: ' + functionName + '</div>': '')
                    + '<div><br><b>Raw answer from server:</b><br>'+data+'</div>');
            }
        }
    } else if (type === 'encode') {
        if (parseInt($('#encryptClientServer').val()) === 0) {
            return stripHtml(data);
        } else {
            let encryption = new Encryption();
            return encryption.encrypt(data, key);
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
 * Returns the text from a HTML string
 * 
 * @param {string} String The html string
 */
function stripHtml(html) {
    // Create a new div element
    let temporalDivElement = document.createElement('div');
    // Set the HTML content with the providen
    temporalDivElement.innerHTML = html;
    // Retrieve the text property of the element (cross-browser support)
    return temporalDivElement.textContent || temporalDivElement.innerText || '';
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