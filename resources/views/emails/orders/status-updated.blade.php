<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Actualización de estado</title>
</head>
<body style="font-family: Arial, sans-serif; color:#111; line-height:1.5;">
  <h2>Hola {{ $order->user->first_name ?? 'Cliente' }},</h2>

  <p>Tu pedido <strong>#{{ $order->id }}</strong> ha cambiado de estado a:</p>

  <h3 style="margin:8px 0;">
    @switch(strtolower($status))
      @case('pending')
        ⏳ Pago pendiente
        @break
      @case('processing')
        🛠️ En preparación
        @break
      @case('shipped')
        📦 Enviado
        @break
      @case('delivered')
        ✅ Entregado
        @break
      @case('cancelled')
        ❌ Cancelado
        @break
      @case('failed')
        ❌ Pago fallido
        @break
      @case('pending_validation')
        🕒 En validación
        @break
      @default
        ℹ️ {{ ucfirst($status) }}
    @endswitch
  </h3>

  {{-- Mensaje contextual --}}
  @switch(strtolower($status))
    @case('pending')
      <p>Hemos recibido tu pedido y estamos a la espera de la confirmación del pago.</p>
      @break

    @case('processing')
      @if(($order->payment_status ?? '') === 'approved')
        <p>Tu pago fue <strong>aprobado</strong>. Estamos preparando tu pedido.</p>
      @else
        <p>Estamos preparando tu pedido mientras confirmamos el pago.</p>
      @endif
      @break

    @case('shipped')
      <p>Tu pedido ha sido enviado.</p>
      @break

    @case('delivered')
      <p>Tu pedido ha sido entregado. ¡Gracias por comprar con nosotros!</p>
      @break

    @case('cancelled')
      <p>Tu pedido fue cancelado. Si necesitas ayuda o deseas reintentar el pago, contáctanos.</p>
      @break

    @case('failed')
      <p>El pago no pudo completarse. Puedes intentar nuevamente desde tu cuenta o contactarnos para asistencia.</p>
      @break

    @case('pending_validation')
      <p>Tu pedido está en validación (contraentrega). Nos comunicaremos si es necesario.</p>
      @break
  @endswitch

  @if(!empty($adminMessage))
    <p><strong>Mensaje adicional:</strong></p>
    <p>{{ $adminMessage }}</p>
  @endif

  @if(!empty($trackingUrl) && strtolower($status) === 'shipped')
    <p>Puedes hacer seguimiento de tu envío aquí:</p>
    <p><a href="{{ $trackingUrl }}">{{ $trackingUrl }}</a></p>
  @endif

  <hr style="border:none;border-top:1px solid #eee; margin:16px 0;">

  <p><strong>Resumen del pedido:</strong></p>
  <ul>
    @foreach ($order->orderItems as $product)
      <li>
        {{ $product->product_name }} x{{ $product->quantity }}
        – Precio: ${{ number_format($product->price, 0, ',', '.') }}
        – Total: ${{ number_format($product->total, 0, ',', '.') }}
      </li>
    @endforeach
  </ul>

  <p><strong>Subtotal:</strong> ${{ number_format($order->subtotal, 0, ',', '.') }}</p>
  <p><strong>Impuestos:</strong> ${{ number_format($order->tax, 0, ',', '.') }}</p>
  <p><strong>Envío:</strong> ${{ number_format($order->shipping_cost, 0, ',', '.') }}</p>
  <p><strong>Total:</strong> ${{ number_format($order->total, 0, ',', '.') }}</p>

  {{-- Info útil --}}
  <hr style="border:none;border-top:1px solid #eee; margin:16px 0;">
  <p style="font-size:13px;">
    Puedes iniciar sesión en <a href="https://nurae.com.co">nuestra web</a> para ver el estado y la trazabilidad completa de tu orden en <strong>Mis Órdenes</strong>.
  </p>
  @if(!$order->user_id)
    <p style="font-size:12px; color:#555;">
      Si realizaste la compra como invitado, revisa tu correo: te enviamos acceso para consultar tu pedido.
    </p>
  @endif

  <p>Gracias por tu compra.</p>
  <p>— El equipo de Nurae</p>
</body>
</html>
