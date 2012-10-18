<?php
//PORTUGUESE
if (!isset($_SESSION['settings']['cpassman_url'])) {
    $TeamPass_url = '';
} else {
    $TeamPass_url = $_SESSION['settings']['cpassman_url'];
}

$txt['category'] = "Categoria";
$txt['kb'] = "Base de conhecimento";
$txt['kb_anyone_can_modify'] = "Qualquer um pode modificar";
$txt['kb_form'] = "Edita entradas no KB";
$txt['new_kb'] = "Adiciona novo KB";
