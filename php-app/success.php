<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CV Subido</title>
    <!-- Favicon: loguito.png -->
    <link rel="icon" href="imagenes/loguito.png" type="image/png">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #047857; /* Color verde más oscuro */
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain; /* Se ve entera sin recortar */
            display: block;
            box-shadow: none;
            /* Animación de entrada suave opcional */
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
    <script>
        // Redirigir después de 2 segundos (2000 ms)
        setTimeout(function() {
            window.location.href = 'list.php';
        }, 2000);
    </script>
</head>
<body>
    <img src="imagenes/okayy.png" alt="OK">
</body>
</html>

