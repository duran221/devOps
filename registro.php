<?php
// Iniciar sesión si planeas usar variables de sesión después del registro/login
// session_start(); // Descomenta si es necesario

$conexion = CreateConnection();
if ($conexion->connect_error) {
    // En un entorno de producción, no muestres detalles del error al usuario.
    // Registra el error en un archivo de log.
    error_log("Error de conexión a la base de datos: " . $conexion->connect_error);
    echo "Error en la conexión. Por favor, inténtalo más tarde.";
    // die() detiene la ejecución bruscamente. Considera una salida más elegante.
    exit(); // Usa exit() en lugar de die()
}


// --- Manejo del Registro ---
if ($_SERVER["REQUEST_METHOD"] == "POST") { // Asumiendo un botón name="registrar"
   
    // Obtener los datos enviados desde el formulario de registro
    // Usamos null coalescing operator (??) para evitar warnings si no existen
    $nombre = cleanInput($_POST['nombre'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    // Validación básica de campos vacíos
    if (empty($nombre) || empty($email) || empty($contrasena)) {
        // Podrías pasar un mensaje de error a la página de registro
        // $_SESSION['error_registro'] = "Todos los campos son obligatorios.";
        redirectToRegister("Todos los campos son obligatorios."); // Pasa mensaje opcional
        // exit() ya está en redirectToRegister
    }

    // Validar formato de email (opcional pero recomendado)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         redirectToRegister("El formato del correo electrónico no es válido.");
         // exit() ya está en redirectToRegister
    }

    try {
        // Genera 16 bytes aleatorios criptográficamente seguros.
        $salt_bytes = random_bytes(16);
        // Esto resultará en una cadena de 32 caracteres hexadecimales.
        $salt_hex = bin2hex($salt_bytes);
    } catch (Exception $e) {
        error_log("Error al generar la salt segura: " . $e->getMessage());
        redirectToRegister("Ocurrió un error crítico de seguridad al procesar tu solicitud.");
    }



    // 2. Hashear la contraseña con SHA-256 y la Salt generada
    // Concatenamos la contraseña con la *salt original en bytes* (no la hexadecimal)
    // para el hashing. Puedes elegir concatenar al inicio o al final, sé consistente.
    $password_plus_salt = $contrasena . $salt_bytes;
    $hashed_password = hash('sha256', $password_plus_salt); // Genera hash SHA-256

    if ($hashed_password === false) {
        // Error al hashear la contraseña
        error_log("Error al hashear la contraseña para el email: " . $email);
        redirectToRegister("Ocurrió un error al procesar tu solicitud. Inténtalo de nuevo.");
        // exit() ya está en redirectToRegister
    }

    // Preparar la consulta SQL para evitar inyección SQL
    // Usa 'names' o 'nombre' según el nombre real de tu columna en la tabla 'clientes'
    $sql = "INSERT INTO clientes (email, password, salt, names) VALUES (?, ?, ?,?)";

    // Preparar la sentencia
    $stmt = $conexion->prepare($sql);

    if ($stmt === false) {
        // Error al preparar la consulta
        error_log("Error al preparar la sentencia SQL: " . $conexion->error);
        redirectToRegister("Ocurrió un problema al intentar registrar el usuario (prepare failed).");
        // exit() ya está en redirectToRegister
    }

    // Vincular los parámetros a la sentencia preparada
    // "sss" indica que los tres parámetros son strings (cadena)
    // Pasamos las variables limpias y la contraseña hasheada
    $stmt->bind_param("ssss", $email, $hashed_password, $salt_hex, $nombre);

    // Ejecutar la sentencia preparada
    if ($stmt->execute()) {
        redirectToWelcomePage(); // Redirige a la página de bienvenida
    } else {
        // Error al ejecutar la consulta
        // Verificar si es un error de duplicado (ej: email ya existe)
        if ($conexion->errno == 1062) { // 1062 es el código de error para entrada duplicada
             redirectToRegister("El correo electrónico ya está registrado.");
        } else {
            error_log("Error al ejecutar la sentencia SQL: " . $stmt->error . " (Código: " . $conexion->errno . ")");
            redirectToRegister("Ocurrió un problema al intentar registrar el usuario (execute failed).");
        }
    }

    // Cerrar la sentencia preparada
    $stmt->close();

}

// Cerrar la conexión a la base de datos al final del script
$conexion->close();

/**
 * Redirige al usuario a la página de bienvenida.
 */
function redirectToWelcomePage() {
    header("Location: bienvenido.php");
    exit(); // Asegura que el script se detenga después de la redirección
}

/**
 * Redirige al usuario a la página de registro, opcionalmente con un mensaje de error.
 * @param string|null $errorMessage Mensaje de error para mostrar (opcional).
 */
function redirectToRegister($errorMessage = null) {
    $location = "registro.html";
    if ($errorMessage !== null) {
        // Pasa el mensaje como parámetro GET (simple, pero visible en URL)
        // Alternativa: usar sesiones para mensajes flash
        $location .= "?error=" . urlencode($errorMessage);
    }
    header("Location: " . $location);
    exit(); // Asegura que el script se detenga después de la redirección
}

/**
 * Crea la conexión a la base de datos.
 * @return mysqli|false Objeto mysqli en caso de éxito, false en caso de error.
 */
function CreateConnection() {
    // Datos de conexión a la base de datos
    $host = "localhost";
    $usuario_db = "security"; // Cambia si es necesario
    $contrasena_db = "123456"; // Cambia si es necesario
    $nombre_db = "usuarios"; // Cambia si es necesario

    // Desactivar reporte de errores de mysqli para manejarlo manualmente
    mysqli_report(MYSQLI_REPORT_OFF);

    // Intentar conexión
    $conexion = new mysqli($host, $usuario_db, $contrasena_db, $nombre_db);

    // Verificar errores de conexión explícitamente
    if ($conexion->connect_error) {
         error_log("Error de conexión a la base de datos: (" . $conexion->connect_errno . ") " . $conexion->connect_error);
        return false; // Devolver false en caso de error
    }

    // Establecer charset (recomendado)
     if (!$conexion->set_charset("utf8mb4")) {
         error_log("Error al establecer el charset UTF-8: " . $conexion->error);
         // No es crítico, pero bueno saberlo
     }


    return $conexion;
}

/**
 * Limpia la entrada del usuario para prevenir XSS al mostrarla en HTML.
 * NO previene inyección SQL (para eso usamos consultas preparadas).
 * @param string $input La cadena de entrada.
 * @return string La cadena limpiada.
 */
function cleanInput($input) {
    $input = trim($input); // Elimina espacios en blanco al inicio y final
    // Codifica caracteres especiales HTML para prevenir XSS si se imprime directamente en HTML
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}
?>