<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta charset="utf-8">
  <title>Tu orden fue recibida</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!--[if mso]>
    <style> body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; } </style>
  <![endif]-->
  <style>
    html,
    body {
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      height: 100% !important;
    }

    * {
      -ms-text-size-adjust: 100%;
      -webkit-text-size-adjust: 100%;
    }

    table,
    td {
      mso-table-lspace: 0pt !important;
      mso-table-rspace: 0pt !important;
      border-collapse: collapse;
    }

    img {
      -ms-interpolation-mode: bicubic;
      border: 0;
      outline: none;
      text-decoration: none;
      display: block;
    }

    a {
      text-decoration: none;
    }

    @media screen and (max-width:600px) {
      .container {
        width: 100% !important;
      }

      .px {
        padding-left: 16px !important;
        padding-right: 16px !important;
      }

      .py {
        padding-top: 16px !important;
        padding-bottom: 16px !important;
      }

      .text-center-sm {
        text-align: center !important;
      }

      .stack {
        display: block !important;
        width: 100% !important;
      }

      .hide-sm {
        display: none !important;
      }
    }

    @media (prefers-color-scheme: dark) {

      body,
      .bg-page {
        background: #0f1115 !important;
      }

      .card {
        background: #161a20 !important;
      }

      .text {
        color: #e7e9ee !important;
      }

      .muted {
        color: #b9bfcc !important;
      }

      .hr {
        border-top: 1px solid #2a3140 !important;
      }

      .btn {
        background: #22c55e !important;
        color: #0f1115 !important;
      }
    }
  </style>
</head>

<body class="bg-page" style="margin:0; padding:0; background:#f3f4f6;">
  <!-- Preheader -->
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f3f4f6;">
    ¡Gracias! Recibimos tu orden #{{ $order->id }}. Aquí tienes el resumen.
  </div>

  @php
  $fmt = function ($n) { try { return '$'.number_format((float)$n, 0, ',', '.'); } catch (\Throwable $e) { return '$0'; } };
  $nombreCliente = $order->user->name ?? 'cliente';
  $items = $order->orderItems ?? $order->products ?? collect(); // usa la relación que tengas
  $shipping = $order->shipping_cost ?? 0;
  $subtotal = $order->subtotal ?? 0;
  $total = $order->total ?? ($subtotal + $shipping);
  $ref = $order->reference ?? $order->id;
  @endphp

  <center style="width:100%; background:#f3f4f6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td align="center" style="padding:24px;">
          <table role="presentation" width="600" class="container" style="width:600px; max-width:100%;">
            <!-- Header -->
            <tr>
              <td class="px" style="padding:24px 24px 0 24px;">
                <a href="{{ url('https://nurae.com.co') }}" target="_blank">
                  <img src="{{ asset('logo-nurae.png') }}" width="120" alt="NURAE">
                </a>
              </td>
            </tr>

            <!-- Card -->
            <tr>
              <td class="px py" style="padding:24px;">
                <table role="presentation" width="100%" class="card" style="background:#ffffff; border-radius:12px; box-shadow:0 1px 2px rgba(16,24,40,.06);">
                  <tr>
                    <td class="text" style="padding:32px; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
                      <h1 style="margin:0 0 12px 0; font-size:22px; line-height:30px; font-weight:700;">
                        🎉 ¡Gracias por tu compra, {{ $nombreCliente }}!
                      </h1>
                      <p style="margin:0 0 8px 0; font-size:14px; line-height:22px; color:#334155;">
                        Hemos recibido tu orden <strong>#{{ $order->id }}</strong>. Método de pago: <strong>{{ $order->payment_method }}</strong>.
                      </p>
                      <p style="margin:0 0 20px 0; font-size:12px; line-height:20px; color:#64748b;">
                        Referencia: <strong>{{ $ref }}</strong>
                      </p>

                      <!-- Items -->
                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 16px 0;">
                        <thead>
                          <tr>
                            <th align="left" style="padding:12px 0; font-size:12px; color:#64748b; font-weight:600; border-bottom:1px solid #e5e7eb;">Producto</th>
                            <th align="center" class="hide-sm" style="padding:12px 0; font-size:12px; color:#64748b; font-weight:600; border-bottom:1px solid #e5e7eb;">Cant.</th>
                            <th align="right" style="padding:12px 0; font-size:12px; color:#64748b; font-weight:600; border-bottom:1px solid #e5e7eb;">Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          @forelse($items as $it)
                          @php
                          $name = $it->product_name ?? $it->name ?? 'Producto';
                          $qty = $it->quantity ?? $it->pivot->quantity ?? 1;
                          $lineTotal = $it->total ?? ($it->pivot->total ?? (($it->price ?? $it->pivot->price ?? 0) * $qty));
                          @endphp
                          <tr>
                            <td style="padding:10px 0; font-size:14px; color:#0f172a;">
                              {{ $name }}
                              <span class="text-center-sm" style="display:block; font-size:12px; color:#64748b;">Cantidad: {{ $qty }}</span>
                            </td>
                            <td align="center" class="hide-sm" style="padding:10px 0; font-size:14px; color:#0f172a;">{{ $qty }}</td>
                            <td align="right" style="padding:10px 0; font-size:14px; color:#0f172a;">{{ $fmt($lineTotal) }}</td>
                          </tr>
                          @empty
                          <tr>
                            <td colspan="3" style="padding:12px 0; font-size:14px; color:#64748b;">No se encontraron ítems en la orden.</td>
                          </tr>
                          @endforelse
                        </tbody>
                      </table>

                      <!-- Resumen -->
                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">
                        <tr>
                          <td align="left" style="padding:6px 0; font-size:13px; color:#64748b;">Subtotal</td>
                          <td align="right" style="padding:6px 0; font-size:13px; color:#0f172a;">{{ $fmt($subtotal) }}</td>
                        </tr>
                        <tr>
                          <td align="left" style="padding:6px 0; font-size:13px; color:#64748b;">IVA (0%)</td>
                          <td align="right" style="padding:6px 0; font-size:13px; color:#0f172a;">$0</td>
                        </tr>
                        <tr>
                          <td align="left" style="padding:6px 0; font-size:13px; color:#64748b;">Envío</td>
                          <td align="right" style="padding:6px 0; font-size:13px; color:#0f172a;">{{ $fmt($shipping) }}</td>
                        </tr>
                        <tr>
                          <td colspan="2" class="hr" style="padding-top:8px; border-top:1px solid #e5e7eb;"></td>
                        </tr>
                        <tr>
                          <td align="left" style="padding:10px 0; font-size:15px; font-weight:700; color:#0f172a;">Total</td>
                          <td align="right" style="padding:10px 0; font-size:15px; font-weight:700; color:#0f172a;">{{ $fmt($total) }}</td>
                        </tr>
                      </table>

                      <!-- Botón -->
                      <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="left" class="stack" style="margin:20px 0 12px 0;">
                        <tr>
                          <td align="center" bgcolor="#16a34a" class="btn" style="border-radius:8px;">
                            <!--[if mso]>
                              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ url('https://nurae.com.co') }}" style="height:44px;v-text-anchor:middle;width:240px;" arcsize="12%" stroke="f" fillcolor="#16a34a">
                                <w:anchorlock/>
                                <center style="color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:bold;">
                                  Ir a NURAE
                                </center>
                              </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-- -->
                            <a href="{{ url('https://nurae.com.co') }}" target="_blank"
                              style="display:inline-block; padding:12px 20px; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; color:#ffffff; background:#16a34a; border-radius:8px;">
                              Ir a NURAE
                            </a>
                            <!--<![endif]-->
                          </td>
                        </tr>
                      </table>

                      <p class="muted" style="margin:8px 0 0 0; font-size:12px; line-height:18px; color:#64748b;">
                        Te avisaremos por correo cuando tu pedido cambie de estado. Si necesitas ayuda, responde a este mensaje.
                      </p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td class="px" style="padding:0 24px 32px 24px;">
                <table role="presentation" width="100%">
                  <tr>
                    <td class="text-center-sm" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94a3b8;">
                      © {{ date('Y') }} NURAE. Todos los derechos reservados.
                    </td>
                    <td align="right" class="text-center-sm">
                      <a href="{{ url('https://nurae.com.co') }}" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94a3b8;">
                        nurae.com.co
                      </a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </center>
</body>

</html>