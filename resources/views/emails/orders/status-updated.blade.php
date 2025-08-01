<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Actualización de estado</title>
</head>
<body>
  <h2>Hola {{ $order->user->first_name }},</h2>

  <p>Te informamos que el estado de tu pedido <strong>#{{ $order->transaction_id }}</strong> ha sido actualizado a:</p>

  <h3>{{ strtoupper($status) }}</h3>

  @if($adminMessage )
    <p><strong>Mensaje del administrador:</strong></p>
    <p>{{ $adminMessage  }}</p>
  @endif

  @if($trackingUrl)
    <p>Puedes hacer seguimiento de tu pedido en el siguiente enlace:</p>
    <p><a href="{{ $trackingUrl }}">{{ $trackingUrl }}</a></p>
  @endif

  <hr>

  <p><strong>Resumen del pedido:</strong></p>
  <ul>
    @foreach ($order->orderItems as $product)
      <li>{{ $product->product_name }} x{{ $product->quantity }} – Precio: ${{ number_format($product->price, 0, ',', '.') }} - Total: ${{ number_format($product->total, 0, ',', '.') }}</li>
    @endforeach
  </ul>

  <p><strong>Total:</strong> ${{ number_format($order->total, 0, ',', '.') }}</p>

  <p>Gracias por tu compra.</p>
</body>
</html>
