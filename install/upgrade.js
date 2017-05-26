/**
 * @file 		  upgrade.js
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2011 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// Function - do a pause during javascript execution
function PauseInExecution(millis)
{
    var date = new Date();
    var curDate = null;

    do { curDate = new Date(); }
    while(curDate-date < millis);
}

//Fonction qui permet d'appeler un fichier qui ex�cute une requete pass�e en parametre
function httpRequest(file,data,type) {
    var xhr_object = null;
    var is_chrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
    
    if (document.getElementById("menu_action") != null) {
        document.getElementById("menu_action").value = "action";
    }

    if(window.XMLHttpRequest) { // Firefox
        xhr_object = new XMLHttpRequest();
    } else if(window.ActiveXObject) { // Internet Explorer
        xhr_object = new ActiveXObject("Microsoft.XMLHTTP");  //Info IE8 now supports =>  xhr_object = new XMLHttpRequest()
    } else { // XMLHttpRequest non support? par le navigateur
        alert("Your browser does not support XMLHTTPRequest objects ...");
        return;
    }

    if (type == "GET") {
        xhr_object.open("GET", file+"?"+data, true);
        xhr_object.send(null);
    } else {
        xhr_object.open("POST", file, true);
        xhr_object.onreadystatechange = function() {
            if(xhr_object.readyState == 4) {
                eval(xhr_object.responseText);
                //Check if query is for user identification. If yes, then reload page.
                if (data != "" && data !== undefined && data.indexOf('ype=identify_user') > 0 ) {
                    if (is_chrome == true ) PauseInExecution(100);  //Needed pause for Chrome
                    if (type == "") {
                        if (document.getElementById('erreur_connexion').style.display == "") {
                            //rise an error in url. This in order to display the eror after refreshing
                            window.location.href="index.php?error=rised";
                        } else {
                            window.location.href="index.php";
                        }
                    } else {
                        if (type = "?error=rised") {
                            if (document.getElementById('erreur_connexion').style.display == "none") type = "";   //clean error in url
                            else type = "?error=rised"; //Maintain the ERROR
                        }
                        window.location.href="index.php"+type;
                    }
                }
            }
        }
        xhr_object.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=utf-8");
        xhr_object.send(data);
    }
}