/**
 * @file 		functions.js
 * @author		Nils Laumaillé
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

function LoadingPage(){
	if ( $("#div_loading").is(':visible') )
	    $("#div_loading").hide();
	else
	    $("#div_loading").show();
}

function RefreshPage(myform){
    document.forms[myform].submit();
}

//Add 1 hour to session duration
function IncreaseSessionTime(){
	 $.post(
		"sources/main.queries.php",
		{
		type    : "increase_session_time"
		},
        function(data){
        	$("#temps_restant").val(data[0].new_value);
        	$("#date_end_session").val(data[0].new_value);
        	$('#countdown').css("color","white");
        },
        "json"
	);
}

//Countdown before session expiration
function countdown()
{
    var DayTill
    var theDay =  document.getElementById('temps_restant').value;
    var today = new Date() //Create an Date Object that contains today's date.
    var second = Math.floor(theDay - (today.getTime()/1000))
    var minute = Math.floor(second/60) //Devide "second" into 60 to get the minute
    var hour = Math.floor(minute/60) //Devide "minute" into 60 to get the hour
    CHour= hour % 24 //Correct hour, after devide into 24, the remainder deposits here.
    if (CHour<10) {CHour = "0" + CHour}
    CMinute= minute % 60 //Correct minute, after devide into 60, the remainder deposits here.
    if (CMinute<10) {CMinute = "0" + CMinute}
    CSecond= second % 60 //Correct second, after devide into 60, the remainder deposits here.
    if (CSecond<10) {CSecond = "0" + CSecond}
    DayTill = CHour+":"+CMinute+":"+CSecond

    //Avertir de la fin imminante de la session
    if ( DayTill == "00:01:00" ){
        $('#div_fin_session').dialog('open');
        document.getElementById('countdown').style.color="red";
    }

    //G?rer la fin de la session
    if ( DayTill == "00:00:00" )
        document.location = "index.php?session=expired";

    //Rewrite the string to the correct information.
    if ( document.getElementById('countdown') )
        document.getElementById('countdown').innerHTML = DayTill //Make the particular form chart become "Daytill"
    var counter = setTimeout("countdown()", 1000) //Create the timer "counter" that will automatic restart function countdown() again every second.
}



//Permits to open a dialogbox
function OpenDialog(id){
    $('#'+id).dialog('open');
}

//Permits to toggle a div
function toggleDiv(id){
    $('#'+id).toggle();
}

//Permits to check if a value is an integer
function isInteger(s) {
  return (s.toString().search(/^-?[0-9]+$/) == 0);
}

//Permits to create random strings
function CreateRandomString(size,type){
    var chars = "";

    // CHoose what kind of string we want
    if ( type == "num" ) chars = "0123456789";
    else if ( type == "num_no_0" ) chars = "123456789";
    else if ( type == "alpha" ) chars = "ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
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


function unprotectString(string){
	if(string != "" && string != null){
		string = string.replace(/\\/g,'').replace(/&#93;/g,'\\');
	}
	return string;
}

function protectString(string){
	if(string != "" && string != null){
		string = string.replace(/\\/g,'&#93;').replace(/"/g,"&quot;");
	}
	return string;
}