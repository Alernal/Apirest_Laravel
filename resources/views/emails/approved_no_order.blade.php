<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pago recibido - Acción requerida</title>
    <style>
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3869D4;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .container {
            max-width: 600px;
            margin: auto;
            font-family: Arial, sans-serif;
            background: #fff;
            padding: 32px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>¡Hola {{ $name }}!</h1>
        <p>
            Recibimos correctamente tu pago a través de <strong>Wompi</strong>, pero no pudimos registrar tu orden en nuestro sistema debido a un problema inesperado.
        </p>
        <h3>Detalles del pago:</h3>
        <ul>
            <li>ID de Transacción: <strong>{{ $transactionId }}</strong></li>
            <li>Referencia de pago: <strong>{{ $reference }}</strong></li>
        </ul>
        <p>
            Por favor, contáctanos lo antes posible para resolver esto y completar tu envío.
        </p>
        <p>
            <a href="mailto:soporte@nurae.com.co" class="button">Contactar Soporte</a>
        </p>
        <p>
            Gracias por tu comprensión,<br>
            <strong>Equipo NURAE</strong>
        </p>
    </div>
</body>
</html>
