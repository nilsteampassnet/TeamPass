<?php
session_start();
include("../includes/settings.php");
global $k;
//ENGLISH
$english_vals = array(
    array('at_modification',"Modification"),
    array('at_creation',"Creation"),
    array('at_delete',"Deletion"),
    array('at_pw',"Password changed."),
    array('at_category',"Group"),
    array('at_personnel',"Personnal"),
    array('at_description',"Description"),
    array('at_url',"Url"),
    array('at_login',"Login"),
    array('at_label',"Label")
);
//FRENCH
$french_vals = array(
    array('at_modification',"Modification"),
    array('at_creation',"Création"),
    array('at_delete',"Suppression"),
    array('at_pw',"Mot de passe changé."),
    array('at_category',"Group"),
    array('at_personnel',"Personnel"),
    array('at_description',"Description."),
    array('at_url',"Url"),
    array('at_login',"Login"),
    array('at_label',"Label")
);
//SPANISH
$spanish_vals = array(
    array('at_modification',"Modificacion"),
    array('at_creation',"Creacion"),
    array('at_delete',"Borrado"),
    array('at_pw',"Contraseña cambiada."),
    array('at_category',"Grupo"),
    array('at_personnel',"Personal"),
    array('at_description',"Descripcion."),
    array('at_url',"Url"),
    array('at_login',"Login"),
    array('at_label',"Etiqueta")
);

changeDB();
changeDB();
changeDB();

//This will permit to update DB due to major change in log_items table for 1.08 version needs.

function changeDB(){
    global $k, $spanish_vals, $french_vals, $english_vals;
    $res = mysql_query("SELECT * FROM ".$_SESSION['tbl_prefix']."log_items") or die(mysql_error());
    while($data = mysql_fetch_array($res)){
        $action = "";
        //ENGLISH
        foreach($english_vals as $lang){
            if($lang[1] == $data['action']){
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET action = '".$lang[0]."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user'] );
                $found = true;
                $action = $lang[0];
            }
            if($lang[1] == $data['raison'] && !empty($data['raison'])){
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET raison = '".$lang[0]."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$data['action']."'" );
                $found = true;
            }else 
            if($lang[1] == trim(substr($data['raison'],0,strpos($data['raison'],":"))) && !empty($data['raison']) ){
                $data1= mysql_fetch_row(mysql_query("SELECT action FROM ".$_SESSION['tbl_prefix']."log_items WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$action."'"));
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET raison = '".$lang[0]." ".substr($data['raison'],strpos($data['raison'],":"))."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$data1[0]."'" );
                $found = true;
            }
        }
        
        //FRENCH    
        $action = "";
        foreach($french_vals as $lang){
            if($lang[1] == $data['action']){
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET action = '".$lang[0]."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user'] );
                $found = true;
                $action = $lang[0];
            }
            if($lang[1] == $data['raison'] && !empty($data['raison'])){
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET raison = '".$lang[0]."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$data['action']."'" );
                $found = true;
            }else 
            if($lang[1] == trim(substr($data['raison'],0,strpos($data['raison'],":"))) && !empty($data['raison']) ){
                $data1= mysql_fetch_row(mysql_query("SELECT action FROM ".$_SESSION['tbl_prefix']."log_items WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$action."'"));
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET raison = '".$lang[0]." ".substr($data['raison'],strpos($data['raison'],":"))."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$data1[0]."'" );
                $found = true;
            }
        }
        
        //SPANISH   
        $action = ""; 
        foreach($spanish_vals as $lang){
            if($lang[1] == $data['action']){
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET action = '".$lang[0]."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user'] );
                $found = true;
                $action = $lang[0];
            }
            if($lang[1] == $data['raison'] && !empty($data['raison'])){
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET raison = '".$lang[0]."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$data['action']."'" );
                $found = true;
            }else 
            if($lang[1] == trim(substr($data['raison'],0,strpos($data['raison'],":"))) && !empty($data['raison']) ){
                $data1= mysql_fetch_row(mysql_query("SELECT action FROM ".$_SESSION['tbl_prefix']."log_items WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$action."'"));
                mysql_query("UPDATE ".$_SESSION['tbl_prefix']."log_items SET raison = '".$lang[0]." ".substr($data['raison'],strpos($data['raison'],":"))."' WHERE id_item=".$data['id_item']." AND date =".$data['date']." AND id_user =".$data['id_user']." AND raison ='".$data['raison']."' AND action ='".$data1[0]."'" );
                $found = true;
            }
        }
    }
}

?>
