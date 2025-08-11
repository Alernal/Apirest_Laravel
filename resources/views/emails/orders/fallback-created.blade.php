<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <title>Problema al procesar tu orden</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body, table, td, a { font-family: Arial, Helvetica, sans-serif; }
    body { margin:0; padding:0; background-color:#f3f4f6; }
    img { border:0; outline:none; text-decoration:none; }
    table { border-collapse:collapse !important; }
    @media screen and (max-width: 600px) {
      .container { width:100% !important; }
      .px { padding-left:16px !important; padding-right:16px !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background:#f3f4f6;">
  <center style="width:100%; background:#f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td align="center" style="padding:24px;">
          <table width="600" class="container" style="max-width:600px; background:#ffffff; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
            <tr>
              <td class="px" style="padding:32px;">
                
                <h2 style="margin-top:0; font-size:22px; color:#0f172a;">
                  Hola {{ $order->user->first_name }},
                </h2>

                <p style="font-size:15px; line-height:22px; color:#334155; margin:0 0 16px 0;">
                  Hemos recibido tu pago exitosamente y te lo agradecemos mucho.
                </p>

                <p style="font-size:15px; line-height:22px; color:#334155; margin:0 0 16px 0;">
                  Sin embargo, encontramos un inconveniente al procesar tu orden <strong>#{{ $order->id }}</strong>. 
                  No te preocupes: ya registramos los datos disponibles y nuestro equipo está revisando el caso.
                </p>

                <p style="font-size:15px; line-height:22px; color:#334155; margin:0 0 16px 0;">
                  Nos pondremos en contacto contigo muy pronto para resolverlo y asegurarnos de que recibas tu pedido.
                </p>

                <p style="font-size:15px; line-height:22px; color:#334155; margin:0 0 24px 0;">
                  Por favor conserva este ID de transacción: 
                  <strong style="color:#0f172a;">{{ $order->transaction_id }}</strong>
                </p>

                <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

                <p style="font-size:14px; line-height:20px; color:#64748b; margin:0;">
                  Gracias por tu comprensión.
                </p>
                <p style="font-size:14px; line-height:20px; color:#64748b; margin:0;">
                  — El equipo de NURAE
                </p>

              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
