<?php
// Inclua o arquivo de configuração do banco de dados aqui
include_once("conexao.php");

if (isset($_POST['eventId']) && !isset($_POST['categoryId'])) {
    $sql = "SELECT * FROM categorias WHERE event_id = " . $_POST['eventId'];
    $result = $conn->query($sql);
    
    while($row = $result->fetch_assoc()) {
        echo "<a href='#' class='category-link event-".$_POST['eventId']."' data-category-id='" . $row['category_id'] . "'>" . $row['name'] . "</a><br>";
    }
} elseif (isset($_POST['eventId']) && isset($_POST['categoryId'])) {
    $sql = "SELECT * FROM largada WHERE event_id = " . $_POST['eventId'] . " AND category_id = " . $_POST['categoryId'];
    $result = $conn->query($sql);

    
    while($row = $result->fetch_assoc()) {

        echo "<br>";
        echo "<div>";
        echo $row['competitor_id'];
        echo " - ";
        echo $row['competitor_name'];
        echo " - ";
        echo $row['horse_name'];
        echo " - ";
        echo "<button type='submit' class='winner-btn' name='winner' value='" . $row['competitor_id'] . "' data-event-id='".$_POST['eventId']."' data-category-id='".$_POST['categoryId']."'>Set as winner</button>";
        echo "</div>";
    }
}
?>
