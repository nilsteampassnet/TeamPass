<?php
/**
 * @file          suggestion.load.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['settings']['enable_suggestion']) || $_SESSION['settings']['enable_suggestion'] != 1) {
    die('Hacking attempt...');
}

?>

<script type="text/javascript">
//<![CDATA[
    var oTable1;
    var oTable2;

    //Function opening
    function openKB(id)
    {
        $.post(
            "sources/kb.queries.php",
            {
                type    : "open_kb",
                id      : id,
                key     : "<?php echo $_SESSION['key'];?>"
            },
            function(data) {
                data = $.parseJSON(data);
                $("#kb_label").val(data.label);
                $("#kb_category").val(data.category);
                $("#kb_description").val(data.description);
                $("#kb_id").val(id);
                if (data.anyone_can_modify == 0) {
                    $("#modify_kb_no").prop("checked", true);
                } else {
                    $("#modify_kb_yes").prop("checked", true);
                }
                for (var i=0; i < data.options.length; ++i) {
                    $("#kb_associated_to option[value="+data.options[i]+"]").prop("selected", true);
                }
                $("#kb_form").dialog("open");
            }
        );
    }

    //Function deleting
    function deleteSuggestion(id)
    {
        $("#suggestion_id").val(id);
        $("#div_suggestion_delete").dialog("open");
    }

    //Function validating
    function validateSuggestion(id, suggestion_text)
    {
        $("#div_loading").show();
        $("#suggestion_id").val(id);

        // check if similar ITEM exists
        $.post(
            "sources/suggestion.queries.php",
            {
                type    : "duplicate_suggestion",
                id      : $("#suggestion_id").val(),
                key     : "<?php echo $_SESSION['key'];?>"
            },
            function(data) {
                if (data[0].status == "no") {
                    $("#suggestion_is_duplicate").hide();
                } else if (data[0].status = "duplicate") {
                    $("#suggestion_is_duplicate").show().addClass("ui-state-error");
                }
                $("#suggestion_add_label").html(suggestion_text);
                $("#div_loading").hide();
                // show dialog
                $("#div_suggestion_validate").dialog("open");
            },
            "json"
        )
    }

    /**
     * Get Item complexity
     */
    function GetRequiredComplexity()
    {
        $("#pw_wait").show();
        var funcReturned = null;
        $.ajaxSetup({async: false});
        $.post(
            "sources/suggestion.queries.php",
            {
                type        : "get_complexity_level",
                folder_id   : $("#suggestion_folder").val(),
                key     : "<?php echo $_SESSION['key'];?>"
            },
            function(data) {
                if (data[0].status == "ok") {
                    $("#complexity_required").val(data[0].complexity);
                    $("#complexity_required_text").html("[<?php echo $LANG['complex_asked'];?>&nbsp;&nbsp;<span style=\"color:#D04806; font-weight:bold;\">"+data[0].complexity_text+"</span>]");
                }
                $("#pw_wait").hide();
            },
            "json"
        );
        $.ajaxSetup({async: true});
        return funcReturned;
    }

    function showDiv(div_name) {
        $(".items_table").hide();
        $("#" + div_name).show();
    }

    function viewSuggestion(id) {
        $("#suggestion_id").val(id);
        $("#div_suggestion_view").dialog("open");
    }

    $(function() {
        $( "#tabs" ).tabs({
            create: function( event, ui ) {
                oTable1 = $("#t_suggestion").dataTable({
                    "aaSorting": [[ 1, "asc" ]],
                    "sPaginationType": "full_numbers",
                    "bProcessing": true,
                    "bDestroy": true,
                    "bServerSide": true,
                    "sAjaxSource": "sources/datatable/datatable.suggestion.php",
                    "bJQueryUI": true,
                    "oLanguage": {
                        "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
                    },
                    "columns": [
                        {"width": "7%", className: "dt-body-left"},
                        {"width": "22%"},
                        {"width": "28%"},
                        {"width": "15%"},
                        {"width": "10%"},
                        {"width": "20%"}
                    ]
                });
                oTable1.fnDraw(false);
            },
            activate: function (event, ui) {
                var act = $("#tabs").tabs("option", "active");

                if (act === 0) {
                    oTable1 = $("#t_suggestion").dataTable({
                        "aaSorting": [[ 1, "asc" ]],
                        "sPaginationType": "full_numbers",
                        "bProcessing": true,
                        "bDestroy": true,
                        "bServerSide": true,
                        "sAjaxSource": "sources/datatable/datatable.suggestion.php",
                        "bJQueryUI": true,
                        "oLanguage": {
                            "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
                        },
                        "columns": [
                            {"width": "7%", className: "dt-body-left"},
                            {"width": "22%"},
                            {"width": "28%"},
                            {"width": "15%"},
                            {"width": "10%"},
                            {"width": "20%"}
                        ]
                    });
                    oTable1.fnDraw(false);
                } else if (act === 1) {
                    oTable2 = $("#t_change").dataTable({
                        "aaSorting": [[ 1, "asc" ]],
                        "sPaginationType": "full_numbers",
                        "bProcessing": true,
                        "bDestroy": true,
                        "bServerSide": true,
                        "sAjaxSource": "sources/datatable/datatable.items_change.php",
                        "bJQueryUI": true,
                        "oLanguage": {
                            "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
                        },
                        "columns": [
                            {"width": "5%", className: "dt-body-left"},
                            {"width": "15%"},
                            {"width": "10%"},
                            {"width": "10%"},
                            {"width": "20%"},
                            {"width": "15%"},
                            {"width": "20%"}
                        ]
                    });
                    oTable2.fnDraw(false);
                }
                $('#tabs').tooltipster({multiple: true});
            }
        });

        //Dialogbox for deleting KB
        $("#div_suggestion_delete").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 300,
            height: 150,
            title: "<?php echo $LANG['suggestion_delete_confirm'];?>",
            buttons: {
                "<?php echo $LANG['del_button'];?>": function() {
                    $.post(
                        "sources/suggestion.queries.php",
                        {
                            type    : "delete_suggestion",
                            id      : $("#suggestion_id").val(),
                            key     : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            $("#div_suggestion_delete").dialog("close");
                            oTable = $("#t_suggestion").dataTable();
                            oTable.fnDraw();
                        }
                    )
                },
                "<?php echo $LANG['cancel_button'];?>": function() {
                    $(this).dialog("close");
                }
            }
        });

        //Dialogbox for validating KB
        $("#div_suggestion_validate").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 400,
            height: 240,
            title: "<?php echo $LANG['suggestion_validate_confirm'];?>",
            open: function( event, ui ) {
                $("#suggestion_edit_wait").hide();
            },
            buttons: {
                "<?php echo $LANG['confirm'];?>": function() {
                    $("#suggestion_edit_wait").show();
                    $.post(
                        "sources/suggestion.queries.php",
                        {
                            type    : "validate_suggestion",
                            id      : $("#suggestion_id").val(),
                            key     : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            if (data[0].status == "done") {
                                oTable = $("#t_suggestion").dataTable();
                                oTable.fnDraw();
                                $("#div_suggestion_validate").dialog("close");
                            } else if (data[0].status = "error_when_creating") {
                                $("#suggestion_error").show().html("<?php echo $LANG['suggestion_error_cannot_add'];?>").addClass("ui-state-error");
                            }
                        },
                        "json"
                    )
                },
                "<?php echo $LANG['cancel_button'];?>": function() {
                    $(this).dialog("close");
                }
            }
        });

        //Dialogbox for new KB
        $("#suggestion_form").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 450,
            height: 550,
            title: "<?php echo $LANG['suggestion_add'];?>",
            buttons: {
                "<?php echo $LANG['save_button'];?>": function() {
                    $("#suggestion_error").hide();
                    if ($("#suggestion_label").val() == "") {
                        $("#suggestion_label").addClass("ui-state-error");
                    } else if ($("#suggestion_pwd").val() == "") {
                        $("#suggestion_pwd").addClass("ui-state-error");
                    } else if ($("#suggestion_folder").val() == "") {
                        $("#suggestion_folder").addClass("ui-state-error");
                    } else if (parseInt($("#password_complexity").val()) < parseInt($("#complexity_required").val())) {
                        $("#suggestion_error").show().html("<?php echo $LANG['error_complex_not_enought'];?>").addClass("ui-state-error");
                    } else {
                        $("#add_suggestion_wait").show();
                        var data = '{"label":"'+sanitizeString($("#suggestion_label").val())+
                            '","password":"'+sanitizeString($("#suggestion_pwd").val())+
                            '", "description":"'+sanitizeString($("#suggestion_description").val()).replace(/\n/g, '<br />')+
                            '","folder":"'+$("#suggestion_folder").val()+
                            '","comment":"'+sanitizeString($("#suggestion_comment").val()).replace(/\n/g, '<br />')+'"}';

                        $.post("sources/suggestion.queries.php",
                            {
                                type     : "add_new",
                                data     : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                                key      : "<?php echo $_SESSION['key'];?>"
                            },
                            function(data) {
                                if (data[0].status == "done") {
                                    oTable = $("#t_suggestion").dataTable();
                                    oTable.fnDraw();
                                    $("#suggestion_form").dialog("close");
                                } else if (data[0].status = "duplicate_suggestion") {
                                    $("#suggestion_error").show().html("<?php echo $LANG['suggestion_error_duplicate'];?>").addClass("ui-state-error");
                                }
                                $("#add_suggestion_wait").hide();
                            },
                            "json"
                        );
                    }
                },
                "<?php echo $LANG['cancel_button'];?>": function() {
                    $(this).dialog("close");
                }
            },
            open:function(event, ui) {
                $("#suggestion_email, #suggestion_pwd, #suggestion_email").removeClass("ui-state-error");
                $("#add_suggestion_wait").hide();
                //empty dialogbox
                $("#suggestion_form input, #suggestion_form select, #suggestion_form textarea").val("");
                $("#password_complexity").val("0");
                $("#complexity_required_text").html("");
                $("#suggestion_pwd").focus();
                $("#suggestion_label").focus();
            }
        });

        //Dialogbox for VIEW KB
        $("#div_suggestion_view").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 700,
            height: 400,
            title: "<?php echo $LANG['suggestion_delete_confirm'];?>",
            buttons: {
                "<?php echo $LANG['approve'];?>": function() {
                    $("#suggestion_view_wait").html("<?php echo "<i class='fa fa-cog fa-spin fa-lg'></i>&nbsp;".addslashes($LANG['please_wait'])."...";?>").show();

                    // select fields to update
                    var fields_to_update = "";
                    if ($("#confirm_label-check").length !== 0) fields_to_update += "label;";
                    if ($("#confirm_pw-check").length !== 0) fields_to_update += "pw;";
                    if ($("#confirm_login-check").length !== 0) fields_to_update += "login;";
                    if ($("#confirm_url-check").length !== 0) fields_to_update += "url;";
                    if ($("#confirm_email-check").length !== 0) fields_to_update += "email;";

                    $.post(
                        "sources/suggestion.queries.php",
                        {
                            type    : "approve_item_change",
                            id      : $("#suggestion_id").val(),
                            data    : fields_to_update,
                            key     : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            if (data[0].error === "") {
                                $("#suggestion_view_wait").html(prepareMsgToDisplay("info", "done"));
                                oTable = $("#t_change").dataTable();
                                oTable.fnDraw();
                                setTimeout(
                                    function() {
                                        $("#div_suggestion_view").dialog("close");
                                    },
                                    1500
                                );
                            } else {
                                $("#suggestion_view_wait").html(prepareMsgToDisplay("error", data[0].error));
                            }
                        },
                        "json"
                    )
                },
                "<?php echo $LANG['reject'];?>": function() {
                    $("#suggestion_view_wait").html("<?php echo "<i class='fa fa-cog fa-spin fa-lg'></i>&nbsp;".addslashes($LANG['please_wait'])."...";?>").show();
                    $.post(
                        "sources/suggestion.queries.php",
                        {
                            type    : "reject_item_change",
                            id      : $("#suggestion_id").val(),
                            key     : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {

                            $("#suggestion_view_wait").html("<?php echo $LANG['alert_message_done'];?>");
                            oTable = $("#t_change").dataTable();
                            oTable.fnDraw();
                            setTimeout(
                                function() {
                                    $("#div_suggestion_view").dialog("close");
                                },
                                1500
                            );
                        }
                    )
                },
                "<?php echo $LANG['cancel_button'];?>": function() {
                    $(this).dialog("close");
                }
            },
            open: function (event, ui) {
                $("#div_suggestion_html").html("<?php echo "<i class='fa fa-cog fa-spin fa-lg'></i>&nbsp;".addslashes($LANG['please_wait'])."...";?>");

                // load change
                $.post("sources/suggestion.queries.php",
                    {
                        type     : "get_item_change_detail",
                        id       : $("#suggestion_id").val(),
                        key      : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        //decrypt data
                        try {
                            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key'];?>");
                        } catch (e) {
                            // error
                            return;
                        }
                        if (data.error === "") {
                            $("#div_suggestion_html").html(
                                data.html
                            );

                            $(".confirm_change").click(function(event){
                                console.log($(this).attr("id"));
                                var tmp = $(this).attr("id").split('-');
                                $("#"+tmp[0]).html('<span class="fa fa-close mi-red fa-lg"></span>');
                                tmp = tmp[0].split('_');
                                $("#"+tmp[1]+"_change").val("").remove();
                            });
                        }
                        $("#add_suggestion_wait").hide();
                    }
                );

            }
        });

        //Password meter for item creation
        $("#suggestion_pwd").simplePassMeter({
            "requirements": {},
            "container": "#pw_strength",
            "defaultText" : "<?php echo $LANG['index_pw_level_txt'];?>",
            "ratings": [
                {"minScore": 0,
                    "className": "meterFail",
                    "text": "<?php echo $LANG['complex_level0'];?>"
                },
                {"minScore": 25,
                    "className": "meterWarn",
                    "text": "<?php echo $LANG['complex_level1'];?>"
                },
                {"minScore": 50,
                    "className": "meterWarn",
                    "text": "<?php echo $LANG['complex_level2'];?>"
                },
                {"minScore": 60,
                    "className": "meterGood",
                    "text": "<?php echo $LANG['complex_level3'];?>"
                },
                {"minScore": 70,
                    "className": "meterGood",
                    "text": "<?php echo $LANG['complex_level4'];?>"
                },
                {"minScore": 80,
                    "className": "meterExcel",
                    "text": "<?php echo $LANG['complex_level5'];?>"
                },
                {"minScore": 90,
                    "className": "meterExcel",
                    "text": "<?php echo $LANG['complex_level6'];?>"
                }
            ]
        });
        $('#suggestion_pwd').bind({
             "score.simplePassMeter" : function(jQEvent, score) {
                $("#password_complexity").val(score);
             }
         });
    });
//]]>
</script>
