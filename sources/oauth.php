<?php
use TeampassClasses\OAuth2Controller\OAuth2Controller;
session_start();
require_once __DIR__. '/../includes/config/include.php';
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();

// MDP  teampss.user    c@mx5q^tL6
// MDP  teampass.admin  Goh@u939!879

// Création d'une instance du contrôleur
$OAuth2 = new OAuth2Controller($SETTINGS);

// Redirection vers Azure pour l'authentification
$OAuth2->redirect();
