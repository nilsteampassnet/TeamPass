/**
 * @file 		  upgrade.js
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2011 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// Function - do a pause during javascript execution
function pauseInExecution(millis)
{
    var date = new Date();
    var curDate = null;

    do {
        curDate = new Date();
    } while(curDate-date < millis);
}

/**
 * Execute a file
 * @param  {[type]} file [description]
 * @param  {[type]} data [description]
 * @param  {[type]} type [description]
 * @return {[type]}      [description]
 */
function httpRequest(file, data, type) {
    var xhrObject = null;
    var isChrome = navigator.userAgent.toLowerCase().indexOf("chrome") > -1;

    if (document.getElementById("menu_action") !== null) {
        document.getElementById("menu_action").value = "action";
    }

    if(window.XMLHttpRequest) {
        // Firefox
        xhrObject = new XMLHttpRequest();
    } else if(window.ActiveXObject) {
        // Internet Explorer
        xhrObject = new ActiveXObject("Microsoft.XMLHTTP");
    } else {
        // XMLHttpRequest not supported by browser
        alert("Your browser does not support XMLHTTPRequest objects ...");
        return;
    }

    if (type === "GET") {
        xhrObject.open("GET", file + "?" + data, true);
        xhrObject.send(null);
    } else {
        xhrObject.open("POST", file, true);
        xhrObject.onreadystatechange = function() {
            if(xhrObject.readyState === 4) {
                eval(xhrObject.responseText);
                //Check if query is for user identification. If yes, then reload page.
                if (data !== "" && data !== undefined && data.indexOf("ype=identify_user") > 0 ) {
                    if (isChrome === true ) {
                        // Needed pause for Chrome
                        pauseInExecution(100);
                    }
                    if (type === "") {
                        if (document.getElementById("erreur_connexion").style.display === "") {
                            //rise an error in url. This in order to display the eror after refreshing
                            window.location.href = encodeURI("index.php?error=rised");
                        } else {
                            window.location.href = encodeURI("index.php");
                        }
                    } else {
                        if (type === "?error=rised") {
                            if (document.getElementById("erreur_connexion").style.display === "none") {
                                // Clean error in url
                                type = "";
                            } else {
                                // Maintain the ERROR
                                type = "?error=rised";
                            }
                        }
                        // Redirection
                        window.location.href = encodeURI("index.php" + type);
                    }
                }
            }
        }
        xhrObject.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=utf-8");
        xhrObject.send(data);
    }
}