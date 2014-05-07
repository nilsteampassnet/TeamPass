<?php
//FRENCH
if (!isset($_SESSION['settings']['cpassman_url'])) {
	$TeamPass_url = '';
}else{
	$TeamPass_url = $_SESSION['settings']['cpassman_url'];
}


$LANG['help_on_folders'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>
Cette page est utilisée pour créer et gérer les RÉPERTOIRES.<br />
Un répertoire est nécessaire pour organiser et structurer vos éléments. Il est similaire à un répertoire de fichiers de votre système d'exploitation.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>Le niveau le plus bas est appelé la RACINE.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>L'ensemble des répertoires et sous-répertoires constitue l'arborescence.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>Chaque répertoire est associé à un niveau de profondeur dans l'arborescence.
</div>
<div id='accordion'>
    <h3><a href='#'>Ajouter un RÉPERTOIRE</a></h3>
    <div>
        Cliquez sur l'icône <img src='includes/images/folder--plus.png' alt='Ajouter un répertoire' />. Une boite de dialogue vous permettra de saisir :<br />
        - l'intitulé du répertoire<br />
        - le répertoire parent (chaque répertoire étant associé à un autre répertoire parent)<br />
        - un niveau de complexité (celui-ci est utilisé pour la complexité des mots de passe. Quand un utilisateur créé un nouvel élément, le mot de passe associé doit au moins répondre à ce critère de complexité)<br />
        - une période de renouvellement exprimée en mois (est nécessaire pour demander un renouvellement des mots de passe).
    </div>
    <h3><a href='#'>Éditer un répertoire existant</a></h3>
    <div>
        De façon à changer un intitulé, la complexité, le répertoire parent ou encore la période de renouvellement d'un répertoire, vous devez cliquer sur la cellule correspondante.<br />
        Cela rends la cellule modifiable. Changer alors la valeur et cliquez sur l'icône <img src='includes/images/disk_black.png' alt='Sauvegarder' /> pour sauvegarder ou sur l'icône <img src='includes/images/cross.png' alt='Annuler' /> pour annuler.<br />
        <p style='text-align:center;'>
        <img src='includes/images/help/folders_1.png' alt='Écran répertoires 1' />
        </p>
        <div style='margin:10px Opx 0px 20px;'>
            Si vous décidez de changer le répertoire parent alors tous les sous-répertoires seront également déplacés.
        </div>
    </div>
    <h3><a href='#'>Supprimer un répertoire</a></h3>
    <div>
        Vous pouvez supprimer un répertoire. Pour cela, il suffit de cliquer sur l'icône <img src='includes/images/folder--minus.png' alt='Supprimer un répertoire' />.<br />
        Attention car cela aura pour conséquence de supprimer également tous les éléments et sous-répertoires inclus !
        <p style='text-align:center;'>
        <img src='includes/images/help/folders_2.png' alt='Écran répertoires 2' />
        </p>
    </div>
    <h3><a href='#'>Astuces spéciales</a></h3>
    <div>
        2 astuces existent sur les répertoires.<br />
        La 1ère autorise la création d'un élément sans avoir à respecter la complexité minimale du mot de passe.<br />
        La 2nde autorise la modification d'un élément sans avoir à respecter la complexité minimale du mot de passe.<br />
        Vous pouvez également combiner les 2.<br />
        Vous pouvez également l'utiliser temporairement.
        <p style='text-align:center;'>
        <img src='includes/images/help/folders_3.png' alt='Écran répertoires 3' />
        </p>
    </div>
</div>";
$LANG['help_on_roles'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>
Cette page est utilisée pour créer et modifier les RÔLES.<br />
Un rôle est associé à un ensemble de répertoires autorisés et interdits.<br />
Une fois plusieurs rôles paramétrés, vous pouvez les utiliser pour les associer à un compte utilisateur.
</div>
<div id='accordion'>
    <h3><a href='#'>Ajouter un RÔLE</a></h3>
    <div>
        Cliquez sur l'icône <img src='includes/images/users--plus.png' alt='Ajouter un utilisateur' />. Une boite de dialogue spécifique vous demandera de saisir l'intitulé de ce nouveau rôle.
    </div>

    <h3><a href='#'>Autoriser ou interdire un RÉPERTOIRE</a></h3>
    <div>
        Vous devez utiliser la matrice 'Rôles / Répertoires' pour définir les droits d'accès des rôles. Si la couleur de la cellule est rouge, alors le rôle ne pourra pas accéder à ce répertoire, et si la cellule est verte, alors le rôle pourra accéder à la cellule.<br />
        Pour changer le droit d'accès d'un rôle à un répertoire, il suffit de cliquer dessus.<br/>
        <p style='text-align:center;'>
            <span style='text-align:center;'><img src='includes/images/help/roles_1.png' alt='Écran rôles 1' /></span>
        </p>
        Dans la capture d'écran, vous voyez que le répertoire 'Cleaner' est autorisé pour le rôle 'Dev' mais qu'il ne l'est pas pour le rôle 'Commercial'.
    </div>

    <h3><a href='#'>Rafraîchir manuellement la matrice</a></h3>
    <div>
        Il vous suffit de cliquer sur l'icône <img src='includes/images/arrow_refresh.png' alt='Rafraîchir' />.
    </div>

    <h3><a href='#'>Éditer un rôle</a></h3>
    <div>
        Il est possible de changer l'intitulé d'un rôle sans aucun impact sur les différents paramétrages effectués..<br />
        Sélectionnez le rôle que vous voulez renommer et cliquez sur l'icône <img src='includes/images/ui-tab--pencil.png' alt='Éditer' />.<br />
        Cela ouvrira une boite de dialogue dans laquelle vous pourrez saisir le nouvel intitulé.
    </div>

    <h3><a href='#'>Supprimer un rôle</a></h3>
    <div>
        Vous pouvez tout à fait supprimer un rôle. Cela aura pour effet de supprimer ce rôle de chaque utilisateur le possédant.<br />
        Sélectionnez le rôle que vous voulez supprimer et cliquez sur l'icône <img src='includes/images/ui-tab--minus.png' alt='Supprimer' />.<br />
        Cela ouvrira une boîte de dialogue dans laquelle vous devrez confirmer la suppression.
    </div>
</div>";
$LANG['help_on_users'] = "<div class='ui-state-highlight ui-corner-all' style='padding:5px;font-weight:bold;'>
Cette page est utilisée pour créer et gérer les UTILISATEURS.<br />
Un compte utilisateur est nécessaire pour chaque personne physique devant utiliser TeamPass.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>La 1ère étape consiste à associer l'utilisateur à un ou plusieurs rôles.<br />
<span class='ui-icon ui-icon-lightbulb' style='float: left;'>&nbsp;</span>La 2nde étape (optionnelle) consiste à définir les répertoires spécifiques auxquels l'utilisateur peut avoir accès.
</div>
<div id='accordion'>
    <h3><a href='#'>Ajouter un UTILISATEUR</a></h3>
    <div>
        Cliquer sur l'icône <img src='includes/images/user--plus.png' alt='Ajouter un utilisateur' />. Dans la boite de dialogue, il conviendra de saisir :<br />
        - l'identifiant de connexion de l'utilisateur<br />
        - un mot de passe (peut être généré automatiquement et sera obligatoirement changé à la 1ère connexion)<br />
        - une adresse e-mail valide<br />
        - si l'utilisateur sera un administrateur (accès sans limite aux fonctionnalités)<br />
        - si l'utilisateur sera un Manager (tous les droits sur les éléments accessibles)<br />
        - si l'utilisateur peut avoir accès à des répertoires personnels
    </div>
    <h3><a href='#'>Ajouter un RÔLE à un UTILISATEUR</a></h3>
    <div>
        Vous pouvez associer un UTILISATEUR à autant de RÔLES que vous voulez. Pour cela, il suffit de cliquer sur l'icône <img src='includes/images/cog_edit.png' alt='Éditer' />.<br />
        Une boite de dialogue vous permettra de sélectionner les rôles désirés.<br /><br />
        Quand un rôle est ajouté à un utilisateur ce dernier aura alors la possibilité de consulter les éléments des répertoires autorisés et n'aura pas accès à ceux qui se trouvent dans les répertoires interdits.<br /><br />
        Maintenant il est possible d'être beaucoup plus précis en associant en plus des rôles des répertoires autorisés et interdits pour chaque utilisateur. En effet, vous pouvez autoriser et interdire d'autres répertoires que ceux présents dans la définition des RÔLES.
        <div style='margin:2px Opx 0px 20px;'>
            Par exemple :
            <p style='margin-left:20px;margin-top: 2px;'>
            - UTILISATEUR1 est associé au RÔLE1 et RÔLE2. <br />
            - RÔLE1 donne accès aux répertoires R1 et R2. <br />
            - R1 possède 4 sous répertoires S1, S2, S3 et S4.<br />
            - Cela signifie que l'UTILISATEUR1 a accès à F1, F2, S1, S2, S3 et S4.<br />
            - Vous pouvez également paramétrer UTILISATEUR1 pour qu'il ne puisse pas accéder à S4.
            </p>
        </div>
    </div>
    <h3><a href='#'>Est Administrateur (DIEU)</a></h3>
    <div>
        Vous pouvez autoriser tout utilisateur à être DIEU. Pour cela, vous n'avez qu'à cocher la case correspondante.<br />
        Attention cependant car un utilisateur DIEU peut accéder à toutes les fonctionnalités de TeamPass !
        <p style='text-align:center;'>
        <img src='includes/images/help/users_1.png' alt='Écran utilisateurs 1' />
        </p>
    </div>
    <h3><a href='#'>Est Manager</a></h3>
    <div>
        Vous pouvez autoriser tout utilisateur à être GESTIONNAIRE. Pour cela, vous n'avez qu'à cocher la case correspondante.<br />
        Un Gestionnaire peut modifier et supprimer des éléments et des répertoires, y compris ceux qu'il n'a pas créé.<br />
        Un Gestionnaire n'a cependant accès qu'aux répertoires qu'il est autorisé à voir. Il est donc possible de créer plusieurs Gestionnaires en fonction des Services par exemple.
        <p style='text-align:center;'>
        <img src='includes/images/help/users_2.png' alt='Écran utilisateurs 2' />
        </p>
    </div>
    <h3><a href='#'>Supprimer un UTILISATEUR</a></h3>
    <div>
        Vous pouvez supprimer un utilisateur. Pour cela, il suffit de cliquer sur l'icône <img src='includes/images/user--minus.png' alt='Supprimer un utilisateur' />.
        <p style='text-align:center;'>
        <img src='includes/images/help/users_3.png' alt='Écran utilisateurs 3' />
        </p>
    </div>
    <h3><a href='#'>Changer le mot de passe d'un utilisateur</a></h3>
    <div>
        Il est tout à fait possible pour un administrateur de changer le mot de passe d'un utilisateur. Pour cela, il suffit de cliquer sur l'icône <img src='includes/images/lock__pencil.png' alt='Éditer le mot de passe' />.<br />
        A la 1ère connexion de l'utilisateur, il devra le modifier.
        <p style='text-align:center;'>
        <img src='includes/images/help/users_4.png' alt='Écran utilisateurs 4' />
        </p>
    </div>
    <h3><a href='#'>Changer l'e-mail d'un utilisateur</a></h3>
    <div>
        Il est tout à fait possible pour un administrateur de changer l'e-mail d'un utilisateur. Pour cela, il suffit de cliquer sur l'icône <img src='includes/images/mail--pencil.png' alt='Éditer l'e-mail' />.<br />
        <p style='text-align:center;'>
        <img src='includes/images/help/users_5.png' alt='Écran utilisateurs 5' />
        </p>
    </div>
</div>";
?>
