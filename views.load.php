<?php
/**
 * @file 		views.load.php
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

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');


?>

<script type="text/javascript">
function GenererLog(){
    LoadingPage();
    $.post(
	    "sources/views.queries.php",
	    {
	        type    : "log_generate",
			date 	: $("#log_jours").val()
	    },
	    function(data){
	        $("#lien_pdf").html(data[0].text);
	    	$("#div_loading").hide();
	    },
	    "json"
	);
}

function ListerElemDel(){
    LoadingPage();
    $.post(
	    "sources/views.queries.php",
	    {
	        type    : "lister_suppression"
	    },
	    function(data){
	    	$("#liste_elems_del").html(data[0].text);
	    	$('#item_deleted_select_all').click(
	    		function(){
	    			if ( $('#item_deleted_select_all').attr('checked') ) {
	    				$("input[type='checkbox']:not([disabled='disabled'])").attr('checked', true);
	    			} else {
	    				$("input[type='checkbox']:not([disabled='disabled'])").removeAttr('checked');
	    			}
	    		}
	    	);
	    	$("#div_loading").hide();
	    },
	    "json"
	);
}

function restoreDeletedItems(){
    if ( confirm("<?php echo $txt['views_confirm_restoration'];?>") ){
        var list_i = "";
        $(".cb_deleted_item:checked").each(function() {
            if ( list_i == "" ) list_i = $(this).val();
            else list_i = list_i+';'+$(this).val();
        });
        var list_f = "";
        $(".cb_deleted_folder:checked").each(function() {
            if ( list_f == "" ) list_f = $(this).val();
            else list_f = list_f+';'+$(this).val();
        });

		$.post(
		    "sources/views.queries.php",
		    {
		        type    : "restore_deleted__items",
		        list_i	: list_i,
		        list_f	: list_f
		    },
		    function(data){
		    	window.location.href = "index.php?page=manage_views";
		    }
		);
    }
}

function reallyDeleteItems(){
    if ( confirm("<?php echo $txt['views_confirm_items_deletion'];?>") ){
        var list_items = "";
        $(".cb_deleted_item:checked").each(function() {
            if ( list_items == "" ) list_items = $(this).val();
            else list_items = list_items+';'+$(this).val();
        });

        var list_folders = "";
        $(".cb_deleted_folder:checked").each(function() {
            if ( list_folders == "" ) list_folders = $(this).val();
            else list_folders = list_folders+';'+$(this).val();
        });

		$.post(
		    "sources/views.queries.php",
		    {
		        type    : "really_delete_items",
		        items	: list_items,
		        folders	: list_folders
		    },
		    function(data){
		    	window.location.href = "index.php?page=manage_views";
		    }
		);
    }
}

function displayLogs(type, page, order){
	if(type == "reorder"){
		type = $("#type_log_displayed").val();
		page = $("#log_page_displayed").val();
		if($("#log_direction_displayed").val() == "ASC") $("#log_direction_displayed").val("DESC");
		else $("#log_direction_displayed").val("ASC")
	}else{
		$("#type_log_displayed").val(type);
		$("#log_page_displayed").val(page);
		if(type != $("#type_log_displayed").val())
			$("#log_direction_displayed").val("ASC");
	}

	if(order == "") order = "date";

	var filter = "";
	$("#div_show_system_logs").show();
    //Show or not the column URL
    if ( type == "errors_logs" ) $("#th_url").show();
    else $("#th_url").hide();
    if ( type == "access_logs" ||type == "copy_logs" ){
    	$("#filter_access_logs_div").show();
    	filter = $("#filter_access_logs").val();
    }else $("#filter_access_logs_div").hide();

    $.post(
	    "sources/views.queries.php",
	    {
	        type    : type,
	        page	: page,
	        filter	: filter,
	        order	: order,
	        direction:	$("#log_direction_displayed").val()
	    },
	    function(data){
    		$("#tbody_logs").empty().append(data[0].tbody_logs);
    		$("#log_pages").empty().append(data[0].log_pages);
	    },
	    "json"
	);
}

//This permits to launch ajax query for generate a listing of expired items
function generate_renewal_listing(){
    LoadingPage();

    $.post(
	    "sources/views.queries.php",
	    {
	        type    : "generate_renewal_listing",
	        period	: $("#expiration_period").val()
	    },
	    function(data){
    		$("#list_renewal_items").html(data[0].text);
    		$("#list_renewal_items_pdf").val(data[0].pdf);
    		$("#renewal_icon_pdf").show();
    		$("#div_loading").hide();
	    },
	    "json"
	);
}

//FUNCTION permits to generate a PDF file
function generate_renewal_pdf(){
    LoadingPage();

    $.post(
	    "sources/views.queries.php",
	    {
	        type    : "generate_renewal_pdf",
	        text	: $("#list_renewal_items_pdf").val()
	    },
	    function(data){
    		window.open(data[0].file, '_blank');
    		$("#div_loading").hide();
	    },
	    "json"
	);
}


$(function() {
	$("#tabs").tabs();
    $("#log_jours").datepicker({
        regional: 'fr',
        dateFormat : 'dd/mm/yy'
    });

    $( "#radio_logs" ).buttonset();

    ListerElemDel();
});

</script>