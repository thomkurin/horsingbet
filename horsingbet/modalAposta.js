function openApostaModal(competidor, cavalo, competitorId, odds) {
    var modal = document.getElementById("apostaModal");
    var competidorSpan = document.getElementById("competidor");
    var cavaloSpan = document.getElementById("cavalo");
    var competitorIdInput = document.getElementById("competitorId");
    var oddsSpan = document.getElementById("odds");
    var oddsInput = document.getElementById("oddsInput"); // Aqui

    // Preenche os dados do competidor e cavalo na modal
    competidorSpan.textContent = competidor;
    cavaloSpan.textContent = cavalo;
    document.getElementById("competidorInput").value = competidor;
    document.getElementById("cavaloInput").value = cavalo;
    competitorIdInput.value = competitorId;

    oddsSpan.textContent = odds.toFixed(6); // Aqui
    oddsInput.value = odds.toFixed(6); // Aqui

    // Exibe a modal
    modal.style.display = "block";
}


// Função para calcular e exibir o possível ganho
function calcularPossivelGanho(inputField, oddValueElementId, outputId) {
    var aposta = parseFloat(inputField.value);
    var oddValue = parseFloat(document.getElementById(oddValueElementId).textContent);

    if (oddValue === 0) {
        document.getElementById(outputId).textContent = "Aposta Anulada";
    } else {
        var possivelGanho = aposta * oddValue;
        document.getElementById(outputId).textContent = "Possível Ganho: R$" + possivelGanho.toFixed(2);
    }
}

// Adiciona o event listener para o input de odds
document.getElementById("valor_apostado").addEventListener("input", function() {
    calcularPossivelGanho(this, "odds", "possivel-ganho1"); // Aqui
});

var oddsModalTriggers = document.querySelectorAll(".odds-modal");

oddsModalTriggers.forEach(function(odds) {
    odds.onclick = function() {
        var competidor = odds.getAttribute("data-competidor");
        var cavalo = odds.getAttribute("data-cavalo");
        var competitorId = odds.getAttribute("data-competitor-id");
        var oddsValue = parseFloat(odds.getAttribute("data-odds")); 
        openApostaModal(competidor, cavalo, competitorId, oddsValue);
    };
}); 


var closeBtn = document.querySelector(".close");

function closeModal() {
    var modal = document.getElementById("apostaModal");
    modal.style.display = "none";
}

closeBtn.addEventListener("click", closeModal);

window.addEventListener("click", function(event) {
    var modal = document.getElementById("apostaModal");
    if (event.target === modal) {
        closeModal();
    }
});

document.getElementById('formAposta').addEventListener('submit', function(e) {
    e.preventDefault();

    var formData = new FormData(this);

    fetch('apostar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log(text);
        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('Invalid JSON:', text);
            throw error;
        }
    })
    .catch((error) => {
        console.error('Error:', error);
    });
});

