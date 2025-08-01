<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido a NURAE</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #333; background: #fff; }
        a.button {
            background: #4CAF50;
            color: #fff !important;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <h2>¡Hola {{ $user->name ?? 'cliente' }}!</h2>

    <p>
        Nos alegra darte la bienvenida a <strong>NURAE</strong>. Hemos creado una cuenta para ti, donde podrás hacer seguimiento de tus pedidos, guardar direcciones y consultar tu historial de compras de manera fácil y segura.
    </p>

    <p><strong>Datos de acceso a tu cuenta:</strong></p>
    <ul>
        <li><strong>Email:</strong> {{ $user->email }}</li>
        <li><strong>Contraseña temporal:</strong> {{ $password }}</li>
    </ul>

    <p>
        Por favor, inicia sesión en tu cuenta y cambia tu contraseña temporal por una de tu preferencia para mayor seguridad.<br>
        <a href="{{ url('https://nurae.com.co/login') }}" class="button">Iniciar sesión</a>
    </p>

    <p>
        Si tienes alguna duda o necesitas ayuda, no dudes en contactarnos respondiendo a este correo o visitando nuestra página de <a href="{{ url('/contacto') }}">Contacto</a>.
    </p>

    <p>
        <img src="{{ asset('logo-nurae.png') }}" width="120" alt="Logo Nurae">
    </p>

    <p>
        ¡Gracias por confiar en nosotros!<br>
        El equipo de NURAE
    </p>
</body>
</html>
