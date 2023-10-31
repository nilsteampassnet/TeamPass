<?php
// Register the secure session handler
session_set_save_handler(new \PHPSecureSession\SecureHandler(), true);