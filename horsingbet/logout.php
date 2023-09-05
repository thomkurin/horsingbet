<?php
session_start();
session_unset();
session_destroy();

header("Location:login.php"); // Redirecionar o usuário para a página inicial
