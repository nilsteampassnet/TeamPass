/*
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      functions.js
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

/**
*   Countdown before session expiration
**/
function countdown()
{
    let DayTill;
    let hoursInDay = 24;
    let limitTen = 10;
    let oneSecondsMs = 1000;
    let theDay =  $('#temps_restant').val();
    let today = new Date(); // Create an Date Object that contains today's date.
    let second = Math.floor(theDay - today.getTime() / oneSecondsMs);
    let minute = Math.floor(second / hourInMinutes); // Devide 'second' into 60 to get the minute
    let hour = Math.floor(minute / hourInMinutes); // Devide 'minute' into 60 to get the hour
    let CHour= hour % hoursInDay; // Correct hour, after devide into 24, the remainder deposits here.
    if (CHour < limitTen) {
        CHour = '0' + CHour;
    }
    let CMinute= minute % hourInMinutes; // Correct minute, after devide into 60, the remainder deposits here.
    if (CMinute < limitTen) {
        CMinute = '0' + CMinute;
    }
    let CSecond= second % hourInMinutes; // Correct second, after devide into 60, the remainder deposits here.
    if (CSecond < limitTen) {
        CSecond = '0' + CSecond;
    }
    DayTill = CHour+':'+CMinute+':'+CSecond;

    // Session will soon be closed
    if (DayTill === '00:00:50') {
        showExtendSession();
        $('#countdown').css('color','red');
    }

    // Manage end of session
    if ($('#temps_restant').val() !== '' && DayTill <= '00:00:00' && parseInt($('#please_login').val()) !== 1) {
        $('#please_login').val('1');
        $(location).attr('href','index.php?session=expired');
    }

    //Rewrite the string to the correct information.
    if ($('#countdown')) {
        $('#countdown').html(DayTill); // Make the particular form chart become 'Daytill'
    }

    //Create the timer 'counter' that will automatic restart function countdown() again every second.
    $(this).delay(1000).queue(function()
    {
        countdown();
        $(this).dequeue();
    });
}


/**
*   Generate a random string
**/
function CreateRandomString(size,type) {
    let chars = '';

    // CHoose what kind of string we want
    if (type === 'num') {
        chars = '0123456789';
    } else if (type === 'num_no_0') {
        chars = '123456789';
    } else if (type === 'alpha') {
        chars = 'ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz';
    } else if (type === 'secure') {
        chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz&#@;!+-$*%';
    } else {
        chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz';
    }

    // Generate it
    let randomstring = '';
    for (let i=0; i<size; i++) {
        let rnum = Math.floor(Math.random() * chars.length);
        randomstring += chars.substring(rnum, rnum+1);
    }

    //return
    return randomstring;
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
*   Send email
**/
function SendMail(category, contentEmail, keySent, message) {
    $.post(
        'sources/items.queries.php',
        {
            type    : 'send_email',
            cat     : category,
            content : contentEmail,
            key     : keySent
        },
        function(data) {
            if (typeof data[0].error !== 'undefined' && data[0].error !== '') {
                message = data[0].message;
            }
            $('#div_dialog_message_text').html(message);
            $('#div_dialog_message').dialog('open');
        },
        'json'
    );
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
 * [aesEncrypt description]
 * @param  {[type]} text [description]
 * @param  {[type]} key  [description]
 * @return {[type]}      [description]
 */
function aesEncrypt(text, key)
{
    return Aes.Ctr.encrypt(text, key, 256);
}

/**
 * [aesDecrypt description]
 * @param  {[type]} text [description]
 * @param  {[type]} key  [description]
 * @return {[type]}      [description]
 */
function aesDecrypt(text, key)
{
    return Aes.Ctr.decrypt(text, key, 256);
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
 * @return {[type]}      [description]
 */
function prepareExchangedData(data, type, key)
{
    if (type === 'decode') {
        if (parseInt($('#encryptClientServer').val()) === 0) {
            try {
                return $.parseJSON(data);
            }
            catch (e) {
                return jsonErrorHdl((data));
            }
        } else {
            try {
                return $.parseJSON(aesDecrypt(data, key));
            }
            catch (e) {
                return jsonErrorHdl((data));
            }
        }
    } else if (type === 'encode') {
        if (parseInt($('#encryptClientServer').val()) === 0) {
            return stripHtml(data);//data;
        } else {
            return aesEncrypt(data, key);
        }
    } else {
        return false;
    }
}

/**
 * Returns the text from a HTML string
 * 
 * @param {html} String The html string
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
 * @param string data Crypted string
 * @param string key  Session key
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
 * @param string data Crypted string
 * @param string key  Session key
 */
function decodeQueryReturn(data, key)
{
    try {
        data = prepareExchangedData(data , "decode", key);
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

    return data;
}

/**
 * 
 * @param {string} action 
 * @param {string} name 
 * @param {array} data 
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
 * @param {string} modalId 
 * @param {string} title 
 * @param {string} body 
 * @param {string} actionButton 
 * @param {string} closeButton 
 * @param {string} xlSize 
 * @param {string} warningModal 
 * @param {string} closeCross 
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
 * @param {string} str  
 */
function htmlEncode(str){
    return String(str).replace(/[^\w. ]/gi, function(c){
        return '&#'+c.charCodeAt(0)+';';
    });
}