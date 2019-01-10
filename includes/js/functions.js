/**
 * @package       functions.js
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */


/**
*   Add 1 hour to session duration
**/
function IncreaseSessionTime(messageEnd, duration){
    duration = duration || 60;
    $.post(
        "sources/main.queries.php",
        {
        type     : "increase_session_time",
        duration : parseInt(duration) * 60
        },
        function(data){
            if (data[0].new_value !== "expired") {
                $("#temps_restant").val(data[0].new_value);
                $("#date_end_session").val(data[0].new_value);
                $("#countdown").css("color","white");
                alertify
                    .success(messageEnd, 2)
                    .dismissOthers();
            } else {
                $(location).attr("href","index.php?session=expired");
            }
        },
        "json"
    );
}

/**
*   Countdown before session expiration
**/
function countdown()
{
    var DayTill;
    var theDay =  $("#temps_restant").val();
    var today = new Date(); //Create an Date Object that contains today's date.
    var second = Math.floor(theDay - (today.getTime()/1000));
    var minute = Math.floor(second/60); //Devide "second" into 60 to get the minute
    var hour = Math.floor(minute/60); //Devide "minute" into 60 to get the hour
    var CHour= hour % 24; //Correct hour, after devide into 24, the remainder deposits here.
    if (CHour<10) {
        CHour = "0" + CHour;
    }
    var CMinute= minute % 60; //Correct minute, after devide into 60, the remainder deposits here.
    if (CMinute<10) {
        CMinute = "0" + CMinute;
    }
    var CSecond= second % 60; //Correct second, after devide into 60, the remainder deposits here.
    if (CSecond<10) {
        CSecond = "0" + CSecond;
    }
    DayTill = CHour+":"+CMinute+":"+CSecond;

    // Session will soon be closed
    if (DayTill === "00:00:50") {
        showExtendSession();
        $("#countdown").css("color","red");
    }

    // Manage end of session
    if ($("#temps_restant").val() !== "" && DayTill <= "00:00:00" && $("#please_login").val() !== "1") {
        $("#please_login").val("1");
        $(location).attr('href',"index.php?session=expired");
    }

    //Rewrite the string to the correct information.
    if ($("#countdown")) {
        $("#countdown").html(DayTill); //Make the particular form chart become "Daytill"
    }

    //Create the timer "counter" that will automatic restart function countdown() again every second.
    $(this).delay(1000).queue(function() {
        countdown();
        $(this).dequeue();
    });
}

/**
*   Open a dialog
**/
function OpenDialog(id){
    $("#"+id).dialog("open");
}


/**
*   Checks if value is an integer
**/
function isInteger(s) {
  return (s.toString().search(/^-?[0-9]+$/) === 0);
}

/**
*   Generate a random string
**/
function CreateRandomString(size,type){
    var chars = "";

    // CHoose what kind of string we want
    if (type === "num") {
        chars = "0123456789";
    } else if (type === "num_no_0") {
        chars = "123456789";
    } else if (type === "alpha") {
        chars = "ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
    } else if (type === "secure") {
        chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz&#@;!+-$*%";
    } else {
        chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
    }

    //generate it
    var randomstring = "";
    for (var i=0; i<size; i++) {
        var rnum = Math.floor(Math.random() * chars.length);
        randomstring += chars.substring(rnum,rnum+1);
    }

    //return
    return randomstring;
}


/**
*
**/
function unsanitizeString(string){
    if(string !== "" && string !== null){
        string = string.replace(/\\/g,"").replace(/&#92;/g,"\\").replace(/&quot;/g, '"');
    }
    return string;
}

/**
*   Clean up a string and delete any scripting tags
**/
function sanitizeString(string){
    if(string !== "" && string !== null && string !== undefined) {
        string = string.replace(/\\/g,"&#92;").replace(/"/g,"&quot;");
        string = string.replace(new RegExp("\\s*<script[^>]*>[\\s\\S]*?</script>\\s*","ig"), "");
    }
    return string;
}

/**
*   Send email
**/
function SendMail(category, contentEmail, keySent, message){
    $.post(
        "sources/items.queries.php",
        {
            type    : "send_email",
            cat     : category,
            content : contentEmail,
            key     : keySent
        },
        function(data){
            if (typeof data[0].error !== "undefined" && data[0].error !== "") {
                message = data[0].message;
            }
            $("#div_dialog_message_text").html(message);
            $("#div_dialog_message").dialog("open");
        },
        "json"
    );
}


/**
*   Checks if URL has expected format
**/
function validateURL(url) {
    var urlregex = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
    return urlregex.test(url);
}


function split( val ) {
    return val.split( / \s*/ );
}

function extractLast( term ) {
    return split( term ).pop();
}


function storeError(messageError, dialogDiv, textDiv)
{
    //Store error in DB
    $.post(
        "sources/main.queries.php",
        {
            type    : "store_error",
            error   : escape(messageError)
        }
    );
    //Display    
    var pre = document.createElement('pre');
    //custom style.
    pre.style.maxHeight = "400px";
    pre.style.margin = "0";
    pre.style.padding = "24px";
    pre.style.whiteSpace = "pre-wrap";
    pre.style.textAlign = "justify";
    pre.appendChild(document.createTextNode(
        "An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+messageError
    ));
    //show as confirm
    alertify.alert().set({labels:{ok:'Accept', cancel: 'Decline'}, padding: false});
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
    //Display    
    var pre = document.createElement('pre');
    //custom style.
    pre.style.maxHeight = "400px";
    pre.style.margin = "0";
    pre.style.padding = "24px";
    pre.style.whiteSpace = "pre-wrap";
    pre.style.textAlign = "justify";
    pre.appendChild(document.createTextNode(
        message
    ));
    //show as confirm
    alertify.alert().set({labels:{ok:'Accept', cancel: 'Decline'}, padding: false});
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
    var jsonResult;
    if (type === "decode") {
        if ($("#encryptClientServer").val() === "0") {
            try {
                return $.parseJSON(data);
            }
            catch (e) {
                return "Error: " + jsonErrorHdl(e);
            }
        } else {
            try {
                return $.parseJSON(aesDecrypt(data, key));
            }
            catch (e) {
                return "Error: " + jsonErrorHdl(e);
            }
        }
    } else if (type === "encode") {
        if ($("#encryptClientServer").val() === "0") {
            return data;
        } else {
            return aesEncrypt(data, key);
        }
    } else {
        return false;
    }
}


/**
 * Make blinking an HMLT element
 * @param  {[type]} elem  [description]
 * @param  {[type]} times [description]
 * @param  {[type]} speed [description]
 * @param  {[type]} klass [description]
 * @return {[type]}       [description]
 */
function blink(elem, times, speed, klass)
{
    if (times > 0 || times < 0) {
        if ($(elem).hasClass(klass)) {
            $(elem).removeClass(klass);
        } else {
            $(elem).addClass(klass);
        }
    }

    clearTimeout(function() { blink(elem, times, speed, klass); });

    if (times > 0 || times < 0) {
        $(this).delay(speed).queue(function() {
            blink(elem, times, speed, klass);
            $(this).dequeue();
        });
        times-= .5;
    }
}

function showAlertify(msg, delay = '', position = '', type = '')
{
    alertify.set(
        'notifier',
        'position',
        position === '' ? 'top-center' : position
    );

    if (type === 'warning' || type === '') {
        alertify
            .warning(
                msg,
                delay === '' ? 5 : delay
            )
            .dismissOthers();
    } else if (type === 'success') {
        alertify
            .success(
                msg,
                delay === '' ? 5 : delay
            )
            .dismissOthers();
    } else if (type === 'message') {
        alertify
            .message(
                msg,
                delay === '' ? 5 : delay
            )
            .dismissOthers();
    } else if (type === 'notify') {
        alertify
            .notify(
                msg,
                delay === '' ? 5 : delay
            )
            .dismissOthers();
    }
    
}

/**
 * 
 * @param string data Crypted string
 * @param string key  Session key
 */
function unCryptData(data, key)
{
    if (data.substr(0, 7) === 'crypted') {
        var uncryptedData = prepareExchangedData(
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
        alertify
            .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
            .dismissOthers();
        return false;
    }

    return data;
}

/**
 * 
 */
function teampassStorage1(storageName, type, fieldNames)
{
    // Create if not existing
    if (localStorage.getItem(storageName)  === null) {
        localStorage.setItem(storageName, JSON.stringify([]));
    }
    var storage = (localStorage.getItem(storageName) === '') ? {} : JSON.parse(localStorage.getItem(storageName));

    if (type === 'update') {
        console.log('--- CONTENU DE STORAGE '+storageName)
        console.log(storage)
        // Loop
        $.each(fieldNames, function(key, value) {
            storage[key] = value;
        });
        console.log(storage)
              
        // Update in storage
        localStorage.setItem(
            storageName,
            JSON.stringify(storage)
        );
        console.info('--- '+storageName+" was UPDATED")
        return true;
    } else if (type === 'get') {
        console.info('--- GET from'+storageName)
        console.log(storage)
        /*if (fieldNames.length === 1 && storage[fieldNames] !== undefined) {
            var ret = storage[fieldNames]
        } else {*/
            var ret = [];
            
            // Loop
            $.each(fieldNames, function(index, value) {
                console.log('>>'+storage[value]);
                if (storage[value] !== undefined) {
                    ret[value] = storage[value];
                } else {
                    ret[value] = '';
                }
            });
        //}
        console.log(ret)
        return ret;
    } else {
        
    }
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
