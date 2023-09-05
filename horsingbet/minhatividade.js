// Função para mostrar uma seção específica
function showSection(sectionId) {
    // Primeiro, esconda todas as seções
    let sections = document.querySelectorAll(".section");
    for (let i = 0; i < sections.length; i++) {
        sections[i].style.display = "none";
    }

    // Em seguida, mostre a seção desejada
    let section = document.getElementById(sectionId);
    if (section) {
        section.style.display = "block";
    }
}

// Função para abrir uma guia específica
function openTab(evt, tabId) {
    // Primeiro, remova a classe "active" de todas as guias
    let tablinks = document.getElementsByClassName("tablinks");
    for (let i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    // Em seguida, esconda todo o conteúdo da guia
    let tabcontents = document.getElementsByClassName("tabcontent");
    for (let i = 0; i < tabcontents.length; i++) {
        tabcontents[i].style.display = "none";
    }

    // Em seguida, mostre o conteúdo da guia desejada e adicione a classe "active" à guia desejada
    document.getElementById(tabId).style.display = "block";
    evt.currentTarget.className += " active";
}
