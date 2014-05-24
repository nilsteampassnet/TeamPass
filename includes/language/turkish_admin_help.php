<?php
//TURKISH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['help_on_folders'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>
This page is used in order to create and manage FOLDERS.<br />
A folder is needed to organize your items. It is similare to windows file directories.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>Lowest level of folder is called ROOT.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>All folders and subfolders create the tree structure.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>Each folder is associated to a depth level in the tree structure.
</div>
<div id='accordion'>
    <h3><a href='#'>Add a new FOLDER</a></h3>
    <div>
        Just click on icon <img src='includes/images/folder--plus.png' alt='' />. A dedicated dialogbox will appear in which you will have to enter:<br />        
        - the folder's label or title<br />
        - its parent's folder (each folder is the subfolder of an other one)<br />
        - a complexity level (complexity level is used for password complexity. When creating a new item, associated password cannot be less complexe than the level required)<br />
        - a renewal period expressed in months (is needed in order to force password renewal after a specific period).    
    </div>
    <h3><a href='#'>Edit an existing folder</a></h3>
    <div>
        In order to change the label, the complexity, the parent folder or the renewal period, you just have to click in the cell.<br />
        This will make the cell editable. Change the value and click on icon <img src='includes/images/disk_black.png' alt='' /> to save, or on icon <img src='includes/images/cross.png' alt='' /> to cancel.<br />
        <p style='text-align:center;'>
        <img src='includes/images/help/folders_1.png' alt='' />
        </p>
        <div style='margin:10px Opx 0px 20px;'>
            Notice that if you change the parent folder, then all subfolders of the changed folder will be moved.
        </div>
    </div>
    <h3><a href='#'>Delete a Folder</a></h3>
    <div>
        You can decide to give to delete a folder. To do so, just click on icon <img src='includes/images/folder--minus.png' alt='' />.<br /> 
        This will delete all items inside the folder as all subfolders ... be carefull!!!!
        <p style='text-align:center;'>
        <img src='includes/images/help/folders_2.png' alt='' />
        </p>
    </div>
    <h3><a href='#'>Special tweaks</a></h3>
    <div>
        Two tweaks exist on folder.<br />
        The 1st allows item creation without respecting the required complexity level for the password.<br /> 
        The 2d allows item modification without respecting the required complexity level for the password.<br /> 
        You can also combine both of them.<br />
        You can also use them temporarly.   
        <p style='text-align:center;'>
        <img src='includes/images/help/folders_3.png' alt='' />
        </p>
    </div>
</div>";
$LANG['help_on_roles'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>
This page is used in order to create and manage ROLES.<br />
A role is associated to a set of allowed and forbidden folders.<br />
Once several roles are defined, you can associate USERS to them.
</div>
<div id='accordion'>
    <h3><a href='#'>Add a new ROLE</a></h3>
    <div>
        Just click on icon <img src='includes/images/users--plus.png' alt='' />. A dedicated dialogbox will appear in which you will have to enter a title for this new ROLE.
    </div>
    
    <h3><a href='#'>Allow or Forbid a folder</a></h3>
    <div>
        You can use the matrix 'Roles vs Folders' to define the access rights. If a cell is red, then the role can't access to the folder, and if the cell is gree, then the role can access to the folder.<br />
        In order to change the access, just click on the cell you want.<br/>
        <p style='text-align:center;'>
            <span style='text-align:center;'><img src='includes/images/help/roles_1.png' alt='' /></span>
        </p>
        In previous screen capture, you can see that folder 'Cleaner' is allowed to role 'Dev' but not for role 'Commercial'.
    </div>
    
    <h3><a href='#'>Refresh manually the matrix</a></h3>
    <div>
        Just click on icon <img src='includes/images/arrow_refresh.png' alt='' />.
    </div>
    
    <h3><a href='#'>Edit a role</a></h3>
    <div>
        You can change the title of a role with no impact on the parameters already done.<br />
        Select the role you want to change, and click on icon <img src='includes/images/ui-tab--pencil.png' alt='' />.<br />
        This will popup a dialogbox in which you will be asked to enter a new title.
    </div>
    
    <h3><a href='#'>Delete a role</a></h3>
    <div>
        You can decide to delete an existing role.<br />
        Select the role you want to delete, and click on icon <img src='includes/images/ui-tab--minus.png' alt='' />.<br />
        This will popup a dialogbox in which you will be asked to confirm the deletion.
    </div>
</div>";
$LANG['help_on_users'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>
This page is used in order to create and manage USERS.<br />
A user account is needed for each physical person that will have to use TeamPass.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>1st step is to set what ROLES the user has.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>2d step is to customize specific folders access or not.
</div>
<div id='accordion'>
    <h3><a href='#'>Add a new USER</a></h3>
    <div>
        Just click on icon <img src='includes/images/user--plus.png' alt='' />. A dedicated dialogbox will appear in which you will have to enter:<br />        
        - the user's login<br />
        - a password (can be generated and will be changed by user at 1st connection)<br />
        - a valid email<br />
        - if the user will be an Admin (full access to all functionnalities)<br />
        - if the user will be a Manager (full rights on Items)<br />
        - if the user could have Personal Folders  
    </div>
    <h3><a href='#'>Add a ROLE to a USER</a></h3>
    <div>
        You can associate a USER to as many ROLES you want. For that, just click on icon <img src='includes/images/cog_edit.png' alt='' />.<br />
        A specific dialogbox will appear in which you will have to tick or not the wanted roles.<br /><br />
        When a ROLE is added to a USER, then the USER will access to the allowed folders of that ROLE and will have no access to the forbidden ones.<br /><br />
        Now you can be more precise in the rights given to a USER by using the fields 'Allowed folders' and 'Forbidden folders'. Indeed, you can allowed or not some others folders even them specified in the ROLE.
        <div style='margin:2px Opx 0px 20px;'>
            For example:
            <p style='margin-left:20px;margin-top: 2px;'>
            - USER1 is associated to ROLE1 and ROLE2. <br />
            - ROLE1 is set to allow access to folder F1 and F2. <br />
            - F1 has 4 subfolders S1, S2, S3 and S4.<br />
            - This means that USER1 has access to F1, F2, S1, S2, S3 and S4.<br />
            - Now you can customize USER1 by forbidding the access to S4 using this page.
            </p>
        </div>
    </div>
    <h3><a href='#'>Is Administrator (GOD)</a></h3>
    <div>
        You can decide to give the GOD right to a user. To do so, just tick the box.<br /> 
        GOD is allowed to anything in TeamPass with absolutely no restriction ... so be carefull!!!!
        <p style='text-align:center;'>
        <img src='includes/images/help/users_1.png' alt='' />
        </p>
    </div>
    <h3><a href='#'>Is Manager</a></h3>
    <div>
        You can decide to give the MANAGER right to a user. To do so, just tick the box.<br /> 
        A Manager can modify and delete items and folders, even them that he has not created.<br /> 
        A manager has only access to the folders he/she is allowed to. So you can create several managers for dedicated departements.    
        <p style='text-align:center;'>
        <img src='includes/images/help/users_2.png' alt='' />
        </p>
    </div>
    <h3><a href='#'>Delete a USER</a></h3>
    <div>
        You can decide to give to delete a user. To do so, just click on icon <img src='includes/images/user--minus.png' alt='' />.
        <p style='text-align:center;'>
        <img src='includes/images/help/users_3.png' alt='' />
        </p>
    </div>
    <h3><a href='#'>Change the User's password</a></h3>
    <div>
        You can decide to give to change the password of a user. To do so, just click on icon <img src='includes/images/lock__pencil.png' alt='' />.<br /> 
        At 1st connection, the user will have to change it. 
        <p style='text-align:center;'>
        <img src='includes/images/help/users_4.png' alt='' />
        </p>
    </div>
    <h3><a href='#'>Change the User's email</a></h3>
    <div>
        You can decide to give to change the password of a user. To do so, just click on icon <img src='includes/images/mail--pencil.png' alt='' />.<br />   
        <p style='text-align:center;'>
        <img src='includes/images/help/users_5.png' alt='' />
        </p>
    </div>
</div>";
?>
