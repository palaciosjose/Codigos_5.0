// Evita que el menú contextual se abra al hacer clic derecho, restringiendo la interacción del usuario
document.addEventListener('contextmenu', function(event) {
    event.preventDefault();
});

// Evita el uso de las teclas de función y ciertas combinaciones de teclas que pueden abrir herramientas de desarrollador
document.addEventListener('keydown', function(event) {
    // Deshabilita la tecla F12 para prevenir la apertura de herramientas de desarrollador, protegiendo el contenido de la página
    if (event.key === "F12") {
        event.preventDefault();
        alert('Inspeccionar está deshabilitado en este sitio.'); // Informa al usuario que no puede acceder a las herramientas
    }

    // Bloquea combinaciones de teclas (Ctrl + Shift + I, J, C) y Ctrl + U, que permiten inspeccionar elementos o ver el código fuente
    if ((event.ctrlKey && event.shiftKey && (event.key === 'I' || event.key === 'J' || event.key === 'C')) || 
        (event.ctrlKey && event.key === 'U')) {
        event.preventDefault();
        alert('Este acceso está bloqueado.'); // Informa al usuario sobre la restricción de acceso
    }
});

// Evita el arrastre de imágenes, restringiendo su manipulación y mostrando un mensaje de advertencia
document.addEventListener('dragstart', function(event) {
    if (event.target.tagName === 'IMG') {
        event.preventDefault(); // Previene la acción de arrastre en imágenes
        alert('La selección y arrastre de imágenes está deshabilitada.'); // Informa al usuario sobre la restricción
    }
});

// Previene la acción de selección al hacer clic en imágenes, deshabilitando la interacción con ellas
document.addEventListener('mousedown', function(event) {
    if (event.target.tagName === 'IMG') {
        event.preventDefault(); // Evita que el clic en imágenes genere acciones predeterminadas
    }
});
