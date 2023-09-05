// Obtém os elementos do menu e do conteúdo das seções
var menuItems = document.querySelectorAll('.menu ul li a');
var sections = document.querySelectorAll('.content .section');

// Oculta todas as seções, exceto a primeira
sections.forEach(function(section, index) {
  if (index !== 0) {
    section.style.display = 'none';
  }
});

// Adiciona o evento de clique a cada item do menu
menuItems.forEach(function(item, index) {
  item.addEventListener('click', function(e) {
    e.preventDefault();
    
    // Remove a classe 'active' de todos os itens do menu
    menuItems.forEach(function(menuItem) {
      menuItem.classList.remove('active');
    });

    // Adiciona a classe 'active' ao item de menu clicado
    item.classList.add('active');

    // Oculta todas as seções
    sections.forEach(function(section) {
      section.style.display = 'none';
    });

    // Exibe a seção correspondente ao item de menu clicado
    var targetSectionId = item.getAttribute('href').substring(1);
    var targetSection = document.getElementById(targetSectionId);
    targetSection.style.display = 'block';
  });
});
