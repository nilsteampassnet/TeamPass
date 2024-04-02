<?php
use TeampassClasses\AzureAuthController\AzureAuthController;
session_start();
require_once __DIR__. '/../../includes/config/include.php';
require_once __DIR__.'/../../sources/main.functions.php';

// init
loadClasses();

// MDP teampss.user c@mx5q^tL6

// Création d'une instance du contrôleur
$azureAuth = new AzureAuthController($SETTINGS);

// Redirection vers Azure pour l'authentification
$azureAuth->redirect();
