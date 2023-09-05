$('#update-stats-btn').on('click', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'get_stats.php',
        type: 'get',
        success: function(response) {
            $('#analise').html(response);
            $('#analise').removeClass('hidden');
            $('#apostas-section').addClass('hidden');
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log("Erro ao buscar estatísticas:", textStatus, errorThrown);
        }
    });
});
