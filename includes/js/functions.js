/**
 * @file 		  functions.js
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link    	  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
*	Show or hide Loading animation GIF
**/
function LoadingPage(){
	if ( $("#div_loading").is(':visible') )
	    $("#div_loading").hide();
	else
	    $("#div_loading").show();
}

/**
*	Reload a page
**/
function RefreshPage(myform){
    document.forms[myform].submit();
}

/**
*	Add 1 hour to session duration
**/
function IncreaseSessionTime(){
	 $.post(
		"sources/main.queries.php",
		{
		type    : "increase_session_time"
		},
        function(data){
			if (data[0].new_value != "expired") {
	        	$("#temps_restant").val(data[0].new_value);
	        	$("#date_end_session").val(data[0].new_value);
	        	$('#countdown').css("color","white");
			} else {
				document.location = "index.php?session=expired";
			}
        },
        "json"
	);
}

/**
*	Countdown before session expiration
**/
function countdown()
{
    var DayTill
    //var theDay =  document.getElementById('temps_restant').value;
    var theDay =  $('#temps_restant').val();
    var today = new Date(); //Create an Date Object that contains today's date.
    var second = Math.floor(theDay - (today.getTime()/1000));
    var minute = Math.floor(second/60); //Devide "second" into 60 to get the minute
    var hour = Math.floor(minute/60); //Devide "minute" into 60 to get the hour
    CHour= hour % 24; //Correct hour, after devide into 24, the remainder deposits here.
    if (CHour<10) {CHour = "0" + CHour;}
    CMinute= minute % 60; //Correct minute, after devide into 60, the remainder deposits here.
    if (CMinute<10) {CMinute = "0" + CMinute;}
    CSecond= second % 60; //Correct second, after devide into 60, the remainder deposits here.
    if (CSecond<10) {CSecond = "0" + CSecond;}
    DayTill = CHour+":"+CMinute+":"+CSecond;

    //Avertir de la fin imminante de la session
    if ( DayTill == "00:01:00" ){
        $('#div_fin_session').dialog('open');
        document.getElementById('countdown').style.color="red";
    }

    // Manage end of session
    if ($("#temps_restant").val() != "" && DayTill <= "00:00:00" && $("#please_login").val() != 1) {
    	$("#please_login").val("1");
    	document.location = "index.php?session=expired";
    }

    //Rewrite the string to the correct information.
    if ($('#countdown')){
    	$('#countdown').html(DayTill); //Make the particular form chart become "Daytill"
    }
    var counter = setTimeout("countdown()", 1000); //Create the timer "counter" that will automatic restart function countdown() again every second.
}

/**
*	Open a dialog
**/
function OpenDialog(id){
    $('#'+id).dialog('open');
}

/**
*	Toggle a DIV
**/
function toggleDiv(id){
    $('#'+id).toggle();
    //specific case to not show upgrade alert
    if(id == "div_maintenance"){
    	$.post(
			"sources/main.queries.php",
			{
			type    : "hide_maintenance"
			}
		);
	}
}

/**
*	Checks if value is an integer
**/
function isInteger(s) {
  return (s.toString().search(/^-?[0-9]+$/) == 0);
}

/**
*	Generate a random string
**/
function CreateRandomString(size,type){
    var chars = "";

    // CHoose what kind of string we want
    if ( type == "num" ) chars = "0123456789";
    else if ( type == "num_no_0" ) chars = "123456789";
    else if ( type == "alpha" ) chars = "ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
    else if ( type == "secure" ) chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz&#@;!+-$*%";
    else chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";

    //generate it
    var randomstring = '';
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
	if(string != "" && string != null){
		string = string.replace(/\\/g,'').replace(/&#92;/g,'\\');
	}
	return string;
}

/**
*	Clean up a string and delete any scripting tags
**/
function sanitizeString(string){
	if(string != "" && string != null){
		string = string.replace(/\\/g,'&#92;').replace(/"/g,"&quot;");
		string = string.replace(new RegExp('\\s*<script[^>]*>[\\s\\S]*?</script>\\s*','ig'),'');
	}
	return string;
}

/**
*	Send email
**/
function SendMail(cat, content, key, message){
	$.post(
		"sources/items.queries.php",
		{
			type    : "send_email",
			cat     : cat,
			content	: content,
			key     : key
		},
        function(data){
        	$("#div_dialog_message_text").html(message);
        	$("#div_dialog_message").dialog("open");
        }
	);
}

/**
*	Checks if email has expected format (xxx@yyy.zzz)
**/
function IsValidEmail(email){
	var filter = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
	return filter.test(email);
}


function split( val ) {
    return val.split( / \s*/ );
}

function extractLast( term ) {
    return split( term ).pop();
}


function store_error(message_error, dialog_div, text_div){
    //Store error in DB
    $.post(
		"sources/main.queries.php",
		{
			type    : "store_error",
			error	: escape(message_error)
		}
	);
    //Display
    $("#"+text_div).html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+message_error);
	$("#"+dialog_div).dialog("open");
}

function prepareExchangedData(data, type)
{
    if (type == "decode") {
        if ($("#encryptClientServer").val() == 0) {
            return $.parseJSON(data);
        } else {
            return $.parseJSON(aes_decrypt(data));
        }
    } else if (type == "encode") {
        if ($("#encryptClientServer").val() == 0) {
            return data;
        } else {
            return aes_encrypt(data);
        }
    }
}