<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Orden {{ $order->payment_status === 'pending' ? 'pendiente' : 'confirmada' }}</title>
</head>
<body>
    <h2>Hola {{ $order->user->first_name }},</h2>

    <p><strong>Método de pago:</strong> 
        {{ $order->payment_method === 'contraentrega' ? 'Contraentrega' : 'Pago en línea' }}
    </p>

    @if ($order->payment_status === 'pending')
        @if ($order->payment_method === 'contraentrega')
            <p>Gracias por tu compra. Tu orden <strong>#{{ $order->id }}</strong> ha sido creada con método <strong>contraentrega</strong>.</p>
            <p>Pronto estaremos validando tu pedido y nos comunicaremos si es necesario.</p>
        @else
            <p>Gracias por tu compra. Tu orden <strong>#{{ $order->id }}</strong> ha sido creada, pero aún está <strong>pendiente de pago</strong>.</p>
            <p>Para asegurar tu pedido, debes completar el pago dentro de los próximos <strong>5 minutos</strong>.</p>
            @if ($order->payment_link)
                <p>Puedes completar el pago haciendo clic en el siguiente enlace:</p>
                <p><a href="{{ $order->payment_link }}">{{ $order->payment_link }}</a></p>
            @endif
            <p>Si pierdes este correo, puedes iniciar sesión en tu cuenta y dirigirte a <strong>Mis órdenes</strong> para recuperar el enlace de pago.</p>
        @endif
    @else
        <p>¡Gracias por tu compra! Hemos recibido tu {{ $order->payment_method === 'contraentrega' ? 'orden' : 'pago' }} exitosamente y tu orden <strong>#{{ $order->id }}</strong> ha sido generada.</p>
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

    <p>Puedes iniciar sesión en <a href="https://nurae.com.co">nuestra web</a> para seguir el estado y trazabilidad de tu orden.</p>
    @if(!$order->user_id)
        <p>Si no tienes usuario, revisa tu correo: allí te enviamos el enlace para consultar tu pedido.</p>
    @endif

    <p>Gracias nuevamente por tu confianza.</p>
    <p>— El equipo de Nurae</p>
</body>
</html>
