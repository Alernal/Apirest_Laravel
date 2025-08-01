<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Orden {{ $order->payment_status === 'pending' ? 'pendiente' : 'confirmada' }}</title>
</head>
<body>
    <h2>Hola {{ $order->user->first_name }},</h2>

    @if ($order->payment_status === 'pending')
        <p>Gracias por tu compra. Tu orden <strong>#{{ $order->transaction_id }}</strong> ha sido creada, pero aún está <strong>pendiente de pago</strong>.</p>

        <p>Para asegurar tu pedido, debes completar el pago dentro de los próximos <strong>5 minutos</strong>.</p>

        @if ($order->payment_link)
            <p>Puedes completar el pago haciendo clic en el siguiente enlace:</p>
            <p><a href="{{ $order->payment_link }}">{{ $order->payment_link }}</a></p>
        @endif

        <p>Si pierdes este correo, puedes iniciar sesión en tu cuenta y dirigirte a <strong>Mis órdenes</strong> para recuperar el enlace de pago.</p>
    @else
        <p>¡Gracias por tu compra! Hemos recibido tu pago exitosamente y tu orden <strong>#{{ $order->transaction_id }}</strong> ha sido generada.</p>
        <p>En breve comenzaremos a procesarla. Te notificaremos por correo electrónico cuando el estado de tu orden cambie.</p>
    @endif

    <hr>

    <h3>Resumen de tu orden:</h3>
    <ul>
        @foreach ($order->orderItems as $item)
            <li>{{ $item->product_name }} x{{ $item->quantity }} – ${{ number_format($item->total, 0, ',', '.') }}</li>
        @endforeach
    </ul>

    <p><strong>Total:</strong> ${{ number_format($order->total, 0, ',', '.') }}</p>

    <hr>

    <p>Puedes consultar el estado de tu orden en nuestro portal en cualquier momento.</p>

    <p>Gracias nuevamente por tu confianza.</p>
    <p>— El equipo de Nurae</p>
</body>
</html>
