/**
 * Función para limpiar caracteres especiales
 * @param {string} cadena 
 * @returns {string} Cadena sin caracteres especiales
 */
function limpiarCadena(cadena) {
    return cadena.replace(/['@\s]/g, ''); // Elimina comillas simples, '@' y espacios
}

/**
 * Función para validar el formulario de registro
 * @param {Event} event
 * @returns {void}
 */
function validarFormulario(event) {
    event.preventDefault(); // Evita el envío del formulario

    // Obtener los valores de los campos
    const nombre = document.getElementById('nombre').value.trim();
    const email = document.getElementById('email').value.trim();
    const contrasena = document.getElementById('contrasena').value.trim();
    const repetirContrasena = document.getElementById('repetir_contrasena').value.trim();

    // Limpiar caracteres especiales
    const nombreLimpio = limpiarCadena(nombre);
    const emailLimpio = limpiarCadena(email);
    const contrasenaLimpia = limpiarCadena(contrasena);
    const repetirContrasenaLimpia = limpiarCadena(repetirContrasena);

    // Validar que los campos no estén vacíos
    if (!nombreLimpio || !emailLimpio || !contrasenaLimpia || !repetirContrasenaLimpia) {
        alert("Todos los campos son obligatorios.");
        console.log("Todos los campos son obligatorios.");
        return;
    }

    // Validar que las contraseñas coincidan
    if (contrasenaLimpia !== repetirContrasenaLimpia) {
        alert("Las contraseñas no coinciden. Por favor, inténtelo de nuevo.");
        console.log("Las contraseñas no coinciden. Por favor, inténtelo de nuevo.");
        return;
    }

    // Si todo está correcto, enviar el formulario
    alert("Formulario validado correctamente. Enviando datos...");
    console.log("Formulario validado correctamente. Enviando datos...");
    document.getElementById('registroForm').submit();
}

document.getElementById('registroForm').addEventListener('submit', validarFormulario);