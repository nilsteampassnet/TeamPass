<?php
/**
 * @file          kb.load.php
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

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['settings']['enable_kb']) || $_SESSION['settings']['enable_kb'] != 1) {
    die('Hacking attempt...');
}

?>
<script type="text/javascript">
//<![CDATA[
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
    function deleteKB(id)
    {
        $("#kb_id").val(id);
        $("#div_kb_delete").dialog("open");
    }

    $(function() {

        //Launch the datatables pluggin
        $("#t_kb").dataTable({
            "aaSorting": [[ 1, "asc" ]],
            "sPaginationType": "full_numbers",
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "sources/datatable/datatable.kb.php",
            "bJQueryUI": true,
            "oLanguage": {
                "sUrl": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
            }
        });

        //Dialogbox for deleting KB
        $("#div_kb_delete").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 300,
            height: 150,
            title: "<?php echo $LANG['item_menu_del_elem'];?>",
            buttons: {
                "<?php echo $LANG['del_button'];?>": function() {
                    $.post(
                		"sources/kb.queries.php",
                        {
                        type    : "delete_kb",
                        id      : $("#kb_id").val(),
                        key     : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            $("#div_kb_delete").dialog("close");
                            oTable = $("#t_kb").dataTable();
                            oTable.fnDraw();
                        }
                   )
                },
                "<?php echo $LANG['cancel_button'];?>": function() {
                    $(this).dialog("close");
                }
            }
        });

        //Dialogbox for new KB
        $("#kb_form").dialog({
            bgiframe: true,
            modal: true,
            autoOpen: false,
            width: 900,
            height: 600,
            title: "<?php echo $LANG['kb_form'];?>",
            buttons: {
                "<?php echo $LANG['save_button'];?>": function() {
                    if ($("#kb_label").val() == "") {
                        $("#kb_label").addClass("ui-state-error");
                    } else if ($("#kb_category").val() == "") {
                        $("#kb_category").addClass("ui-state-error");
                    } else if ($("#kb_description").val() == "") {
                        $("#kb_description").addClass("ui-state-error");
                    } else {
                        //selected items associated to KB
                        var itemsvalues = [];
                        $("#kb_associated_to :selected").each(function(i, selected) {
                            itemsvalues[i] = $(selected).val();
                        });

                         var data = '{"label":"'+sanitizeString($("#kb_label").val())+'","category":"'+sanitizeString($("#kb_category").val())+
                             '","anyone_can_modify":"'+$("input[name=modify_kb]:checked").val()+'","id":"'+$("#kb_id").val()+
                             '","kb_associated_to":"'+itemsvalues+'","description":"'+sanitizeString(CKEDITOR.instances["kb_description"].getData())+'"}';

                         $.post("sources/kb.queries.php",
                              {
                                  type     : "kb_in_db",
                                  data     : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                                  key      : "<?php echo $_SESSION['key'];?>"
                              },
                            function(data) {
                                if (data[0].status == "done") {
                                    oTable = $("#t_kb").dataTable();
                                    oTable.fnDraw();
                                }
                                $("#kb_form").dialog("close");
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
                $("#kb_label, #kb_description, #kb_category").removeClass("ui-state-error");
                $("#kb_associated_to").multiselect();
                var instance = CKEDITOR.instances["kb_description"];
                if (instance) {
                    CKEDITOR.replace("kb_description",{toolbar:"Full", height: 250,language: "<?php echo $_SESSION['user_language_code'];?>"});
                } else {
                    $("#kb_description").ckeditor({toolbar:"Full", height: 250,language: "<?php echo $_SESSION['user_language_code'];?>"});
                }
            },
            close: function(event,ui) {
                if (CKEDITOR.instances["kb_description"]) {
                    CKEDITOR.instances["kb_description"].destroy();
                }
                $("#kb_id,#kb_label, #kb_description, #kb_category, #full_list_items_associated").val("");
            }
        });

        //category listing
        $("#kb_category").autocomplete({
            source: "sources/kb.queries.categories.php",
            minLength: 1
        }).focus(function() {
            if (this.value == "")
                $(this).trigger("keydown.autocomplete");
        });
    });
//]]>
</script>
