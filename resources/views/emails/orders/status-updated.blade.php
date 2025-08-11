<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <title>Actualización de estado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body, table, td, a { font-family: Arial, sans-serif; }
    body { margin:0; padding:0; background-color:#f3f4f6; color:#111; }
    table { border-collapse:collapse !important; }
    a { color:#16a34a; text-decoration:none; }
    .container { max-width:600px; width:100%; margin:0 auto; background:#ffffff; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05); }
    .content { padding:32px; }
    .status { margin:8px 0; font-size:18px; }
    .muted { font-size:13px; color:#555; }
    .summary-table td { padding:6px 0; font-size:14px; }
    .summary-table td:last-child { text-align:right; }
    .hr { border:none; border-top:1px solid #eee; margin:16px 0; }
  </style>
</head>
<body>
  <center style="width:100%; background-color:#f3f4f6;">
    <table role="presentation" width="100%">
      <tr>
        <td align="center" style="padding:24px;">
          <table class="container">
            <tr>
              <td class="content">
                <h2 style="margin-top:0;">Hola {{ $order->user->first_name ?? 'Cliente' }},</h2>

                <p>Tu pedido <strong>#{{ $order->id }}</strong> ha cambiado de estado a:</p>

                <h3 class="status">
                  @switch(strtolower($status))
                    @case('pending') ⏳ Pago pendiente @break
                    @case('processing') 🛠️ En preparación @break
                    @case('shipped') 📦 Enviado @break
                    @case('delivered') ✅ Entregado @break
                    @case('cancelled') ❌ Cancelado @break
                    @case('failed') ❌ Pago fallido @break
                    @case('pending_validation') 🕒 En validación @break
                    @default ℹ️ {{ ucfirst($status) }}
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

                <hr class="hr">

                <p><strong>Resumen del pedido:</strong></p>
                <table width="100%" class="summary-table">
                  <tbody>
                    @foreach ($order->orderItems as $product)
                      <tr>
                        <td>{{ $product->product_name }} x{{ $product->quantity }}</td>
                        <td>{{ '$'.number_format($product->total, 0, ',', '.') }}</td>
                      </tr>
                    @endforeach
                    <tr>
                      <td>Subtotal</td>
                      <td>{{ '$'.number_format($order->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                      <td>IVA (0%)</td>
                      <td>$0</td>
                    </tr>
                    <tr>
                      <td>Envío</td>
                      <td>{{ '$'.number_format($order->shipping_cost, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                      <td><strong>Total</strong></td>
                      <td><strong>{{ '$'.number_format($order->total, 0, ',', '.') }}</strong></td>
                    </tr>
                  </tbody>
                </table>

                <hr class="hr">

                <p class="muted">
                  Puedes iniciar sesión en <a href="https://nurae.com.co">nuestra web</a> para ver el estado y la trazabilidad completa de tu orden en <strong>Mis Órdenes</strong>.
                </p>
                @if(!$order->user_id)
                  <p class="muted">
                    Si realizaste la compra como invitado, revisa tu correo: te enviamos acceso para consultar tu pedido.
                  </p>
                @endif

                <p>Gracias por tu compra.</p>
                <p>— El equipo de NURAE</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
