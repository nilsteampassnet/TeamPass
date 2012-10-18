<?php
/**
 * @file 		class.database.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

# Name: Database.class.php
# File Description: MySQL Class to allow easy and clean access to common mysql commands
# Author: ricocheting
# Web: http://www.ricocheting.com/
# Update: 2009-12-17
# Version: 2.2.2
# Copyright 2003 ricocheting.com

/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

#session_start();

###################################################################################################
###################################################################################################
###################################################################################################
class database
{
#######################
//internal info
var $error = "";
var $errno = 0;

//number of rows affected by SQL query
var $affected_rows = 0;

var $link_id = 0;
var $query_id = 0;

#-#############################################
# desc: constructor
function Database($server, $user, $pass, $database, $pre='')
{
    $this->server=$server;
    $this->user=$user;
    $this->pass=$pass;
    $this->database=$database;
    $this->pre=$pre;
}#-#constructor()

#-#############################################
# desc: connect and select database using vars above
# Param: $new_link can force connect() to open a new link, even if mysql_connect() was called before with the same parameters
function connect($new_link=false)
{
    $this->link_id=@mysql_connect($this->server,$this->user,$this->pass,$new_link);

    if (!$this->link_id) {//open failed
        $this->oops("Could not connect to server: <b>$this->server</b>.");
        }

    if (!@mysql_select_db($this->database, $this->link_id)) {//no database
        $this->oops("Could not open database: <b>$this->database</b>.");
        }

    mysql_query("SET NAMES UTF8");
    mysql_query("SET CHARACTER SET 'utf8'");

    // unset the data so it can't be dumped
    $this->server='';
    $this->user='';
    $this->pass='';
    $this->database='';
}#-#connect()


#-#############################################
# desc: close the connection
function close()
{
    if (!@mysql_close($this->link_id)) {
        $this->oops("Connection close failed.");
    }
}#-#close()


#-#############################################
# Desc: escapes characters to be mysql ready
# Param: string
# returns: string
function escape($string)
{
    if(get_magic_quotes_runtime()) $string = stripslashes($string);

    return @mysql_real_escape_string($string,$this->link_id);
}#-#escape()


#-#############################################
# Desc: executes SQL query to an open connection
# Param: (MySQL query) to execute
# returns: (query_id) for fetching results etc
function query($sql)
{
    // do query
    $this->query_id = @mysql_query($sql, $this->link_id);

    if (!$this->query_id) {
        $this->oops("<b>MySQL Query fail:</b> $sql");

        return 0;//$this->oops("<b>MySQL Query fail:</b> $sql");
    }

    $this->affected_rows = @mysql_affected_rows($this->link_id);

    return $this->query_id;
}#-#query()


#-#############################################
# desc: fetches and returns results one line at a time
# param: query_id for mysql run. if none specified, last used
# return: (array) fetched record(s)
function fetch_array($query_id=-1)
{
    // retrieve row
    if ($query_id!=-1) {
        $this->query_id=$query_id;
    }

    if (isset($this->query_id)) {
        $record = @mysql_fetch_assoc($this->query_id);
    } else {
        $this->oops("Invalid query_id: <b>$this->query_id</b>. Records could not be fetched.");
    }

    return $record;
}#-#fetch_array()


#-#############################################
# desc: fetches and returns results one line at a time
# param: query_id for mysql run. if none specified, last used
# return: (array) fetched record(s)
function fetch_row($sql)
{
    // retrieve row
    $query_id = $this->query($sql);

    $record = @mysql_fetch_row($this->query_id);

    $this->free_result($query_id);

    return $record;
}#-#fetch_array()


#-#############################################
# desc: returns all the results (not one row)
# param: (MySQL query) the query to run on server
# returns: assoc array of ALL fetched results
function fetch_all_array($sql)
{
    $query_id = $this->query($sql);
    $out = array();

    while ($row = $this->fetch_array($query_id, $sql)) {
        $out[] = $row;
    }

    $this->free_result($query_id);

    return $out;
}#-#fetch_all_array()


#-#############################################
# desc: frees the resultset
# param: query_id for mysql run. if none specified, last used
function free_result($query_id=-1)
{
    if ($query_id!=-1) {
        $this->query_id=$query_id;
    }
    if ($this->query_id!=0 && !@mysql_free_result($this->query_id)) {
        $this->oops("Result ID: <b>$this->query_id</b> could not be freed.");
    }
}#-#free_result()


#-#############################################
# desc: does a query, fetches the first row only, frees resultset
# param: (MySQL query) the query to run on server
# returns: array of fetched results
function query_first($query_string)
{
    $query_id = $this->query($query_string);
    $out = $this->fetch_array($query_id);
    $this->free_result($query_id);

    return $out;
}#-#query_first()


#-#############################################
# desc: does an update query with an array
# param: table (no prefix), assoc array with data (doesn't need escaped), where condition
# returns: (query_id) for fetching results etc
function query_update($table, $data, $where='1')
{
    $q="UPDATE `".$this->pre.$table."` SET ";

    foreach ($data as $key=>$val) {
        if(strtolower($val)=='null') $q.= "`$key` = NULL, ";
        elseif(strtolower($val)=='now()') $q.= "`$key` = NOW(), ";
        else $q.= "`$key`='".$this->escape($val)."', ";
    }

    if (is_array($where)) {
        $w = "";
        foreach ($where as $key=>$val) {
            if(strtolower($val)=='null') $w.= "`$key` = NULL, ";
            elseif(strtolower($val)=='now()') $w.= "`$key` = NOW(), ";
            else $w.= "`$key`='".$this->escape($val)."' AND ";
        }
        $q = rtrim($q, ', ') . ' WHERE '. rtrim($w, ' AND ') .';';
    } else {
        $q = rtrim($q, ', ') . ' WHERE '.$where.';';
    }

    return $this->query($q);
}#-#query_update()

#-#############################################
# desc: does an insert query with an array
# param: table (no prefix), assoc array with data
# returns: id of inserted record, false if error
function query_insert($table, $data)
{
    $q="INSERT INTO `".$this->pre.$table."` ";
    $v=''; $n='';

    foreach ($data as $key=>$val) {
        $n.="`$key`, ";
        if(strtolower($val)=='null') $v.="NULL, ";
        elseif(strtolower($val)=='now()') $v.="NOW(), ";
        else $v.= "'".$this->escape($val)."', ";
    }

    $q .= "(". rtrim($n, ', ') .") VALUES (". rtrim($v, ', ') .");";

    if ($this->query($q)) {
        //$this->free_result();
        return mysql_insert_id($this->link_id);
    } else return false;

}#-#query_insert()

    #-#############################################
    # desc: does a delete query with an array
    # param: table (no prefix), assoc array with data (doesn't need escaped), where condition
    # returns: (query_id) for fetching results etc
function query_delete($table, $data, $where='1')
{
    $q="DELETE FROM `".$this->pre.$table."` WHERE ";

    foreach ($data as $key=>$val) {
        if(strtolower($val)=='null') $q.= "`$key` = NULL, ";
        elseif(strtolower($val)=='now()') $q.= "`$key` = NOW(), ";
        else $q.= "`$key`='".$this->escape($val)."' AND ";
    }

    $q = rtrim($q, ' AND ').';';

    return $this->query($q);
}

#-#############################################
# desc: throw an error message
# param: [optional] any custom error to display
function oops($msg='')
{
    if ($this->link_id>0) {
        $this->error=mysql_error($this->link_id);
        $this->errno=mysql_errno($this->link_id);
    } else {
        $this->error=mysql_error();
        $this->errno=mysql_errno();
    }
    if ($this->errno != "1049") {
        @mysql_query("INSERT INTO ".$this->pre."log_system SET
            date=".mktime(date("h"),date("i"),date("s"),date("m"),date("d"),date("y")).",
            qui=".$_SESSION['user_id'].",
            label='".$msg."<br />".addslashes($this->error)."@".$_SERVER['REQUEST_URI']."',
            type='error'"
        );

        //Show Error
        //echo '$("#mysql_error_warning").html("'.str_replace(array(CHR(10),CHR(13)),array('\n','\n'),$msg).'<br />'.str_replace(array(CHR(10),CHR(13)),array('\n','\n'),addslashes($this->error)).'");';
        //echo '$("#div_mysql_error").dialog("open");';
        return str_replace(array(CHR(10),CHR(13)),array('\n','\n'),$msg).'<br />'.str_replace(array(CHR(10),CHR(13)),array('\n','\n'),addslashes($this->error));
        exit;
    } else {
        //DB connection error
        echo '
        <div style="float:left; width:400px; margin-left:30%; margin-top:10%">
            <div style="background-color:#E0E0E0; padding:30px; font-size:16px; font-weight:bold;">
                Database ERROR!<br />No connection to database is possible!<br />
                Error rised by server is:<br />
                <i>'.str_replace(array(CHR(10),CHR(13)),array('\n','\n'),addslashes($this->error)).'</i><br />
                Please, check your settings.php file.
            </div>
        </div>';
        exit;
    }
}#-#oops()

}//CLASS Database
###################################################################################################
