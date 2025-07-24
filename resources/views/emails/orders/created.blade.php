<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Orden confirmada</title>
</head>
<body>
    <h2>Hola {{ $order->user->first_name }},</h2>

    <p>¡Gracias por tu compra! Hemos recibido tu pago exitosamente y tu orden <strong>#{{ $order->id }}</strong> ha sido generada.</p>

    <p>En breve comenzaremos a procesarla. Te notificaremos por correo electrónico cuando el estado de tu orden cambie.</p>

    <hr>

    <h3>Resumen de tu orden:</h3>
    <ul>
        @foreach ($order->orderItems as $item)
            <li>{{ $item->product_name }} x{{ $item->quantity }} – ${{ number_format($item->total, 0, ',', '.') }}</li>
        @endforeach
    </ul>

    <p><strong>Total pagado:</strong> ${{ number_format($order->total, 0, ',', '.') }}</p>

    <hr>

    <p>Puedes consultar el estado de tu orden en nuestro portal en cualquier momento.</p>

    <p>Gracias nuevamente por tu confianza.</p>
    <p>— El equipo de Nurae</p>
</body>
</html>
