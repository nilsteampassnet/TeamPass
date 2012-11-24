<?php
//SWEDISH
if (!isset($_SESSION['settings']['cpassman_url'])) {
    $TeamPass_url = '';
} else {
    $TeamPass_url = $_SESSION['settings']['cpassman_url'];
}

$txt['category'] = "Category";
$txt['kb'] = "Knowledge Base";
$txt['kb_anyone_can_modify'] = "Anyone can modify it";
$txt['kb_form'] = "Manage entries in KB";
$txt['new_kb'] = "Add a new KB";
