<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Problema al procesar tu orden</title>
</head>
<body>
  <h2>Hola {{ $order->user->first_name }},</h2>

  <p>Hemos recibido tu pago exitosamente y lo agradecemos mucho.</p>

  <p>Sin embargo, ocurrió un inconveniente al procesar tu orden <strong>#{{ $order->id }}</strong>. No te preocupes, ya registramos los datos disponibles y nuestro equipo está revisando el caso.</p>

  <p>Nos pondremos en contacto contigo muy pronto para resolverlo y asegurarnos de que recibas tu pedido.</p>

  <p>Por favor conserva este ID de transacción: <strong>{{ $order->transaction_id }}</strong></p>

  <hr>

  <p>Gracias por tu comprensión.</p>
  <p>— El equipo de Nurae</p>
</body>
</html>
