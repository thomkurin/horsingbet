
$('#login-modal form').submit(function(e) {
    e.preventDefault();
    $.ajax({
        type: "POST",
        url: "login.php",
        data: $("#login-modal form").serialize(),
        dataType: "json",
        success: function(response) {
            if(response.status === 200) {
                window.location.href = "http://localhost/horsingbet/index.php";
            } else {
                $('#error-message').text(response.message);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $('#error-message').text("Houve um erro na solicitação. Tente novamente mais tarde.");
        }
    });
});

