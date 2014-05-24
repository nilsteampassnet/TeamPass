<?php
/**
 * @file          suggestion.load.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
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
    function validateSuggestion(id)
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
                    $("#complexity_required_text").html("<b>"+data[0].complexity_text+"</b>");
                }
                $("#pw_wait").hide();
            },
            "json"
        );
        $.ajaxSetup({async: true});
        return funcReturned;
    }

    $(function() {
        //buttons
        $("#button_new_suggestion").button();

        //Launch the datatables pluggin
        $("#t_suggestion").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.suggestion.php",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
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
            buttons: {
                "<?php echo $LANG['add_button'];?>": function() {
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
            width: 600,
            height: 500,
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
                        var data = '{"label":"'+sanitizeString($("#suggestion_label").val())+
                            '","password":"'+sanitizeString($("#suggestion_pwd").val())+
                            '", "description":"'+sanitizeString($("#suggestion_description").val()).replace(/\n/g, '<br />')+
                            '","folder":"'+$("#suggestion_folder").val()+
                            '","comment":"'+sanitizeString($("#suggestion_comment").val()).replace(/\n/g, '<br />')+'"}';

                        $.post("sources/suggestion.queries.php",
                            {
                                type     : "add_new",
                                data     : prepareExchangedData(data, "encode"),
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
            },
            close: function(event,ui) {
                $("#suggestion_email, #suggestion_description, #suggestion_label, #suggestion_password, #suggestion_comment").val("");
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

</script>
