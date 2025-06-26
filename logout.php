<?php
require_once 'config.php';
require_once 'functions.php';

registrarLog('LOGOUT', 'Usuário fez logout do sistema');

session_destroy();

header('Location: index.php');
exit;
?>