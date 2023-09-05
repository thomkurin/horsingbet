// Função para verificar se um elemento está dentro de outro
function isInside(child, parent) {
    let node = child;
    while (node != null) {
        if (node == parent) {
            return true;
        }
        node = node.parentNode;
    }
    return false;
}

document.getElementById('menu-btn').addEventListener('click', function(event) {
    var header = document.getElementById('header');
    if (header.style.display === "none") {
        header.style.display = "block";
    } else {
        header.style.display = "none";
    }
});

document.getElementById('close-btn').addEventListener('click', function(event) {
    var header = document.getElementById('header');
    header.style.display = "none";
});

// Ouvinte de evento para clicar em qualquer lugar fora do menu para fechá-lo
document.body.addEventListener('click', function(event) {
    var header = document.getElementById('header');
    if (!isInside(event.target, header) && header.style.display === "block") {
        header.style.display = "none";
    }
});
