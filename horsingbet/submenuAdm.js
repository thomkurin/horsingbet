var currentEventId = 0;

$(document).ready(function() {
    function showSection(parentId, sectionId, eventId, categoryId) {
        console.log("showSection chamada com", parentId, sectionId, eventId, categoryId);

        var parent = document.getElementById(parentId);
        console.log("Elemento pai:", parent);

        // Remove a classe 'hidden' da seção pai
        parent.classList.remove('hidden');

        var sections = parent.getElementsByClassName('submenu');
        console.log("Seções filhas:", sections);

        for (var i = 0; i < sections.length; i++) {
            if (sections[i].id !== sectionId) {  // Verifica se o id da seção é diferente do id da seção alvo
                sections[i].style.display = 'none';
                sections[i].classList.remove('hidden');
            }
        }
        

        var targetSection = document.getElementById(sectionId);
        console.log("Seção alvo:", targetSection);

        targetSection.style.display = 'block';

        // Se o eventId não estiver definido, use o currentEventId
        eventId = eventId || currentEventId;

        if (eventId || categoryId) {
            console.log("Enviando requisição AJAX para get_data.php com", { eventId: eventId, categoryId: categoryId });

            $.ajax({
                url: 'get_data.php',
                type: 'post',
                data: { eventId: eventId, categoryId: categoryId },
                success: function(response) {
                    console.log("Resposta da requisição AJAX:", response);
                    targetSection.innerHTML = response;
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log("Erro na requisição AJAX:", textStatus, errorThrown);
                }
            });
        }
    }

    $('#apostas-link').on('click', function(e) {
        e.preventDefault();
        showSection('apostas-section', 'eventos');
    });

    $(document).on('click', '.event-link', function(e) {
        e.preventDefault();
        var eventId = $(this).data('event-id');
        currentEventId = eventId; // Atualize o currentEventId aqui
        showSection('apostas-section', 'categorias', eventId);
    });

    $(document).on('click', '.category-link', function(e) {
        e.preventDefault();
        var categoryId = $(this).data('category-id');
        showSection('apostas-section', 'competidores', null, categoryId);
    });
});

$(document).on('click', '.winner-btn', function(e) {
    e.preventDefault();
    var competitorId = $(this).val();
    $.ajax({
        url: 'declare_winner.php',
        type: 'post',
        data: { competitorId: competitorId },
        success: function(response) {
            console.log("Resposta da requisição AJAX:", response);
            alert("Vencedor declarado com sucesso!");
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log("Erro na requisição AJAX:", textStatus, errorThrown);
        }
    });
});
